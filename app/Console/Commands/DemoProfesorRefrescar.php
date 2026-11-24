<?php

namespace App\Console\Commands;

use App\Livewire\Profesor\PorCalificar\Index as PorCalificar;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Throwable;

/**
 * Refresca los datos de demostración de UN profesor para que las entregas
 * por calificar tengan esperas realistas respecto de hoy.
 *
 * Plan (el menos destructivo, 0 borrados): solo en los cursos no archivados ni terminados
 * donde el profesor es el único docente (más los de --incluir-curso):
 * - entregas pendientes: nuevos created_at/updated_at (mismo valor para todo
 *   un grupo, nunca antes de la apertura de la tarea ni en el futuro);
 * - la tarea de la semana actual sin entregas: vence hoy, mañana o pasado
 *   mañana (sin salir de su semana);
 * - si ninguna tarea individual tiene 30+ pendientes: una entrega por
 *   estudiante matriculado en la tarea de la semana actual del curso más grande.
 *
 * Siempre en una transacción. Sin --apply simula y hace rollback. Antes de
 * escribir respalda en storage/app/backups-demo/<fecha-hora>/ las filas que
 * cambia (más manifiesto con conteos y SHA-256); --restore=<carpeta> las
 * devuelve y borra solo las filas listadas en creadas.json.
 */
class DemoProfesorRefrescar extends Command
{
    protected $signature = 'app:demo-profesor-refrescar
        {--profesor=40 : Id del profesor de demostración}
        {--apply : Escribe los cambios (sin esta opción solo simula)}
        {--dry-run : Solo simula dentro de una transacción con rollback (predeterminado)}
        {--restore= : Carpeta de respaldo a restaurar (ruta completa o nombre dentro de storage/app/backups-demo)}
        {--incluir-curso=* : Curso compartido con otro docente que también se refresca}
        {--semilla=7 : Semilla para que la simulación y la aplicación del mismo día den el mismo plan}';

    protected $description = 'Refresca las fechas de entrega de los datos de demostración de un profesor (simula por defecto)';

    /** Días de espera para pendientes de tareas que vencieron hace 2 semanas o más. */
    protected const ESPERA_ANTIGUAS = [5, 6, 6, 7, 7, 8, 9, 10];

    /** Días de espera para pendientes de tareas más recientes. */
    protected const ESPERA_RECIENTES = [0, 1, 1, 2, 2, 3, 3, 4, 5, 6];

    /** Días de espera de las entregas nuevas (tarea de la semana actual). */
    protected const ESPERA_NUEVAS = [0, 0, 1, 1, 2, 3, 4];

    /** Pendientes individuales a partir de los cuales ya hay una tarea "cargada". */
    protected const PENDIENTES_TAREA_CARGADA = 30;

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

        $profesor = User::findOrFail((int) $this->option('profesor'));
        [$cursos, $excluidos] = $this->cursosEnAlcance($profesor);
        $plan = $this->planificar($cursos);

        $this->mostrarAlcance($profesor, $cursos, $excluidos);
        $carpeta = $this->respaldar($profesor, $cursos, $plan, $aplicar);
        $this->mostrarPlan($plan);

        DB::beginTransaction();

        try {
            $antes = $this->totales($profesor);
            $creadas = $this->aplicarPlan($plan);
            $errores = $this->verificar($profesor, $cursos, $plan, $creadas, $antes);
            $despues = $this->totales($profesor);
            $this->mostrarTotales($antes, $despues);

            if ($errores !== []) {
                DB::rollBack();
                $this->error('Verificación fallida; no se escribió nada:');
                foreach ($errores as $error) {
                    $this->line("  - {$error}");
                }

                return self::FAILURE;
            }

            if (! $aplicar) {
                $this->probarRestauracion($carpeta, $creadas);
                DB::rollBack();
                $this->info("SIMULACIÓN: se hizo rollback, la base no cambió. Respaldo de prueba en {$carpeta}");

                return self::SUCCESS;
            }

            $this->escribirJson("{$carpeta}/creadas.json", $creadas);
            $this->actualizarManifiesto($carpeta);
            DB::commit();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info("APLICADO. Para revertir: php artisan app:demo-profesor-refrescar --restore=\"{$carpeta}\" --apply");

        return self::SUCCESS;
    }

