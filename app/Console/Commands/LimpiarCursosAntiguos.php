<?php

namespace App\Console\Commands;

use App\Livewire\Docente\PorCalificar\Index as PorCalificar;
use App\Models\Entrega;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Borra los cursos ANTIGUOS (archivados o con fecha de fin anterior a hoy, de
 * cualquier profesor) y todas las filas que cuelgan de ellos. Solo DELETE de
 * filas con WHERE por ids explícitos: nunca toca tablas, esquema, usuarios,
 * roles ni datos de los cursos actuales. La bitácora de auditoría es inmutable
 * (trigger): no se borra y su FK la deja con curso_id NULL.
 *
 * Siempre en una transacción. Sin --apply simula y hace rollback. Antes de
 * borrar respalda en storage/app/backups-demo/<fecha-hora>/ cada fila que se
 * borra (una tabla por archivo, manifiesto con conteos y SHA-256, volcado
 * pg_dump si está disponible y copia de los archivos físicos referenciados).
 * --restore=<carpeta> reinserta las filas con sus ids originales, padres antes
 * que hijos, y re-sincroniza las secuencias.
 */
class LimpiarCursosAntiguos extends Command
{
    protected $signature = 'app:limpiar-cursos-antiguos
        {--apply : Borra de verdad (sin esta opción solo simula)}
        {--dry-run : Solo simula dentro de una transacción con rollback (predeterminado)}
        {--restore= : Carpeta de respaldo a restaurar (ruta completa o nombre dentro de storage/app/backups-demo)}
        {--antes-de= : Fecha (YYYY-MM-DD) que se usa como "hoy" para decidir qué curso terminó}
        {--excluir-curso=* : Ids de curso que no se borran (admite "1,2" o la opción repetida)}
        {--profesor=40 : Profesor cuyo panel se muestra en el estado resultante}';

    protected $description = 'Borra los cursos antiguos y sus datos (simula por defecto; respalda y se puede restaurar)';

    /** Tablas que nunca deben cambiar de filas (usuarios, roles, configuración, migraciones). */
    protected const TABLAS_INTOCABLES = ['users', 'roles', 'permissions', 'model_has_roles', 'model_has_permissions', 'role_has_permissions', 'migrations', 'configuraciones_plataforma', 'bitacoras_auditoria', 'notifications'];

    public function handle(ConfiguracionPlataforma $configuracion): int
    {
        if (app()->environment('production')) {
            $this->error('Este comando no se ejecuta con APP_ENV=production.');

            return self::FAILURE;
        }

        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('Usa --apply o --dry-run, no ambos.');

            return self::FAILURE;
        }

        $configuracion->aplicarZonaHoraria();
        $aplicar = (bool) $this->option('apply');

        if ($this->option('restore')) {
            return $this->restaurar((string) $this->option('restore'), $aplicar);
        }

        $hoy = $this->option('antes-de') ? Carbon::parse((string) $this->option('antes-de'))->startOfDay() : now()->startOfDay();
        [$antiguos, $actuales] = $this->clasificar($hoy);

        if ($antiguos === []) {
            $this->info('No hay cursos antiguos que borrar.');

            return self::SUCCESS;
        }

        $plan = $this->plan($antiguos);
        $carpeta = $this->respaldar($plan, $antiguos, $hoy, $aplicar);

        DB::beginTransaction();

