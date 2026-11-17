<?php

namespace App\Console\Commands;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Vacía los datos de prueba: borra TODOS los cursos (con todo lo que cuelga de
 * ellos) y TODOS los usuarios salvo los profesores y los ids indicados en
 * --conservar-usuarios (con todo lo ligado a esos usuarios). No siembra nada.
 *
 * - Solo DELETE de filas acotado por valores explícitos. No toca el esquema,
 *   triggers, roles/permisos, migraciones ni tablas de configuración.
 * - Qué se borra se calcula desde las FK reales: toda fila que referencia una
 *   fila borrada se borra también (recursivo), hijos antes que padres, así
 *   ningún ON DELETE CASCADE borra algo no previsto. Sin FK: roles/permisos
 *   asignados, notificaciones, sesiones y tokens de clave de los usuarios
 *   borrados; notificaciones que apuntan a /cursos/{id}; propuestas del bot
 *   ligadas a un curso (y su evento de calendario).
 * - La bitácora de auditoría es inmutable (trigger): no se borra; sus FK
 *   SET NULL dejan curso_id / ejecutado_por / usuario_afectado_id en NULL,
 *   lo único que el trigger permite.
 *
 * Sin --apply simula en una transacción con rollback. Con --apply exige
 * --conservar-usuarios y escribir VACIAR, respalda cada fila borrada en
 * storage/app/backups-demo/<fecha-hora>_vaciado/ y borra en UNA transacción
 * con verificación posterior (rollback total si algo falla).
 */
class VaciarDatosPrueba extends Command
{
    protected $signature = 'app:vaciar-datos-prueba
        {--apply : Borra de verdad (sin esta opción solo simula)}
        {--conservar-usuarios= : Ids de usuarios a conservar además de todos los profesores (p. ej. 41,42)}
        {--restore= : Carpeta de respaldo a restaurar (ruta completa o nombre dentro de storage/app/backups-demo)}';

    protected $description = 'Borra todos los cursos y los usuarios que no son profesores ni se conservan (simula por defecto)';

    /** Tablas cuyo contenido debe quedar idéntico. */
    protected const TABLAS_INTOCABLES = ['roles', 'permissions', 'role_has_permissions', 'migrations', 'configuraciones_plataforma', 'integraciones_externas', 'logs_integracion_api', 'failed_jobs', 'jobs', 'job_batches'];

    /** Tabla inmutable: no se borra, sus FK quedan en NULL. */
    protected const BITACORA = 'bitacoras_auditoria';

    /** @var list<object{hija: string, columna: string, padre: string, columna_padre: string}>|null */
    protected ?array $relaciones = null;

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta con APP_ENV=production.');