    /**
     * Cursos no archivados donde el profesor es el único docente, más los pedidos con --incluir-curso.
     *
     * @return array{0: Collection<int, Curso>, 1: array<int, string>}
     */
    protected function cursosEnAlcance(User $profesor): array
    {
        $incluidos = array_map('intval', (array) $this->option('incluir-curso'));
        $todos = Curso::dictadosPor($profesor)->withCount('profesoresAdicionales')->orderBy('id')->get();
        $excluidos = [];

        $enAlcance = $todos->filter(function (Curso $curso) use ($profesor, $incluidos, &$excluidos) {
            $motivo = match (true) {
                $curso->estaFinalizado() => 'archivado (no cuenta en el panel y no admite cambios)',
                $curso->fecha_inicio !== null && now()->gt($curso->rangoSemana($curso->totalSemanas())[1]) => 'ya terminó sus semanas (entregas antiguas coherentes)',
                in_array($curso->id, $incluidos, true) => null,
                $curso->profesor_id !== $profesor->id => 'el titular es otro profesor',
                $curso->profesores_adicionales_count > 0 => 'tiene co-docentes (datos compartidos)',
                default => null,
            };

            if ($motivo !== null) {
                $excluidos[$curso->id] = "#{$curso->id} {$curso->nombre}: {$motivo}";
            }

            return $motivo === null;
        })->values();

        return [$enAlcance, $excluidos];
    }

    /**
     * @param  Collection<int, Curso>  $cursos
     * @return array{entregas: array<int, array{tarea_id: int, antes: string, despues: string, dias: int}>, tareas: array<int, array{curso_id: int, antes: string, despues: string}>, nuevas: list<array<string, mixed>>}
     */
    protected function planificar(Collection $cursos): array
    {
        mt_srand((int) $this->option('semilla'));
        $ahora = now();
        $hoy = $ahora->copy()->startOfDay();
        $tareas = Tarea::query()->whereIn('curso_id', $cursos->modelKeys())->with('modulo')->orderBy('curso_id')->orderBy('id')->get();
        $pendientes = Entrega::query()->whereIn('tarea_id', $tareas->modelKeys())->where('estado', 'entregado')->orderBy('id')->get()->groupBy('tarea_id');
        $conEntregas = Entrega::query()->whereIn('tarea_id', $tareas->modelKeys())->distinct()->pluck('tarea_id')->all();
        $plan = ['entregas' => [], 'tareas' => [], 'nuevas' => []];

        foreach ($tareas as $tarea) {
            $grupo = $pendientes->get($tarea->id, collect());
            $dias = $tarea->fecha_entrega && $tarea->fecha_entrega->lt($ahora->copy()->subDays(14)) ? self::ESPERA_ANTIGUAS : self::ESPERA_RECIENTES;

            foreach ($grupo->groupBy(fn (Entrega $entrega) => $entrega->grupo_id ? "g{$entrega->grupo_id}" : "e{$entrega->id}") as $unidad) {
                $momento = $this->momento($dias, $tarea, $hoy, $ahora);

                foreach ($unidad as $entrega) {
                    $plan['entregas'][$entrega->id] = [
                        'tarea_id' => $tarea->id,
                        'antes' => (string) $entrega->updated_at,
                        'despues' => $momento->format('Y-m-d H:i:s'),
                        'dias' => (int) $momento->copy()->startOfDay()->diffInDays($hoy),
                    ];
                }
            }
        }

        // Tareas de la semana actual sin entregas que vencen hoy: hoy, mañana o pasado mañana, sin salir de su semana.
        $actuales = $tareas->filter(function (Tarea $tarea) use ($cursos, $conEntregas, $hoy) {
            $curso = $cursos->firstWhere('id', $tarea->curso_id);
            $semana = CursosProfesor::semanaActual($curso, $curso->totalSemanas());

            return $semana !== null && ! in_array($tarea->id, $conEntregas, true) && $tarea->practica === null
                && (int) $tarea->modulo?->semana === $semana && $tarea->fecha_entrega?->isSameDay($hoy);
        })->values();

        $receptora = $this->tareaParaNuevas($cursos, $tareas, $actuales, $pendientes);

        foreach ($actuales->reject(fn (Tarea $tarea) => $tarea->id === $receptora?->id)->values() as $indice => $tarea) {
            $curso = $cursos->firstWhere('id', $tarea->curso_id);
            $finSemana = $curso->rangoSemana(CursosProfesor::semanaActual($curso, $curso->totalSemanas()))[1];
            $nueva = $tarea->fecha_entrega->copy()->addDays([1, 2, 0][$indice % 3]);

            if ($nueva->gt($finSemana) || $nueva->eq($tarea->fecha_entrega)) {
                continue;
            }

            $plan['tareas'][$tarea->id] = ['curso_id' => $tarea->curso_id, 'antes' => (string) $tarea->fecha_entrega, 'despues' => $nueva->format('Y-m-d H:i:s')];
        }

        if ($receptora) {
            $curso = $cursos->firstWhere('id', $receptora->curso_id);
            foreach ($curso->inscripcionesActivas()->orderBy('estudiante_id')->limit(40)->pluck('estudiante_id') as $estudianteId) {
                $momento = $this->momento(self::ESPERA_NUEVAS, $receptora, $hoy, $ahora)->format('Y-m-d H:i:s');
                $plan['nuevas'][] = [
                    'tarea_id' => $receptora->id, 'estudiante_id' => (int) $estudianteId, 'estado' => 'entregado',
                    'intentos_usados' => 1, 'created_at' => $momento, 'updated_at' => $momento,
                ];
            }
        }

        return $plan;
    }