        try {
            $antes = $this->foto($actuales);
            $borradas = $this->borrar($plan);
            $errores = $this->verificar($plan, $borradas, $antes, $actuales);
            $this->table(['Tabla', 'Filas borradas'], collect($borradas)->map(fn (int $n, string $tabla) => [$tabla, $n])->values()->all());

            if ($errores !== []) {
                DB::rollBack();
                $this->error('Verificación fallida; no se borró nada:');
                foreach ($errores as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            $this->info('Verificación: sin huérfanos ni violaciones de FK, ninguna tabla borrada ni alterada, usuarios/roles intactos y los cursos actuales con las mismas filas dependientes.');
            $this->mostrarEstado();

            if (! $aplicar) {
                $this->probarRestauracion($carpeta, $antes, $actuales);
                DB::rollBack();
                $this->info("SIMULACIÓN: se hizo rollback, la base no cambió. Respaldo de prueba en {$carpeta}");

                return self::SUCCESS;
            }

            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info("APLICADO. Para revertir: php artisan app:limpiar-cursos-antiguos --restore=\"{$carpeta}\" --apply");

        return self::SUCCESS;
    }

    /**
     * ANTIGUO: archivado o con fecha de fin anterior a $hoy. Se protegen los excluidos
     * y los que algún curso actual referencia (módulos, grupos, tareas o quizzes).
     *
     * @return array{0: list<int>, 1: list<int>}
     */
    protected function clasificar(Carbon $hoy): array
    {
        $excluidos = collect((array) $this->option('excluir-curso'))->flatMap(fn ($valor) => explode(',', (string) $valor))->map(fn ($id) => (int) trim($id))->filter()->all();
        $nombres = DB::table('users')->pluck('name', 'id');
        $filas = [];
        $antiguos = [];
        $actuales = [];

        foreach (DB::table('cursos')->orderBy('id')->get() as $curso) {
            $fin = $curso->fecha_fin ? Carbon::parse($curso->fecha_fin)->startOfDay() : null;
            $esAntiguo = $curso->estado === 'archivado' || ($fin !== null && $fin->lt($hoy));
            $motivo = match (true) {
                ! $esAntiguo => $fin === null ? 'ACTUAL (fecha de fin nula)' : 'ACTUAL',
                in_array($curso->id, $excluidos, true) => 'PROTEGIDO (--excluir-curso)',
                ($referencias = $this->referenciasDesdeOtrosCursos((int) $curso->id)) > 0 => "PROTEGIDO ({$referencias} referencias desde cursos actuales)",
                default => 'ANTIGUO (se borra)',
            };

            str_starts_with($motivo, 'ANTIGUO') ? $antiguos[] = (int) $curso->id : $actuales[] = (int) $curso->id;
            $docentes = DB::table('curso_profesor')->where('curso_id', $curso->id)->pluck('profesor_id')->map(fn ($id) => $nombres[$id] ?? $id)->join(', ');
            $filas[] = ["#{$curso->id}", $curso->nombre, $curso->estado, "{$curso->fecha_inicio} – {$curso->fecha_fin}", $nombres[$curso->profesor_id] ?? $curso->profesor_id, $docentes, $motivo];
        }

        $this->line('Hoy para la clasificación: '.$hoy->toDateString());
        $this->table(['Id', 'Curso', 'Estado', 'Fechas', 'Titular', 'Co-docentes', 'Clasificación'], $filas);

        return [$antiguos, $actuales];
    }

    /** Filas de OTROS cursos que apuntan a módulos, grupos, tareas o quizzes de este. */
    protected function referenciasDesdeOtrosCursos(int $cursoId): int
    {
        $de = fn (string $tabla) => DB::table($tabla)->where('curso_id', $cursoId)->select('id');

        return DB::table('tareas')->where('curso_id', '!=', $cursoId)->where(fn ($q) => $q->whereIn('modulo_id', $de('modulos'))->orWhereIn('grupo_asignado_id', $de('grupos')))->count()
            + DB::table('quizzes')->where('curso_id', '!=', $cursoId)->whereIn('modulo_id', $de('modulos'))->count()
            + DB::table('entregas')->whereIn('grupo_id', $de('grupos'))->whereNotIn('tarea_id', $de('tareas'))->count()
            + DB::table('rubro_tarea')->whereIn('tarea_id', $de('tareas'))->whereNotIn('rubro_id', $this->rubrosDe([$cursoId]))->count()
            + DB::table('rubro_quiz')->whereIn('quiz_id', $de('quizzes'))->whereNotIn('rubro_id', $this->rubrosDe([$cursoId]))->count();
    }

    /** @param  list<int>  $cursos */
    protected function rubrosDe(array $cursos): Builder
    {
        return DB::table('rubros_evaluacion')->whereIn('esquema_id', DB::table('esquemas_evaluacion')->whereIn('curso_id', $cursos)->select('id'))->select('id');
    }

    /**
     * Orden seguro de borrado (hijos antes que padres) con el filtro de cada tabla.
     * Los ids intermedios se fijan antes de borrar, para no depender del orden.
     *
     * @param  list<int>  $cursos
     * @return array<string, callable(): Builder>
     */
    protected function plan(array $cursos): array
    {
        $ids = fn (string $tabla, string $columna, array|Collection $valores) => DB::table($tabla)->whereIn($columna, $valores)->pluck('id')->all();
        $modulos = $ids('modulos', 'curso_id', $cursos);
        $tareas = $ids('tareas', 'curso_id', $cursos);
        $quizzes = $ids('quizzes', 'curso_id', $cursos);
        $grupos = $ids('grupos', 'curso_id', $cursos);
        $sesiones = $ids('sesiones_clase', 'curso_id', $cursos);
        $esquemas = $ids('esquemas_evaluacion', 'curso_id', $cursos);
        $rubros = $ids('rubros_evaluacion', 'esquema_id', $esquemas);
        $debates = $ids('debates', 'curso_id', $cursos);
        $lecciones = $ids('lecciones', 'modulo_id', $modulos);
        $porCurso = fn (string $tabla) => fn () => DB::table($tabla)->whereIn('curso_id', $cursos);

        return [
            'lecciones_completadas' => fn () => DB::table('lecciones_completadas')->whereIn('leccion_id', $lecciones),
            'debate_comentarios' => fn () => DB::table('debate_comentarios')->whereIn('debate_id', $debates),
            'notas_rubro' => fn () => DB::table('notas_rubro')->whereIn('rubro_id', $rubros),
            'rubro_tarea' => fn () => DB::table('rubro_tarea')->where(fn ($q) => $q->whereIn('rubro_id', $rubros)->orWhereIn('tarea_id', $tareas)),
            'rubro_quiz' => fn () => DB::table('rubro_quiz')->where(fn ($q) => $q->whereIn('rubro_id', $rubros)->orWhereIn('quiz_id', $quizzes)),
            'resultados_quiz' => fn () => DB::table('resultados_quiz')->whereIn('quiz_id', $quizzes),
            'asistencias' => fn () => DB::table('asistencias')->whereIn('sesion_id', $sesiones),
            'entregas' => fn () => DB::table('entregas')->whereIn('tarea_id', $tareas),
            'grupo_estudiante' => fn () => DB::table('grupo_estudiante')->whereIn('grupo_id', $grupos),
            'tareas' => $porCurso('tareas'),
            'grupos' => $porCurso('grupos'),
            'quizzes' => $porCurso('quizzes'),
            'lecciones' => fn () => DB::table('lecciones')->whereIn('modulo_id', $modulos),
            'modulos' => $porCurso('modulos'),
            'rubros_evaluacion' => fn () => DB::table('rubros_evaluacion')->whereIn('esquema_id', $esquemas),
            'esquemas_evaluacion' => $porCurso('esquemas_evaluacion'),
            'sesiones_clase' => $porCurso('sesiones_clase'),
            'debates' => $porCurso('debates'),
            'inscripciones' => $porCurso('inscripciones'),
            'curso_profesor' => $porCurso('curso_profesor'),
            'banco_preguntas' => $porCurso('banco_preguntas'),
            'carpetas_curso' => $porCurso('carpetas_curso'),
            'comunicados' => $porCurso('comunicados'),
            // Con FK SET NULL: se borran para no dejar filas con la referencia vacía.
            'mensajes' => $porCurso('mensajes'),
            'conversaciones_bot' => $porCurso('conversaciones_bot'),
            // bitacoras_auditoria NO se borra: un trigger la hace inmutable (solo admite que la FK ponga curso_id en NULL).
            'cursos' => fn () => DB::table('cursos')->whereIn('id', $cursos),
        ];
    }

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @param  list<int>  $antiguos
     */
    protected function respaldar(array $plan, array $antiguos, Carbon $hoy, bool $aplicar): string
    {
        $carpeta = storage_path('app/backups-demo/'.now()->format('Y-m-d_His').'_limpieza'.($aplicar ? '' : '_simulacion'));
        File::ensureDirectoryExists("{$carpeta}/filas");
        $conteos = [];

        foreach ($plan as $tabla => $consulta) {
            $filas = $consulta()->get()->map(fn ($fila) => (array) $fila)->all();
            $conteos[$tabla] = count($filas);
            $this->escribirJson("{$carpeta}/filas/{$tabla}.json", $filas);
        }

        // Bitácora inmutable: se conserva y la FK la deja con curso_id NULL; se guarda el vínculo para reportarlo.
        $this->escribirJson("{$carpeta}/bitacora_desvinculada.json", DB::table('bitacoras_auditoria')->whereIn('curso_id', $antiguos)->orderBy('id')->get(['id', 'curso_id'])->map(fn ($fila) => (array) $fila)->all());
        $archivos = $this->copiarArchivos($carpeta);
        $this->escribirJson("{$carpeta}/manifest.json", [
            'modo' => $aplicar ? 'apply' : 'dry-run',
            'fecha' => now()->toIso8601String(),
            'hoy_usado' => $hoy->toDateString(),
            'cursos_borrados' => $antiguos,
            'orden_borrado' => array_keys($plan),
            'conteos' => $conteos,
            'archivos' => $archivos,
        ]);
        $this->volcarConPgDump($carpeta, array_keys($plan));
        $this->actualizarManifiesto($carpeta);
        $this->line("Respaldo: {$carpeta}");

        return $carpeta;
    }

    /**
     * Copia al respaldo los archivos del disco de subidas que referencian las filas respaldadas.
     *
     * @return array{copiados: list<string>, enlaces_externos: list<string>, no_encontrados: list<string>}
     */
    protected function copiarArchivos(string $carpeta): array
    {
        $columnas = ['cursos' => ['imagen_portada_url'], 'entregas' => ['archivo_url'], 'lecciones' => ['material_url', 'video_url'], 'mensajes' => ['archivo_url']];
        $rutas = collect($columnas)->flatMap(fn (array $campos, string $tabla) => collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"))
            ->flatMap(fn (array $fila) => array_map(fn ($campo) => $fila[$campo] ?? null, $campos)))
            ->merge(collect([...$this->leerJson("{$carpeta}/filas/modulos.json"), ...$this->leerJson("{$carpeta}/filas/carpetas_curso.json")])
                ->flatMap(fn (array $fila) => collect(json_decode((string) ($fila['materiales'] ?? $fila['recursos'] ?? ''), true) ?: [])->flatten()))
            ->filter(fn ($valor) => is_string($valor) && $valor !== '' && str_contains($valor, '/'))
            ->unique()->values();
        $disco = Storage::disk(config('archivos.disco'));
        $resultado = ['copiados' => [], 'enlaces_externos' => [], 'no_encontrados' => []];

        foreach ($rutas as $ruta) {
            if (preg_match('~^https?://~i', $ruta)) {
                $resultado['enlaces_externos'][] = $ruta;
            } elseif ($disco->exists($ruta)) {
                File::ensureDirectoryExists(dirname("{$carpeta}/archivos/{$ruta}"));
                File::put("{$carpeta}/archivos/{$ruta}", $disco->get($ruta));
                $resultado['copiados'][] = $ruta;
            } else {
                $resultado['no_encontrados'][] = $ruta;
            }
        }

        return $resultado;
    }

    /**
     * Volcado opcional (solo datos) de las tablas involucradas; si falla, solo avisa.
     *
     * @param  list<string>  $tablas
     */
    protected function volcarConPgDump(string $carpeta, array $tablas): void
    {
        $conexion = config('database.connections.'.config('database.default'));

        if (($conexion['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $resultado = Process::timeout(600)
            ->env(['PGPASSWORD' => (string) $conexion['password'], 'PGSSLMODE' => (string) ($conexion['sslmode'] ?? 'prefer')])
            ->run(['pg_dump', '--data-only', '--no-owner', ...array_map(fn ($tabla) => "--table=public.{$tabla}", $tablas),
                '--host='.$conexion['host'], '--port='.$conexion['port'], '--username='.$conexion['username'],
                '--dbname='.$conexion['database'], '--file='.$carpeta.'/pg_dump_tablas_involucradas.sql']);

        $resultado->successful()
            ? $this->line('pg_dump: volcado de datos de las tablas involucradas guardado.')
            : $this->warn('pg_dump no disponible o falló (el respaldo por filas sigue siendo válido).');
    }

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @return array<string, int>
     */
    protected function borrar(array $plan): array
    {
        $borradas = [];

        foreach ($plan as $tabla => $consulta) {
            $borradas[$tabla] = $consulta()->delete();
        }

        return $borradas;
    }

    /**
     * Foto para comparar antes y después: tablas existentes, filas de las tablas
     * intocables y filas dependientes de los cursos actuales.
     *
     * @param  list<int>  $actuales
     * @return array{tablas: list<string>, intocables: array<string, int>, actuales: array<string, int>}
     */
    protected function foto(array $actuales): array
    {
        return [
            'tablas' => collect(Schema::getTables())->map(fn (array $tabla) => ($tabla['schema'] ?? '').'.'.$tabla['name'])->sort()->values()->all(),
            'intocables' => collect(self::TABLAS_INTOCABLES)->filter(fn ($tabla) => Schema::hasTable($tabla))->mapWithKeys(fn ($tabla) => [$tabla => DB::table($tabla)->count()])->all(),
            'actuales' => collect($this->plan($actuales))->map(fn (callable $consulta) => $consulta()->count())->all(),
        ];
    }

    /**
     * @param  array<string, callable(): Builder>  $plan
     * @param  array<string, int>  $borradas
     * @param  array<string, mixed>  $antes
     * @param  list<int>  $actuales
     * @return list<string>
     */
    protected function verificar(array $plan, array $borradas, array $antes, array $actuales): array
    {
        $errores = [];
        $esperadas = collect($plan)->map(fn ($consulta, string $tabla) => count($this->leerJsonDeRespaldoPendiente($tabla)))->all();

        foreach ($plan as $tabla => $consulta) {
            ($quedan = $consulta()->count()) === 0 || $errores[] = "{$tabla}: quedan {$quedan} filas de los cursos antiguos";
            $borradas[$tabla] === $esperadas[$tabla] || $errores[] = "{$tabla}: se borraron {$borradas[$tabla]} filas y el respaldo tiene {$esperadas[$tabla]}";
        }

        $despues = $this->foto($actuales);
        $despues['tablas'] === $antes['tablas'] || $errores[] = 'cambió la lista de tablas';
        $despues['intocables'] === $antes['intocables'] || $errores[] = 'cambiaron filas de usuarios/roles/configuración: '.json_encode(array_diff_assoc($despues['intocables'], $antes['intocables']));
        $despues['actuales'] === $antes['actuales'] || $errores[] = 'cambiaron filas dependientes de cursos actuales: '.json_encode(array_diff_assoc($despues['actuales'], $antes['actuales']));

        foreach ($this->violacionesDeFk() as $violacion) {
            $errores[] = $violacion;
        }

        $desvinculadas = collect($this->leerJson($this->carpetaActual.'/bitacora_desvinculada.json'))->pluck('id');
        $conservadas = DB::table('bitacoras_auditoria')->whereIn('id', $desvinculadas)->whereNull('curso_id')->count();
        $conservadas === $desvinculadas->count() || $errores[] = "bitácora: {$conservadas} de {$desvinculadas->count()} filas conservadas con curso_id NULL";

        return $errores;
    }

    /** Filas que el respaldo en curso guardó para una tabla (se llena en respaldar()). */
    protected function leerJsonDeRespaldoPendiente(string $tabla): array
    {
        return $this->leerJson($this->carpetaActual."/filas/{$tabla}.json");
    }

    protected string $carpetaActual = '';

    /**
     * Huérfanos: filas cuya clave foránea apunta a una fila que no existe, en toda la base.
     *
     * @return list<string>
     */
    protected function violacionesDeFk(): array
    {
        if (DB::getDriverName() === 'sqlite') {
            return collect(DB::select('PRAGMA foreign_key_check'))->map(fn ($fila) => "huérfano en {$fila->table} (rowid {$fila->rowid}) hacia {$fila->parent}")->all();
        }

        $relaciones = DB::select("select tc.table_name as hija, kcu.column_name as columna, ccu.table_name as padre, ccu.column_name as columna_padre
            from information_schema.table_constraints tc
            join information_schema.key_column_usage kcu on tc.constraint_name = kcu.constraint_name and tc.table_schema = kcu.table_schema
            join information_schema.constraint_column_usage ccu on ccu.constraint_name = tc.constraint_name and ccu.table_schema = tc.table_schema
            where tc.constraint_type = 'FOREIGN KEY' and tc.table_schema = 'public'");

        return collect($relaciones)->map(function ($fk) {
            $huerfanas = DB::table("{$fk->hija} as h")->whereNotNull("h.{$fk->columna}")
                ->whereNotExists(fn ($q) => $q->from("{$fk->padre} as p")->whereColumn("p.{$fk->columna_padre}", "h.{$fk->columna}"))->count();

            return $huerfanas > 0 ? "{$huerfanas} huérfanos en {$fk->hija}.{$fk->columna} → {$fk->padre}" : null;
        })->filter()->values()->all();
    }

    /** Estado resultante: cursos por profesor y el panel del profesor de --profesor. */
    protected function mostrarEstado(): void
    {
        $this->table(['Profesor titular', 'Cursos que quedan'], DB::table('cursos as c')->join('users as u', 'u.id', '=', 'c.profesor_id')
            ->selectRaw('u.name, count(*) as total')->groupBy('u.name')->orderBy('u.name')->get()->map(fn ($fila) => [$fila->name, $fila->total])->all());

        $profesor = User::find((int) $this->option('profesor'));

        if (! $profesor) {
            return;
        }

        $servicio = app(CursosProfesor::class);
        $cursos = $servicio->cursos($profesor);
        $pendientes = Entrega::query()->whereHas('tarea.curso', fn ($q) => $q->dictadosPor($profesor->id)->where('estado', '!=', 'archivado'))
            ->where('estado', 'entregado')->with(['estudiante:id,name', 'tarea.curso', 'tarea.modulo', 'grupo'])->get();
        $tareas = PorCalificar::agruparPorTarea(PorCalificar::filasPendientes($pendientes));
        $seleccionada = PorCalificar::tareaSeleccionada($tareas, null);
        $activos = PanelProfesor::consultaCursosActivos($profesor)->count();

        $this->table(["Panel de {$profesor->name}", 'Valor'], [
            ['Cursos a cargo / "N cursos activos"', $activos],
            ['Archivados que quedan', $cursos->count() - $activos],
            ['Insignia del dock', app(PanelProfesor::class)->totalPorCalificar($profesor)],
            ['Hero de Cursos', (int) collect($servicio->resumen($cursos, $cursos))->sum('pendientes')],
            ['Por calificar seleccionada por defecto', $seleccionada ? '#'.$seleccionada['tarea']->id.' '.$seleccionada['tarea']->titulo.' ('.$seleccionada['tarea']->curso->nombre.')' : '—'],
        ]);
    }

    /**
     * Restaura una carpeta de respaldo en una transacción (simula salvo --apply).
     */
    protected function restaurar(string $carpeta, bool $aplicar): int
    {
        $carpeta = is_dir($carpeta) ? $carpeta : storage_path('app/backups-demo/'.$carpeta);
        $this->verificarSumas($carpeta);

        $cursos = collect($this->leerJson("{$carpeta}/filas/cursos.json"))->pluck('id');
        if (($existentes = DB::table('cursos')->whereIn('id', $cursos)->count()) > 0) {
            $this->error("{$existentes} de los {$cursos->count()} cursos del respaldo ya existen en la base: no hay nada que restaurar (¿es un respaldo de simulación?).");

            return self::FAILURE;
        }

        DB::beginTransaction();

        try {
            $insertadas = $this->reinsertar($carpeta);
            $errores = $this->violacionesDeFk();
            $this->table(['Tabla', 'Filas reinsertadas'], collect($insertadas)->map(fn (int $n, string $tabla) => [$tabla, $n])->values()->all());

            if ($errores !== []) {
                DB::rollBack();
                $this->error('La restauración dejaría huérfanos; no se escribió nada: '.implode('; ', $errores));

                return self::FAILURE;
            }

            $aplicar ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        if ($aplicar) {
            $this->restaurarArchivos($carpeta);
        }

        $desvinculadas = count($this->leerJson("{$carpeta}/bitacora_desvinculada.json"));
        if ($desvinculadas > 0) {
            $this->warn("{$desvinculadas} filas de la bitácora siguen con curso_id NULL: el trigger de inmutabilidad no permite volver a vincularlas (su texto se conserva).");
        }

        $this->info($aplicar ? 'RESTAURADO.' : 'SIMULACIÓN de restauración: rollback, la base no cambió. Usa --apply para restaurar.');

        return self::SUCCESS;
    }

    protected function verificarSumas(string $carpeta): void
    {
        $this->exigir(is_file("{$carpeta}/manifest.json"), "no hay manifest.json en {$carpeta}");
        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);

        foreach ($manifiesto['sha256'] ?? [] as $archivo => $suma) {
            $this->exigir(hash_file('sha256', "{$carpeta}/{$archivo}") === $suma, "el archivo {$archivo} no coincide con su SHA-256");
        }
    }

    /**
     * Reinserta las filas padres antes que hijos (orden inverso al borrado), con sus ids, y re-sincroniza secuencias.
     *
     * @return array<string, int>
     */
    protected function reinsertar(string $carpeta): array
    {
        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);
        $insertadas = [];

        foreach (array_reverse($manifiesto['orden_borrado']) as $tabla) {
            $filas = collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"));
            $filas = $tabla === 'debate_comentarios' ? $filas->sortBy('id')->values() : $filas;
            $this->exigir($filas->count() === (int) $manifiesto['conteos'][$tabla], "{$tabla}: el archivo no coincide con el manifiesto");

            foreach ($filas->chunk(200) as $tanda) {
                DB::table($tabla)->insert($tanda->values()->all());
            }

            $insertadas[$tabla] = $filas->count();

            if (DB::getDriverName() === 'pgsql' && $filas->isNotEmpty() && array_key_exists('id', $filas->first())) {
                DB::statement("select setval(pg_get_serial_sequence('{$tabla}', 'id'), greatest((select max(id) from {$tabla}), 1))");
            }
        }

        return $insertadas;
    }

    protected function restaurarArchivos(string $carpeta): void
    {
        $origen = "{$carpeta}/archivos";

        if (! is_dir($origen)) {
            return;
        }

        $disco = Storage::disk(config('archivos.disco'));
        foreach (File::allFiles($origen) as $archivo) {
            $ruta = str_replace('\\', '/', $archivo->getRelativePathname());
            $disco->exists($ruta) || $disco->put($ruta, File::get($archivo->getPathname()));
        }
    }

    /**
     * Dentro de la simulación: reinserta lo borrado desde el respaldo y comprueba que
     * cada fila vuelve idéntica y que la foto de cursos actuales no cambió.
     *
     * @param  array<string, mixed>  $antes
     * @param  list<int>  $actuales
     */
    protected function probarRestauracion(string $carpeta, array $antes, array $actuales): void
    {
        $insertadas = $this->reinsertar($carpeta);
        $distintas = [];

        foreach (array_keys($insertadas) as $tabla) {
            $respaldo = collect($this->leerJson("{$carpeta}/filas/{$tabla}.json"));

            if ($respaldo->isEmpty()) {
                continue;
            }

            $clave = array_key_exists('id', $respaldo->first()) ? ['id'] : array_keys($respaldo->first());
            $faltan = $respaldo->reject(fn (array $fila) => DB::table($tabla)->where(collect($fila)->only($clave)->all())->get()
                ->contains(fn ($actual) => (array) $actual == $fila))->count();
            $faltan === 0 || $distintas[] = "{$tabla}: {$faltan} filas no volvieron idénticas";
        }

        $despues = $this->foto($actuales);
        $despues['intocables'] === $antes['intocables'] || $distintas[] = 'cambiaron usuarios/roles';
        $this->exigir($distintas === [] && $this->violacionesDeFk() === [], 'la prueba de restauración falló: '.implode('; ', $distintas));
        $this->info('Prueba de --restore (dentro de la simulación): OK — '.array_sum($insertadas).' filas reinsertadas e idénticas al respaldo, sin huérfanos.');
    }

    protected function actualizarManifiesto(string $carpeta): void
    {
        $ruta = "{$carpeta}/manifest.json";
        $manifiesto = json_decode(File::get($ruta), true);
        $manifiesto['sha256'] = collect(File::allFiles($carpeta))
            ->reject(fn ($archivo) => $archivo->getFilename() === 'manifest.json' && $archivo->getRelativePath() === '')
            ->mapWithKeys(fn ($archivo) => [str_replace('\\', '/', $archivo->getRelativePathname()) => hash_file('sha256', $archivo->getPathname())])
            ->sortKeys()->all();
        $this->escribirJson($ruta, $manifiesto);
        $this->carpetaActual = $carpeta;
    }

    protected function exigir(bool $condicion, string $mensaje): void
    {
        if (! $condicion) {
            throw new RuntimeException($mensaje);
        }
    }

    protected function escribirJson(string $ruta, mixed $datos): void
    {
        File::put($ruta, json_encode($datos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return list<array<string, mixed>> */
    protected function leerJson(string $ruta): array
    {
        return is_file($ruta) ? json_decode(File::get($ruta), true) : [];
    }
}