            return self::FAILURE;
        }

        $aplicar = (bool) $this->option('apply');

        if ($this->option('restore')) {
            return $this->restaurar((string) $this->option('restore'), $aplicar);
        }

        $extra = $this->idsAConservar();

        if ($extra === null) {
            return self::FAILURE;
        }

        if ($aplicar && $extra === []) {
            $this->error('--apply exige --conservar-usuarios=ID,ID con los ids confirmados (además de los profesores, que siempre se conservan).');

            return self::FAILURE;
        }

        $conservados = $this->usuariosConservados($extra);
        $plan = $this->plan($conservados);
        $this->reportar($plan, $conservados);

        if ($aplicar && $this->ask('Esto borra los datos de una base COMPARTIDA. Escribe VACIAR para confirmar') !== 'VACIAR') {
            $this->error('Cancelado: no se escribió VACIAR. La base no cambió.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $antes = $this->foto($conservados);
            $planFinal = $this->plan($conservados);

            if ($this->conteos($planFinal) !== $this->conteos($plan)) {
                throw new RuntimeException('La base cambió entre el reporte y la ejecución; vuelve a ejecutar el comando.');
            }

            $orden = $this->orden(array_keys($planFinal));
            $carpeta = $aplicar ? $this->respaldar($planFinal, $orden, $conservados) : null;
            $huerfanosAntes = $this->archivosHuerfanos();
            $borradas = $this->borrar($planFinal, $orden);
            $errores = $this->verificar($planFinal, $borradas, $antes, $conservados);
            $this->mostrarAntesDespues($antes['conteos'], $borradas);

            if ($errores !== []) {
                DB::rollBack();
                $this->error('Verificación fallida; se hizo rollback y no se borró nada:');
                foreach ($errores as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->info('Verificación OK: usuarios y roles previstos; roles/permisos/migraciones/configuración idénticos; tablas, triggers y funciones idénticos; 0 filas ligadas a cursos; 0 FK huérfanas.');
            $huerfanos = $this->archivosHuerfanos();
            $this->mostrarArchivos($huerfanosAntes, $huerfanos);

            if (! $aplicar) {
                DB::rollBack();
                $this->info('SIMULACIÓN: se hizo rollback, la base no cambió. Para aplicar: --apply --conservar-usuarios=ID,ID');

                return self::SUCCESS;
            }

            $this->escribirJson("{$carpeta}/archivos_huerfanos.json", $huerfanos);
            $this->actualizarManifiesto($carpeta);
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info('APLICADO.');
        $this->line("Respaldo: {$carpeta}");
        $this->line("Para revertir: php artisan app:vaciar-datos-prueba --restore=\"{$carpeta}\" --apply");
        $this->warn('El respaldo contiene datos personales: bórralo a mano cuando ya no haga falta. La base es compartida: avisa al equipo.');

        return self::SUCCESS;
    }

    // ==========================================
    // USUARIOS
    // ==========================================

    /**
     * Ids de --conservar-usuarios validados; null si hay un error (ya informado).
     *
     * @return list<int>|null
     */
    protected function idsAConservar(): ?array
    {
        $texto = trim((string) $this->option('conservar-usuarios'));

        if ($texto === '') {
            return [];
        }

        $partes = array_map('trim', explode(',', $texto));

        if (collect($partes)->contains(fn (string $parte) => ! ctype_digit($parte))) {
            $this->error("--conservar-usuarios debe ser una lista de ids numéricos separados por comas; se recibió \"{$texto}\".");

            return null;
        }

        $ids = array_values(array_unique(array_map('intval', $partes)));
        $faltan = array_diff($ids, DB::table('users')->whereIn('id', $ids)->pluck('id')->map(fn ($id) => (int) $id)->all());

        if ($faltan !== []) {
            $this->error('No existen usuarios con id: '.implode(', ', $faltan).'.');

            return null;
        }

        return $ids;
    }

    /**
     * Todos los profesores más los ids indicados.
     *
     * @param  list<int>  $extra
     * @return list<int>
     */
    protected function usuariosConservados(array $extra): array
    {
        $profesores = $this->usuariosConRoles()->filter(fn (object $usuario) => in_array(Rol::Profesor->value, $usuario->roles, true))->pluck('id')->all();
        $ids = array_values(array_unique([...$profesores, ...$extra]));
        sort($ids);

        return $ids;
    }

    /** @return Collection<int, object{id: int, name: string, email: string, activo: mixed, roles: list<string>}> */
    protected function usuariosConRoles(): Collection
    {
        $roles = DB::table('model_has_roles as m')->join('roles as r', 'r.id', '=', 'm.role_id')
            ->where('m.model_type', (new User)->getMorphClass())->orderBy('r.name')->get(['m.model_id', 'r.name'])
            ->groupBy('model_id')->map(fn (Collection $filas) => $filas->pluck('name')->all());

        return DB::table('users')->orderBy('id')->get(['id', 'name', 'email', 'activo'])
            ->map(function (object $usuario) use ($roles) {
                $usuario->id = (int) $usuario->id;
                $usuario->roles = $roles[$usuario->id] ?? [];

                return $usuario;
            });
    }

    // ==========================================
    // PLAN DE BORRADO
    // ==========================================

    /**
     * Filas a borrar por tabla: lista de condiciones unidas con OR; dentro de cada
     * condición, columna => valores unidas con AND.
     *
     * @param  list<int>  $conservados
     * @return array<string, list<array<string, list<int|string>>>>
     */
    protected function plan(array $conservados): array
    {
        $cursos = DB::table('cursos')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $usuarios = DB::table('users')->whereNotIn('id', $conservados)->orderBy('id')->get(['id', 'email']);
        $idsUsuarios = $usuarios->pluck('id')->map(fn ($id) => (int) $id)->all();
        $tipoUsuario = (new User)->getMorphClass();

        $plan = ['cursos' => [['id' => $cursos]], 'users' => [['id' => $idsUsuarios]]];

        foreach (['model_has_roles', 'model_has_permissions'] as $tabla) {
            $plan[$tabla] = [['model_type' => [$tipoUsuario], 'model_id' => $idsUsuarios]];
        }

        $plan['notifications'] = [['notifiable_type' => [$tipoUsuario], 'notifiable_id' => $idsUsuarios], ['id' => $this->notificacionesDeCursos($cursos)]];
        $plan['sessions'] = [['user_id' => $idsUsuarios]];
        $plan['password_reset_tokens'] = [['email' => $usuarios->pluck('email')->filter()->values()->all()]];

        $propuestas = DB::table('propuestas_bot')->get(['id', 'datos_propuesta'])
            ->filter(fn (object $fila) => in_array((int) ($this->datos($fila->datos_propuesta)['curso_id'] ?? 0), $cursos, true));
        $plan['propuestas_bot'] = [['id' => $propuestas->pluck('id')->map(fn ($id) => (int) $id)->values()->all()]];
        $plan['eventos_calendario'] = [['id' => $propuestas->map(fn (object $fila) => (int) ($this->datos($fila->datos_propuesta)['evento_calendario_id'] ?? 0))->filter()->unique()->values()->all()]];

        $plan = array_filter($plan, fn (array $condiciones, string $tabla) => Schema::hasTable($tabla), ARRAY_FILTER_USE_BOTH);

        return $this->propagarPorFk($plan);
    }

    /**
     * Agrega, hasta el punto fijo, toda fila que referencia por FK a una fila del plan.
     *
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @return array<string, list<array<string, list<int|string>>>>
     */
    protected function propagarPorFk(array $plan): array
    {
        $pendientes = array_keys($plan);

        while ($pendientes !== []) {
            $tabla = array_shift($pendientes);

            foreach ($this->relaciones() as $fk) {
                if ($fk->padre !== $tabla || $fk->hija === self::BITACORA) {
                    continue;
                }

                $valores = $this->consulta($tabla, $plan[$tabla])->whereNotNull($fk->columna_padre)->distinct()->pluck($fk->columna_padre)->all();

                if ($valores === []) {
                    continue;
                }

                $condiciones = $plan[$fk->hija] ?? [];
                $indice = collect($condiciones)->search(fn (array $condicion) => array_keys($condicion) === [$fk->columna]);
                $actuales = $indice === false ? [] : $condiciones[$indice][$fk->columna];
                $nuevos = array_values(array_unique([...$actuales, ...$valores]));

                if (count($nuevos) === count($actuales)) {
                    continue;
                }

                if ($indice === false) {
                    $condiciones[] = [$fk->columna => $nuevos];
                } else {
                    $condiciones[$indice][$fk->columna] = $nuevos;
                }

                $plan[$fk->hija] = $condiciones;

                if (! in_array($fk->hija, $pendientes, true)) {
                    $pendientes[] = $fk->hija;
                }
            }
        }

        return $plan;
    }

    /** @param  list<array<string, list<int|string>>>  $condiciones */
    protected function consulta(string $tabla, array $condiciones): Builder
    {
        $condiciones = array_values(array_filter($condiciones, fn (array $condicion) => $condicion !== [] && ! in_array([], $condicion, true)));

        if ($condiciones === []) {
            return DB::table($tabla)->whereRaw('1 = 0');
        }

        return DB::table($tabla)->where(function (Builder $consulta) use ($condiciones) {
            foreach ($condiciones as $condicion) {
                $consulta->orWhere(function (Builder $consulta) use ($condicion) {
                    foreach ($condicion as $columna => $valores) {
                        $consulta->whereIn($columna, $valores);
                    }
                });
            }
        });
    }

    /**
     * Orden de borrado: cada tabla después de todas sus hijas del plan (hijo → padre).
     *
     * @param  list<string>  $tablas
     * @return list<string>
     */
    protected function orden(array $tablas): array
    {
        $hijas = [];
        foreach ($this->relaciones() as $fk) {
            if ($fk->hija !== $fk->padre && in_array($fk->hija, $tablas, true) && in_array($fk->padre, $tablas, true)) {
                $hijas[$fk->padre][] = $fk->hija;
            }
        }

        $orden = [];
        $restantes = $tablas;
        sort($restantes);

        while ($restantes !== []) {
            $listas = array_values(array_filter($restantes, fn (string $tabla) => array_diff($hijas[$tabla] ?? [], $orden) === []));

            if ($listas === []) {
                throw new RuntimeException('Ciclo de FK entre: '.implode(', ', $restantes).'; no hay un orden de borrado seguro.');
            }

            $orden = [...$orden, ...$listas];
            $restantes = array_values(array_diff($restantes, $listas));
        }

        return $orden;
    }

    /**
     * FK reales del esquema (public en PostgreSQL).
     *
     * @return list<object{hija: string, columna: string, padre: string, columna_padre: string}>
     */
    protected function relaciones(): array
    {
        if ($this->relaciones !== null) {
            return $this->relaciones;
        }

        if (DB::getDriverName() === 'pgsql') {
            $filas = DB::select("select c.conrelid::regclass::text as hija, a.attname as columna, c.confrelid::regclass::text as padre, af.attname as columna_padre, array_length(c.conkey, 1) as columnas
                from pg_constraint c
                join pg_namespace n on n.oid = c.connamespace
                join pg_attribute a on a.attrelid = c.conrelid and a.attnum = c.conkey[1]
                join pg_attribute af on af.attrelid = c.confrelid and af.attnum = c.confkey[1]
                where c.contype = 'f' and n.nspname = 'public' order by 1, 2");

            foreach ($filas as $fila) {
                if ((int) $fila->columnas !== 1) {
                    throw new RuntimeException("FK compuesta en {$fila->hija}: no soportada, no se borra nada.");
                }
            }
        } else {
            $filas = collect($this->tablas())->flatMap(fn (string $tabla) => collect(Schema::getForeignKeys($tabla))->map(fn (array $fk) => (object) [
                'hija' => $tabla, 'columna' => $fk['columns'][0], 'padre' => $fk['foreign_table'], 'columna_padre' => $fk['foreign_columns'][0],
            ]))->all();
        }

        return $this->relaciones = array_map(fn (object $fila) => (object) ['hija' => $fila->hija, 'columna' => $fila->columna, 'padre' => $fila->padre, 'columna_padre' => $fila->columna_padre], $filas);
    }

    /** @return list<string> */
    protected function tablas(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            return array_column(DB::select("select table_name from information_schema.tables where table_schema = 'public' and table_type = 'BASE TABLE' order by 1"), 'table_name');
        }

        return collect(Schema::getTables())->pluck('name')->reject(fn (string $tabla) => str_starts_with($tabla, 'sqlite_'))->sort()->values()->all();
    }

    /**
     * Notificaciones cuyo data.url apunta a /cursos/{id} de uno de estos cursos.
     *
     * @param  list<int>  $cursos
     * @return list<string>
     */
    protected function notificacionesDeCursos(array $cursos): array
    {
        return DB::table('notifications')->get(['id', 'data'])
            ->filter(fn (object $fila) => preg_match('~/cursos/(\d+)(?:/|$|\?)~', (string) ($this->datos($fila->data)['url'] ?? ''), $m) === 1 && in_array((int) $m[1], $cursos, true))
            ->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }

    /** @return array<string, mixed> */
    protected function datos(mixed $json): array
    {
        return is_array($json) ? $json : (json_decode((string) $json, true) ?: []);
    }

    /**
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @return array<string, int>
     */
    protected function conteos(array $plan): array
    {
        $conteos = collect($plan)->map(fn (array $condiciones, string $tabla) => $this->consulta($tabla, $condiciones)->count())->all();
        ksort($conteos);

        return $conteos;
    }

    /**
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @param  list<string>  $orden
     * @return array<string, int>
     */
    protected function borrar(array $plan, array $orden): array
    {
        $borradas = [];
        foreach ($orden as $tabla) {
            $borradas[$tabla] = $this->consulta($tabla, $plan[$tabla])->delete();
        }

        return $borradas;
    }

    // ==========================================
    // REPORTE
    // ==========================================

    /**
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @param  list<int>  $conservados
     */
    protected function reportar(array $plan, array $conservados): void
    {
        $conteos = $this->conteos($plan);
        $orden = $this->orden(array_keys($plan));

        $this->info('1) Se BORRARÍAN, en este orden (hijo → padre):');
        $this->table(['#', 'Tabla', 'Filas a borrar', 'Filas actuales'], collect($orden)->values()->map(fn (string $tabla, int $i) => [$i + 1, $tabla, $conteos[$tabla], DB::table($tabla)->count()])->all());

        $usuarios = $this->usuariosConRoles();
        [$quedan, $van] = $usuarios->partition(fn (object $usuario) => in_array($usuario->id, $conservados, true));
        $fila = fn (object $usuario) => [$usuario->id, $usuario->name, $usuario->email, implode(', ', $usuario->roles) ?: '(sin rol)', $usuario->activo ? 'activo' : 'inactivo'];

        $this->info("2) Usuarios que se CONSERVAN ({$quedan->count()}):");
        $this->table(['Id', 'Nombre', 'Email', 'Rol(es)', 'Estado'], $quedan->map($fila)->values()->all());
        $this->info("3) Usuarios que se BORRAN ({$van->count()}): ".$van->flatMap(fn (object $usuario) => $usuario->roles ?: ['(sin rol)'])->countBy()->map(fn (int $n, string $rol) => "{$rol} {$n}")->join(', '));
        $this->table(['Id', 'Nombre', 'Email', 'Rol(es)', 'Estado'], $van->map($fila)->values()->all());

        if ($quedan->filter(fn (object $usuario) => in_array(Rol::Supervisor->value, $usuario->roles, true))->isEmpty()) {
            $this->error('ALERTA: tras el borrado quedarían 0 usuarios con rol supervisor. Nadie podría administrar la plataforma.');
        }

        $cursos = $plan['cursos'][0]['id'];
        $usuariosBorrados = $plan['users'][0]['id'];
        $bitacora = DB::table(self::BITACORA);
        $this->info('4) Bitácora de auditoría (inmutable, NO se borra): '.$bitacora->count().' filas se conservan; quedarían en NULL '
            .DB::table(self::BITACORA)->whereIn('curso_id', $cursos)->count().' curso_id, '
            .DB::table(self::BITACORA)->whereIn('ejecutado_por', $usuariosBorrados)->count().' ejecutado_por y '
            .DB::table(self::BITACORA)->whereIn('usuario_afectado_id', $usuariosBorrados)->count().' usuario_afectado_id.');

        $intactas = collect($this->tablas())->reject(fn (string $tabla) => array_key_exists($tabla, $plan) || $tabla === self::BITACORA)
            ->map(fn (string $tabla) => $tabla.' ('.DB::table($tabla)->count().')');
        $this->info('5) Tablas que NO se tocan: '.$intactas->join(', '));

        $huerfanas = DB::table('notifications')->where('notifiable_type', (new User)->getMorphClass())
            ->whereNotIn('notifiable_id', DB::table('users')->select('id'))->count();
        if ($huerfanas > 0) {
            $this->warn("Aviso: ya hay {$huerfanas} notificaciones de usuarios que no existen; no se tocan (no son de los usuarios que se borran).");
        }
    }

    /**
     * @param  array<string, int>  $antes
     * @param  array<string, int>  $borradas
     */
    protected function mostrarAntesDespues(array $antes, array $borradas): void
    {
        $filas = collect([...array_keys($borradas), self::BITACORA])->unique()
            ->map(fn (string $tabla) => [$tabla, $antes[$tabla] ?? 0, $borradas[$tabla] ?? 0, DB::table($tabla)->count()])->values()->all();
        $this->table(['Tabla', 'Antes', 'Borradas', 'Después'], $filas);
    }

    /**
     * @param  array<string, list<string>>  $antes
     * @param  array<string, list<string>>  $despues
     */
    protected function mostrarArchivos(array $antes, array $despues): void
    {
        $this->info('Archivos en storage sin ninguna fila que los referencie (NO se borran):');
        $this->table(['Disco/carpeta', 'Huérfanos antes', 'Huérfanos después'], collect(array_keys($antes + $despues))
            ->map(fn (string $carpeta) => [$carpeta, count($antes[$carpeta] ?? []), count($despues[$carpeta] ?? [])])->all());
    }

    /**
     * Archivos de los discos local y public que ninguna fila menciona, agrupados por carpeta.
     *
     * @return array<string, list<string>>
     */
    protected function archivosHuerfanos(): array
    {
        $referencias = collect($this->tablas())->flatMap(function (string $tabla) {
            $columnas = collect(Schema::getColumnListing($tabla))->filter(fn (string $columna) => preg_match('/url|path|archivo|adjunto|materiales|recursos|imagen|avatar/i', $columna))->values()->all();

            return $columnas === [] ? [] : DB::table($tabla)->get($columnas)->flatMap(fn (object $fila) => array_values((array) $fila));
        })->filter(fn ($valor) => is_string($valor) && $valor !== '')->values()->all();
        $texto = implode("\n", $referencias);
        $huerfanos = [];

        foreach (['local', 'public'] as $disco) {
            foreach (Storage::disk($disco)->allFiles() as $archivo) {
                if (str_ends_with($archivo, '.gitignore') || str_starts_with($archivo, 'backups-demo/') || ! str_contains($archivo, '/') || str_contains($texto, $archivo)) {
                    continue;
                }

                $huerfanos["{$disco}:".dirname($archivo)][] = $archivo;
            }
        }

        ksort($huerfanos);

        return $huerfanos;
    }

    // ==========================================
    // RESPALDO
    // ==========================================

    /**
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @param  list<string>  $orden
     * @param  list<int>  $conservados
     */
    protected function respaldar(array $plan, array $orden, array $conservados): string
    {
        $carpeta = storage_path('app/backups-demo/'.now()->format('Y-m-d_His').'_vaciado');
        File::ensureDirectoryExists("{$carpeta}/filas");
        $conteos = [];

        foreach ($orden as $tabla) {
            $filas = $this->consulta($tabla, $plan[$tabla])->get()->map(fn (object $fila) => (array) $fila)->all();
            $conteos[$tabla] = count($filas);
            $this->escribirJson("{$carpeta}/filas/{$tabla}.json", $filas);
        }

        $cursos = $plan['cursos'][0]['id'];
        $usuarios = $plan['users'][0]['id'];
        $this->escribirJson("{$carpeta}/bitacora_desvinculada.json", DB::table(self::BITACORA)
            ->where(fn (Builder $q) => $q->whereIn('curso_id', $cursos)->orWhereIn('ejecutado_por', $usuarios)->orWhereIn('usuario_afectado_id', $usuarios))
            ->orderBy('id')->get(['id', 'curso_id', 'ejecutado_por', 'usuario_afectado_id'])->map(fn (object $fila) => (array) $fila)->all());
        $this->escribirJson("{$carpeta}/manifest.json", [
            'comando' => 'app:vaciar-datos-prueba',
            'fecha' => now()->toIso8601String(),
            'usuarios_conservados' => $conservados,
            'usuarios_borrados' => $usuarios,
            'cursos_borrados' => $cursos,
            'orden_borrado' => $orden,
            'conteos' => $conteos,
        ]);
        $this->actualizarManifiesto($carpeta);
        $this->line("Respaldo previo escrito en {$carpeta}");

        return $carpeta;
    }

    // ==========================================
    // VERIFICACIÓN
    // ==========================================

    /**
     * Huella del esquema, conteos y contenido de lo que debe quedar intacto.
     *
     * @param  list<int>  $conservados
     * @return array{esquema: string, objetos: array<string, int>, conteos: array<string, int>, intocables: array<string, string>, usuarios: string, roles_usuarios: string, permisos_usuarios: string}
     */
    protected function foto(array $conservados): array
    {
        $tipoUsuario = (new User)->getMorphClass();

        return [
            'esquema' => $this->huellaEsquema(),
            'objetos' => $this->objetosEsquema(),
            'conteos' => collect($this->tablas())->mapWithKeys(fn (string $tabla) => [$tabla => DB::table($tabla)->count()])->all(),
            'intocables' => collect(self::TABLAS_INTOCABLES)->filter(fn (string $tabla) => Schema::hasTable($tabla))->mapWithKeys(fn (string $tabla) => [$tabla => $this->huella(DB::table($tabla))])->all(),
            'usuarios' => $this->huella(DB::table('users')->whereIn('id', $conservados)),
            'roles_usuarios' => $this->huella(DB::table('model_has_roles')->where('model_type', $tipoUsuario)->whereIn('model_id', $conservados)),
            'permisos_usuarios' => $this->huella(DB::table('model_has_permissions')->where('model_type', $tipoUsuario)->whereIn('model_id', $conservados)),
        ];
    }

    protected function huella(Builder $consulta): string
    {
        return md5($consulta->get()->map(fn (object $fila) => json_encode($fila))->sort()->implode('|'));
    }

    protected function huellaEsquema(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return md5(json_encode([
                DB::select("select table_name, column_name, data_type, is_nullable, column_default from information_schema.columns where table_schema = 'public' order by 1, 2"),
                DB::select("select table_name, constraint_name, constraint_type from information_schema.table_constraints where table_schema = 'public' order by 1, 2"),
                DB::select("select tablename, indexname, indexdef from pg_indexes where schemaname = 'public' order by 1, 2"),
                DB::select("select c.relname, t.tgname, pg_get_triggerdef(t.oid) as definicion from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and not t.tgisinternal order by 1, 2"),
                DB::select("select p.proname, pg_get_function_identity_arguments(p.oid) as args, md5(p.prosrc) as cuerpo from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public' order by 1, 2"),
                DB::select("select tablename, policyname, cmd, qual, with_check from pg_policies where schemaname = 'public' order by 1, 2"),
            ]));
        }

        return md5(json_encode(DB::select('select type, name, sql from sqlite_master order by type, name')));
    }

    /** @return array{tablas: int, triggers: int, funciones: int} */
    protected function objetosEsquema(): array
    {
        if (DB::getDriverName() === 'pgsql') {
            return [
                'tablas' => count($this->tablas()),
                'triggers' => (int) DB::selectOne("select count(*) as n from pg_trigger t join pg_class c on c.oid = t.tgrelid join pg_namespace n on n.oid = c.relnamespace where n.nspname = 'public' and not t.tgisinternal")->n,
                'funciones' => (int) DB::selectOne("select count(*) as n from pg_proc p join pg_namespace n on n.oid = p.pronamespace where n.nspname = 'public'")->n,
            ];
        }

        return [
            'tablas' => count($this->tablas()),
            'triggers' => (int) DB::selectOne("select count(*) as n from sqlite_master where type = 'trigger'")->n,
            'funciones' => 0,
        ];
    }

    /**
     * @param  array<string, list<array<string, list<int|string>>>>  $plan
     * @param  array<string, int>  $borradas
     * @param  array<string, mixed>  $antes
     * @param  list<int>  $conservados
     * @return list<string>
     */
    protected function verificar(array $plan, array $borradas, array $antes, array $conservados): array
    {
        $errores = [];
        $despues = $this->foto($conservados);
        $tipoUsuario = (new User)->getMorphClass();

        // (a) Exactamente los usuarios previstos, con sus mismos datos y roles.
        $usuarios = DB::table('users')->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $usuarios === $conservados || $errores[] = 'quedaron los usuarios ['.implode(',', $usuarios).'] en vez de ['.implode(',', $conservados).']';
        $this->huella(DB::table('users')) === $antes['usuarios'] || $errores[] = 'cambiaron los datos de los usuarios conservados';
        $this->huella(DB::table('model_has_roles')->where('model_type', $tipoUsuario)) === $antes['roles_usuarios'] || $errores[] = 'cambiaron los roles de los usuarios conservados';
        $this->huella(DB::table('model_has_permissions')->where('model_type', $tipoUsuario)) === $antes['permisos_usuarios'] || $errores[] = 'cambiaron los permisos de los usuarios conservados';

        // (b) Roles, permisos, migraciones y configuración idénticos.
        foreach ($antes['intocables'] as $tabla => $huella) {
            ($despues['intocables'][$tabla] ?? null) === $huella || $errores[] = "cambió el contenido de {$tabla}";
        }

        // (c) Esquema: mismo número de tablas, triggers y funciones, y misma definición.
        foreach ($antes['objetos'] as $objeto => $cantidad) {
            $despues['objetos'][$objeto] === $cantidad || $errores[] = "{$objeto}: había {$cantidad} y quedan {$despues['objetos'][$objeto]}";
        }
        $despues['esquema'] === $antes['esquema'] || $errores[] = 'cambió el esquema (columnas, constraints, índices, triggers, funciones o políticas)';

        // Cada tabla perdió exactamente lo planeado (ningún CASCADE borró algo no previsto).
        foreach ($antes['conteos'] as $tabla => $cantidad) {
            $esperado = $cantidad - ($borradas[$tabla] ?? 0);
            $despues['conteos'][$tabla] === $esperado || $errores[] = "{$tabla}: quedan {$despues['conteos'][$tabla]} filas y se esperaban {$esperado}";
        }
        $conteos = $this->conteos($plan);
        foreach ($borradas as $tabla => $filas) {
            ($conteos[$tabla] ?? 0) === 0 || $errores[] = "{$tabla}: quedan {$conteos[$tabla]} filas del plan sin borrar";
        }

        // (d) 0 filas ligadas a cursos (la bitácora se conserva con curso_id NULL).
        DB::table('cursos')->count() === 0 || $errores[] = 'quedaron cursos';
        foreach ($this->ligadasACursos() as $tabla => $filas) {
            $filas === 0 || $errores[] = "{$tabla}: quedan {$filas} filas ligadas a cursos";
        }
        DB::table(self::BITACORA)->whereNotNull('curso_id')->count() === 0 || $errores[] = 'la bitácora aún apunta a cursos';

        // (e) 0 FK huérfanas.
        foreach ($this->violacionesDeFk() as $violacion) {
            $errores[] = $violacion;
        }

        return $errores;
    }

    /**
     * Filas de las tablas que dependen (recursivamente) de cursos que aún tienen la FK hacia esa cadena.
     *
     * @return array<string, int>
     */
    protected function ligadasACursos(): array
    {
        $cadena = ['cursos'];
        do {
            $nuevas = collect($this->relaciones())->filter(fn (object $fk) => in_array($fk->padre, $cadena, true) && ! in_array($fk->hija, $cadena, true) && $fk->hija !== self::BITACORA)->pluck('hija')->unique()->all();
            $cadena = [...$cadena, ...$nuevas];
        } while ($nuevas !== []);

        return collect($this->relaciones())->filter(fn (object $fk) => in_array($fk->padre, $cadena, true) && $fk->hija !== self::BITACORA)
            ->groupBy('hija')->map(fn (Collection $fks, string $tabla) => DB::table($tabla)->where(function (Builder $q) use ($fks) {
                foreach ($fks as $fk) {
                    $q->orWhereNotNull($fk->columna);
                }
            })->count())->all();
    }

    /** @return list<string> */
    protected function violacionesDeFk(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA foreign_key_check'))->map(fn ($fila) => "huérfano en {$fila->table} hacia {$fila->parent}")->all();
        }

        return collect($this->relaciones())->map(function (object $fk) {
            $huerfanas = DB::table("{$fk->hija} as h")->whereNotNull("h.{$fk->columna}")
                ->whereNotExists(fn ($q) => $q->from("{$fk->padre} as p")->whereColumn("p.{$fk->columna_padre}", "h.{$fk->columna}"))->count();

            return $huerfanas > 0 ? "{$huerfanas} huérfanos en {$fk->hija}.{$fk->columna} → {$fk->padre}" : null;
        })->filter()->values()->all();
    }

    // ==========================================
    // RESTAURACIÓN
    // ==========================================

    protected function restaurar(string $carpeta, bool $aplicar): int
    {
        $carpeta = is_dir($carpeta) ? $carpeta : storage_path('app/backups-demo/'.$carpeta);

        if (! is_file("{$carpeta}/manifest.json")) {
            $this->error("No hay manifest.json en {$carpeta}.");

            return self::FAILURE;
        }

        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);

        if (($manifiesto['comando'] ?? null) !== 'app:vaciar-datos-prueba') {
            $this->error('Ese respaldo no es de app:vaciar-datos-prueba.');

            return self::FAILURE;
        }

        foreach ($manifiesto['sha256'] ?? [] as $archivo => $suma) {
            if (hash_file('sha256', "{$carpeta}/{$archivo}") !== $suma) {
                $this->error("El archivo {$archivo} no coincide con su SHA-256: el respaldo está alterado.");

                return self::FAILURE;
            }
        }

        if (DB::table('users')->whereIn('id', $manifiesto['usuarios_borrados'])->exists() || DB::table('cursos')->whereIn('id', $manifiesto['cursos_borrados'])->exists()) {
            $this->error('Algunos usuarios o cursos del respaldo ya existen en la base: no se restaura nada.');

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $filasPorTabla = [];

            foreach (array_reverse($manifiesto['orden_borrado']) as $tabla) {
                $filas = collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"));
                $filas = array_key_exists('id', $filas->first() ?? []) ? $filas->sortBy('id')->values() : $filas;

                if ($filas->count() !== (int) $manifiesto['conteos'][$tabla]) {
                    throw new RuntimeException("{$tabla}: el archivo no coincide con el manifiesto");
                }

                foreach ($filas->chunk(200) as $tanda) {
                    DB::table($tabla)->insert($tanda->values()->all());
                }

                $filasPorTabla[] = [$tabla, $filas->count()];

                if (DB::getDriverName() === 'pgsql' && $filas->isNotEmpty() && is_int($filas->first()['id'] ?? null)) {
                    $secuencia = DB::selectOne("select pg_get_serial_sequence('{$tabla}', 'id') as s")->s;
                    $secuencia && DB::statement("select setval('{$secuencia}', greatest((select max(id) from {$tabla}), (select last_value from {$secuencia})))");
                }
            }

            $this->table(['Tabla', 'Filas reinsertadas'], $filasPorTabla);
            $huerfanos = $this->violacionesDeFk();

            if ($huerfanos !== []) {
                DB::rollBack();
                $this->error('La restauración dejaría huérfanos; no se escribió nada: '.implode('; ', $huerfanos));

                return self::FAILURE;
            }

            $aplicar ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->warn('La bitácora no se puede revertir (trigger de inmutabilidad): sus filas siguen con curso_id / ejecutado_por / usuario_afectado_id en NULL (detalle en bitacora_desvinculada.json).');
        $this->info($aplicar ? 'RESTAURADO.' : 'SIMULACIÓN de restauración: rollback, la base no cambió. Usa --apply para restaurar.');

        return self::SUCCESS;
    }

    // ==========================================
    // AUXILIARES
    // ==========================================

    protected function actualizarManifiesto(string $carpeta): void
    {
        $ruta = "{$carpeta}/manifest.json";
        $manifiesto = json_decode(File::get($ruta), true);
        $manifiesto['sha256'] = collect(File::allFiles($carpeta))
            ->reject(fn ($archivo) => $archivo->getFilename() === 'manifest.json' && $archivo->getRelativePath() === '')
            ->mapWithKeys(fn ($archivo) => [str_replace('\\', '/', $archivo->getRelativePathname()) => hash_file('sha256', $archivo->getPathname())])
            ->sortKeys()->all();
        $this->escribirJson($ruta, $manifiesto);
    }

    protected function escribirJson(string $ruta, mixed $datos): void
    {
        File::put($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<int|string, mixed> */
    protected function leerJson(string $ruta): array
    {
        return is_file($ruta) ? json_decode(File::get($ruta), true) : [];
    }
}