    /**
     * Tarea individual de la semana actual (sin entregas) del curso con más matriculados,
     * solo si ninguna tarea individual del alcance ya tiene PENDIENTES_TAREA_CARGADA pendientes.
     *
     * @param  Collection<int, Curso>  $cursos
     * @param  Collection<int, Tarea>  $tareas
     * @param  Collection<int, Tarea>  $actuales
     * @param  Collection<int, Collection<int, Entrega>>  $pendientes
     */
    protected function tareaParaNuevas(Collection $cursos, Collection $tareas, Collection $actuales, Collection $pendientes): ?Tarea
    {
        $yaHay = $tareas->contains(fn (Tarea $tarea) => ! $tarea->esGrupal() && $pendientes->get($tarea->id, collect())->count() >= self::PENDIENTES_TAREA_CARGADA);

        if ($yaHay) {
            return null;
        }

        $matriculados = DB::table('inscripciones')->whereIn('curso_id', $cursos->modelKeys())->where('estado', 'activo')->where('activo_local', true)
            ->selectRaw('curso_id, count(*) as total')->groupBy('curso_id')->pluck('total', 'curso_id');

        return $actuales->reject(fn (Tarea $tarea) => $tarea->esGrupal())
            ->sortByDesc(fn (Tarea $tarea) => (int) ($matriculados[$tarea->curso_id] ?? 0))
            ->first(fn (Tarea $tarea) => (int) ($matriculados[$tarea->curso_id] ?? 0) >= 25);
    }

    /**
     * Fecha y hora de entrega para una espera tomada de $dias: entre las 07:00 y las 23:00,
     * nunca en el futuro ni antes de que se abra la tarea.
     *
     * @param  list<int>  $dias
     */
    protected function momento(array $dias, Tarea $tarea, Carbon $hoy, Carbon $ahora): Carbon
    {
        $momento = $hoy->copy()->subDays($dias[mt_rand(0, count($dias) - 1)])->addMinutes(mt_rand(7 * 60, 23 * 60));

        if ($momento->gt($ahora)) {
            $momento = $ahora->copy()->subMinutes(mt_rand(20, 180));
        }

        $apertura = $tarea->fecha_inicio ?? $tarea->created_at;

        if ($apertura && $momento->lt($apertura)) {
            $momento = $apertura->copy()->addHour()->min($ahora);
        }

        return $momento->startOfMinute();
    }

    /**
     * Escribe las filas actuales que el plan cambiará y un manifiesto con conteos y SHA-256.
     * Si pg_dump está disponible, guarda además un volcado de datos de las tablas afectadas.
     *
     * @param  Collection<int, Curso>  $cursos
     * @param  array<string, mixed>  $plan
     */
    protected function respaldar(User $profesor, Collection $cursos, array $plan, bool $aplicar): string
    {
        $carpeta = storage_path('app/backups-demo/'.now()->format('Y-m-d_His').($aplicar ? '' : '_simulacion'));
        File::ensureDirectoryExists($carpeta);

        $this->escribirJson("{$carpeta}/entregas.json", DB::table('entregas')->whereIn('id', array_keys($plan['entregas']))->orderBy('id')->get()->all());
        $this->escribirJson("{$carpeta}/tareas.json", DB::table('tareas')->whereIn('id', array_keys($plan['tareas']))->orderBy('id')->get()->all());
        $this->escribirJson("{$carpeta}/plan.json", $plan);
        $this->escribirJson("{$carpeta}/creadas.json", []);
        $this->escribirJson("{$carpeta}/manifest.json", [
            'modo' => $aplicar ? 'apply' : 'dry-run',
            'fecha' => now()->toIso8601String(),
            'profesor_id' => $profesor->id,
            'cursos' => $cursos->modelKeys(),
            'semilla' => (int) $this->option('semilla'),
            'conteos' => ['entregas' => count($plan['entregas']), 'tareas' => count($plan['tareas']), 'nuevas' => count($plan['nuevas'])],
        ]);
        $this->volcarConPgDump($carpeta);
        $this->actualizarManifiesto($carpeta);
        $this->line("Respaldo: {$carpeta}");

        return $carpeta;
    }

    /** Volcado opcional (solo datos) de las tablas que el plan toca; si falla, solo avisa. */
    protected function volcarConPgDump(string $carpeta): void
    {
        $conexion = config('database.connections.'.config('database.default'));

        if (($conexion['driver'] ?? null) !== 'pgsql') {
            return;
        }

        $resultado = Process::timeout(300)
            ->env(['PGPASSWORD' => (string) $conexion['password'], 'PGSSLMODE' => (string) ($conexion['sslmode'] ?? 'prefer')])
            ->run(['pg_dump', '--data-only', '--no-owner', '--table=public.entregas', '--table=public.tareas',
                '--host='.$conexion['host'], '--port='.$conexion['port'], '--username='.$conexion['username'],
                '--dbname='.$conexion['database'], '--file='.$carpeta.'/pg_dump_entregas_tareas.sql']);

        $resultado->successful()
            ? $this->line('pg_dump: volcado de entregas y tareas guardado.')
            : $this->warn('pg_dump no disponible o falló (el respaldo por filas sigue siendo válido).');
    }

    /** Recalcula los SHA-256 de todos los archivos de la carpeta en el manifiesto. */
    protected function actualizarManifiesto(string $carpeta): void
    {
        $ruta = "{$carpeta}/manifest.json";
        $manifiesto = json_decode(File::get($ruta), true);
        $manifiesto['sha256'] = collect(File::files($carpeta))
            ->reject(fn ($archivo) => $archivo->getFilename() === 'manifest.json')
            ->mapWithKeys(fn ($archivo) => [$archivo->getFilename() => hash_file('sha256', $archivo->getPathname())])
            ->sortKeys()->all();
        $this->escribirJson($ruta, $manifiesto);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return list<array{tabla: string, id: int, tarea_id: int, estudiante_id: int}>
     */
    protected function aplicarPlan(array $plan): array
    {
        foreach ($plan['entregas'] as $id => $cambio) {
            $filas = DB::table('entregas')->where('id', $id)->where('tarea_id', $cambio['tarea_id'])->where('estado', 'entregado')
                ->update(['created_at' => $cambio['despues'], 'updated_at' => $cambio['despues']]);
            $this->exigir($filas === 1, "entrega {$id}: se esperaba 1 fila actualizada y fueron {$filas}");
        }

        foreach ($plan['tareas'] as $id => $cambio) {
            $filas = DB::table('tareas')->where('id', $id)->where('curso_id', $cambio['curso_id'])->update(['fecha_entrega' => $cambio['despues']]);
            $this->exigir($filas === 1, "tarea {$id}: se esperaba 1 fila actualizada y fueron {$filas}");
        }

        $creadas = [];
        foreach ($plan['nuevas'] as $fila) {
            $this->exigir(DB::table('entregas')->where('tarea_id', $fila['tarea_id'])->where('estudiante_id', $fila['estudiante_id'])->doesntExist(),
                "el estudiante {$fila['estudiante_id']} ya tiene entrega en la tarea {$fila['tarea_id']}");
            $creadas[] = ['tabla' => 'entregas', 'id' => (int) DB::table('entregas')->insertGetId($fila), 'tarea_id' => $fila['tarea_id'], 'estudiante_id' => $fila['estudiante_id']];
        }

        return $creadas;
    }

    /**
     * Integridad y cifras dentro de la transacción, antes del commit.
     *
     * @param  Collection<int, Curso>  $cursos
     * @param  array<string, mixed>  $plan
     * @param  list<array<string, mixed>>  $creadas
     * @param  array<string, mixed>  $antes
     * @return list<string>
     */
    protected function verificar(User $profesor, Collection $cursos, array $plan, array $creadas, array $antes): array
    {
        $errores = [];
        $tareaIds = DB::table('tareas')->whereIn('curso_id', $cursos->modelKeys())->pluck('id');

        $huerfanas = DB::table('entregas as e')
            ->leftJoin('tareas as t', 't.id', '=', 'e.tarea_id')
            ->leftJoin('users as u', 'u.id', '=', 'e.estudiante_id')
            ->leftJoin('grupos as g', 'g.id', '=', 'e.grupo_id')
            ->whereIn('e.tarea_id', $tareaIds)
            ->where(fn ($q) => $q->whereNull('t.id')->orWhereNull('u.id')->orWhere(fn ($q) => $q->whereNotNull('e.grupo_id')->whereNull('g.id')))
            ->count();
        $huerfanas === 0 || $errores[] = "{$huerfanas} entregas con tarea, estudiante o grupo inexistente";

        $fueraDeRango = DB::table('entregas as e')->join('tareas as t', 't.id', '=', 'e.tarea_id')
            ->whereIn('e.id', [...array_keys($plan['entregas']), ...array_column($creadas, 'id')])
            ->where(fn ($q) => $q->where('e.updated_at', '>', now())->orWhereColumn('e.updated_at', '<', DB::raw('coalesce(t.fecha_inicio, t.created_at)')))
            ->count();
        $fueraDeRango === 0 || $errores[] = "{$fueraDeRango} entregas en el futuro o antes de la apertura de su tarea";

        $gruposDispares = DB::table('entregas')->whereIn('tarea_id', $tareaIds)->where('estado', 'entregado')->whereNotNull('grupo_id')
            ->groupBy('tarea_id', 'grupo_id')->havingRaw('count(distinct updated_at) > 1')->select('tarea_id')->get()->count();
        $gruposDispares === 0 || $errores[] = "{$gruposDispares} grupos con fechas distintas entre sus integrantes";

        $sinMatricula = collect($creadas)->reject(fn (array $fila) => DB::table('inscripciones as i')->join('tareas as t', 't.curso_id', '=', 'i.curso_id')
            ->where('t.id', $fila['tarea_id'])->where('i.estudiante_id', $fila['estudiante_id'])->where('i.estado', 'activo')->exists())->count();
        $sinMatricula === 0 || $errores[] = "{$sinMatricula} entregas nuevas de estudiantes sin matrícula activa";

        $actualizadas = DB::table('entregas')->whereIn('id', array_keys($plan['entregas']))->get(['id', 'updated_at'])
            ->filter(fn ($fila) => substr((string) $fila->updated_at, 0, 19) === $plan['entregas'][$fila->id]['despues'])->count();
        $actualizadas === count($plan['entregas']) || $errores[] = "entregas actualizadas {$actualizadas} de ".count($plan['entregas']);
        count($creadas) === count($plan['nuevas']) || $errores[] = 'entregas creadas '.count($creadas).' de '.count($plan['nuevas']);

        $despues = $this->totales($profesor);
        $despues['calificadas'] === $antes['calificadas'] || $errores[] = "cambió el número de entregas calificadas ({$antes['calificadas']} → {$despues['calificadas']})";
        ($despues['dock'] === $despues['hero'] && $despues['hero'] === $despues['por_calificar'])
            || $errores[] = "cifras distintas: dock {$despues['dock']}, hero {$despues['hero']}, Por calificar {$despues['por_calificar']}";

        return $errores;
    }

    /** @return array{dock: int, hero: int, por_calificar: int, calificadas: int, esperas: array<string, int>} */
    protected function totales(User $profesor): array
    {
        $servicio = app(CursosProfesor::class);
        $cursos = $servicio->cursos($profesor);
        $pendientes = Entrega::query()
            ->whereHas('tarea.curso', fn ($q) => $q->dictadosPor($profesor->id)->where('estado', '!=', 'archivado'))
            ->where('estado', 'entregado')
            ->with(['estudiante:id,name', 'tarea.curso', 'grupo'])
            ->get();
        $filas = PorCalificar::filasPendientes($pendientes);

        return [
            'dock' => app(PanelProfesor::class)->totalPorCalificar($profesor),
            'hero' => (int) collect($servicio->resumen($cursos, $cursos))->sum('pendientes'),
            'por_calificar' => $filas->count(),
            'calificadas' => Entrega::query()->whereHas('tarea.curso', fn ($q) => $q->dictadosPor($profesor->id))->where('estado', 'calificado')->count(),
            'esperas' => [
                '0-6 días' => $filas->where('dias', '<=', 6)->count(),
                '7-10 días' => $filas->whereBetween('dias', [7, 10])->count(),
                '11 días' => $filas->where('dias', 11)->count(),
                '12+ días' => $filas->where('dias', '>=', 12)->count(),
            ],
        ];
    }

    /**
     * Restaura una carpeta de respaldo en una transacción (simula salvo --apply).
     */
    protected function restaurar(string $carpeta, bool $aplicar): int
    {
        $carpeta = is_dir($carpeta) ? $carpeta : storage_path('app/backups-demo/'.$carpeta);
        $this->exigir(is_file("{$carpeta}/manifest.json"), "no hay manifest.json en {$carpeta}");
        $manifiesto = json_decode(File::get("{$carpeta}/manifest.json"), true);

        foreach ($manifiesto['sha256'] ?? [] as $archivo => $suma) {
            $this->exigir(hash_file('sha256', "{$carpeta}/{$archivo}") === $suma, "el archivo {$archivo} no coincide con su SHA-256");
        }

        DB::beginTransaction();

        try {
            $conteos = $this->revertir($carpeta, $this->leerJson("{$carpeta}/creadas.json"));
            $this->table(['Tabla', 'Acción', 'Filas'], [
                ['entregas', 'restaurar fechas', $conteos['entregas']],
                ['tareas', 'restaurar fecha_entrega', $conteos['tareas']],
                ['entregas', 'borrar creadas (creadas.json)', $conteos['borradas']],
            ]);
            $aplicar ? DB::commit() : DB::rollBack();
        } catch (Throwable $error) {
            DB::rollBack();
            throw $error;
        }

        $this->info($aplicar ? 'RESTAURADO.' : 'SIMULACIÓN de restauración: rollback, la base no cambió. Usa --apply para restaurar.');

        return self::SUCCESS;
    }

    /**
     * Devuelve las filas respaldadas a sus valores y borra solo las creadas (por id y tarea).
     *
     * @param  list<array<string, mixed>>  $creadas
     * @return array{entregas: int, tareas: int, borradas: int}
     */
    protected function revertir(string $carpeta, array $creadas): array
    {
        $entregas = $this->leerJson("{$carpeta}/entregas.json");
        $tareas = $this->leerJson("{$carpeta}/tareas.json");

        foreach ($entregas as $fila) {
            $filas = DB::table('entregas')->where('id', $fila['id'])->where('tarea_id', $fila['tarea_id'])
                ->update(['created_at' => $fila['created_at'], 'updated_at' => $fila['updated_at']]);
            $this->exigir($filas === 1, "restaurar entrega {$fila['id']}: {$filas} filas");
        }

        foreach ($tareas as $fila) {
            $filas = DB::table('tareas')->where('id', $fila['id'])->where('curso_id', $fila['curso_id'])->update(['fecha_entrega' => $fila['fecha_entrega']]);
            $this->exigir($filas === 1, "restaurar tarea {$fila['id']}: {$filas} filas");
        }

        $borradas = 0;
        foreach ($creadas as $fila) {
            $borradas += DB::table('entregas')->where('id', $fila['id'])->where('tarea_id', $fila['tarea_id'])->where('estudiante_id', $fila['estudiante_id'])->delete();
        }
        $this->exigir($borradas === count($creadas), 'se esperaban borrar '.count($creadas)." entregas creadas y fueron {$borradas}");

        return ['entregas' => count($entregas), 'tareas' => count($tareas), 'borradas' => $borradas];
    }

    /**
     * Dentro de la simulación: revierte lo aplicado con el respaldo recién escrito
     * y comprueba que las filas vuelven exactamente a sus valores originales.
     *
     * @param  list<array<string, mixed>>  $creadas
     */
    protected function probarRestauracion(string $carpeta, array $creadas): void
    {
        $conteos = $this->revertir($carpeta, $creadas);
        $originales = collect($this->leerJson("{$carpeta}/entregas.json"))->keyBy('id');
        $distintas = DB::table('entregas')->whereIn('id', $originales->keys())->get()
            ->reject(fn ($fila) => (array) $fila == $originales[$fila->id])->count();
        $tareasOriginales = collect($this->leerJson("{$carpeta}/tareas.json"))->keyBy('id');
        $tareasDistintas = DB::table('tareas')->whereIn('id', $tareasOriginales->keys())->get()
            ->reject(fn ($fila) => (array) $fila == $tareasOriginales[$fila->id])->count();
        $quedan = DB::table('entregas')->whereIn('id', array_column($creadas, 'id'))->count();

        $this->exigir($distintas === 0 && $tareasDistintas === 0 && $quedan === 0,
            "la prueba de restauración no devolvió el estado original ({$distintas} entregas y {$tareasDistintas} tareas distintas, {$quedan} creadas sin borrar)");
        $this->info("Prueba de --restore (dentro de la simulación): OK — {$conteos['entregas']} entregas y {$conteos['tareas']} tareas idénticas al respaldo, {$conteos['borradas']} creadas borradas.");
    }

    /**
     * @param  Collection<int, Curso>  $cursos
     * @param  array<int, string>  $excluidos
     */
    protected function mostrarAlcance(User $profesor, Collection $cursos, array $excluidos): void
    {
        $this->info("Profesor #{$profesor->id} {$profesor->name}");
        $this->line('Cursos en alcance: '.$cursos->map(fn (Curso $curso) => "#{$curso->id} {$curso->nombre}")->join(', '));
        foreach ($excluidos as $motivo) {
            $this->line("  Excluido {$motivo}");
        }
    }

    /** @param  array<string, mixed>  $plan */
    protected function mostrarPlan(array $plan): void
    {
        $this->table(['Tabla', 'Actualizar', 'Crear', 'Borrar'], [
            ['entregas', count($plan['entregas']), count($plan['nuevas']), 0],
            ['tareas', count($plan['tareas']), 0, 0],
        ]);

        $ejemplos = collect($plan['entregas'])->take(7)->map(fn (array $cambio, int $id) => ["entrega {$id} (tarea {$cambio['tarea_id']})", $cambio['antes'], $cambio['despues'], "{$cambio['dias']} d"])
            ->concat(collect($plan['tareas'])->take(3)->map(fn (array $cambio, int $id) => ["tarea {$id} fecha_entrega", $cambio['antes'], $cambio['despues'], '']))
            ->values()->all();
        $this->table(['Fila', 'Antes', 'Después', 'Espera'], $ejemplos);

        if ($plan['nuevas'] !== []) {
            $this->line('Entregas nuevas en la tarea '.$plan['nuevas'][0]['tarea_id'].': '.count($plan['nuevas']).' (una por estudiante con matrícula activa)');
        }
    }

    /**
     * @param  array<string, mixed>  $antes
     * @param  array<string, mixed>  $despues
     */
    protected function mostrarTotales(array $antes, array $despues): void
    {
        $this->table(['Cifra', 'Antes', 'Después'], [
            ['Insignia del dock', $antes['dock'], $despues['dock']],
            ['Hero de Cursos', $antes['hero'], $despues['hero']],
            ['Filas de Por calificar', $antes['por_calificar'], $despues['por_calificar']],
            ['Entregas calificadas', $antes['calificadas'], $despues['calificadas']],
            ...collect($despues['esperas'])->map(fn (int $valor, string $rango) => ["Espera {$rango}", $antes['esperas'][$rango], $valor])->values()->all(),
        ]);
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
