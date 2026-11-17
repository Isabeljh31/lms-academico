<?php

namespace App\Services\Reportes;

use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\SesionClase;
use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lectura de la asistencia para la pantalla rediseñada del Profesor
 * (/cursos/{id}/asistencia). Solo lee: el pase de lista y su guardado siguen
 * en App\Livewire\Asistencia\Index.
 *
 * Replica con agregados SQL el criterio oficial de
 * App\Services\Asistencia\CalculadoraAsistencia (que no se modifica):
 * - solo cuentan las sesiones con fecha <= ahora;
 * - Presente y Tardanza cuentan como asistencia;
 * - Justificado se excluye del total;
 * - una sesión pasada sin registro del estudiante cuenta como Ausente.
 *
 * Un mismo resultado de resumen() alimenta los indicadores, la vista por
 * semana y la cinta de semanas, para que «sin lista» coincida en todos.
 */
class AsistenciaProfesor
{
    /** Menos de este porcentaje: estudiante en riesgo (solo pantallas del Profesor). */
    public const UMBRAL_RIESGO = 70;

    /** Desde este porcentaje: estudiante al día. Entre ambos umbrales: atención. */
    public const UMBRAL_AL_DIA = 85;

    public const NIVELES = [
        'riesgo' => 'En riesgo',
        'atencion' => 'Atención',
        'al_dia' => 'Al día',
    ];

    public const POR_PAGINA = 12;

    public const ULTIMAS_SESIONES = 10;

    /**
     * Nivel de un porcentaje de asistencia según los umbrales del Profesor.
     */
    public static function nivel(?float $porcentaje): ?string
    {
        return match (true) {
            $porcentaje === null => null,
            $porcentaje < self::UMBRAL_RIESGO => 'riesgo',
            $porcentaje < self::UMBRAL_AL_DIA => 'atencion',
            default => 'al_dia',
        };
    }

    /**
     * Porcentaje para mostrar: entero si no tiene decimales, si no con uno (66,7 %).
     */
    public static function porcentajeTexto(?float $porcentaje): string
    {
        if ($porcentaje === null) {
            return '—';
        }

        return (fmod($porcentaje, 1.0) === 0.0 ? number_format($porcentaje, 0) : number_format($porcentaje, 1, ',', '')).' %';
    }

    /**
     * Aula de la clase: la descripción exacta que escribe
     * Curso::generarSesionesDesdeHorario() («Aula: X») o una línea «Aula: X»
     * dentro de una descripción con más texto (SolicitudesCambioClase::descripcionConAula).
     */
    public static function aula(SesionClase $sesion): ?string
    {
        return preg_match('/^[ \t]*Aula:[ \t]*(\S[^\r\n]{0,49}?)[ \t]*$/mu', trim((string) $sesion->descripcion), $coincidencia) === 1
            ? trim($coincidencia[1])
            : null;
    }

    /**
     * Todo lo que necesitan los indicadores, la cinta de semanas y la vista por
     * semana, con un número fijo de consultas (4) sin importar la semana elegida.
     *
     * @return array{
     *     activos: int,
     *     clases: Collection<int, array<string, mixed>>,
     *     semanas: array<int, array<string, mixed>>,
     *     total_semanas: int,
     *     semana_actual: ?int,
     *     pasadas: int,
     *     realizadas: int,
     *     sin_lista: Collection<int, array<string, mixed>>,
     *     proxima: ?array<string, mixed>,
     *     promedio: ?float,
     *     niveles: array{riesgo: int, atencion: int, al_dia: int}
     * }
     */
    public function resumen(Curso $curso, ?Carbon $ahora = null, ?int $totalSemanas = null): array
    {
        $ahora ??= now();
        $activos = $curso->inscripcionesActivas()->count();
        $clases = $this->clases($curso, $activos, $ahora);
        $totalSemanas ??= $curso->totalSemanas();
        $pasadas = $clases->where('pasada', true)->count();
        $niveles = $this->niveles($curso, $pasadas, $ahora);

        return [
            'activos' => $activos,
            'clases' => $clases,
            'semanas' => $this->semanas($curso, $clases, $totalSemanas, $ahora),
            'total_semanas' => $totalSemanas,
            'semana_actual' => CursosProfesor::semanaActual($curso, $totalSemanas, $ahora),
            'pasadas' => $pasadas,
            'realizadas' => $clases->where('estado', 'realizada')->count(),
            'sin_lista' => $clases->where('estado', 'sin_lista')->values(),
            'proxima' => $clases->firstWhere('estado', 'proxima'),
            'promedio' => $niveles['promedio'],
            'niveles' => $niveles['conteo'],
        ];
    }

    /**
     * Clases del curso con sus conteos (solo estudiantes con matrícula activa)
     * y su estado: realizada | sin_lista | proxima | programada.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function clases(Curso $curso, int $activos, Carbon $ahora): Collection
    {
        // Solicitud de cambio vigente de cada clase (marca «Cambio solicitado» y menú ⋮): una consulta para todas.
        $sesiones = $curso->sesionesClase()->orderBy('id')
            ->with('solicitudCambioVigente')
            ->get(['id', 'curso_id', 'titulo', 'fecha', 'duracion_minutos', 'descripcion', 'numero_sesion']);

        if ($sesiones->isEmpty()) {
            return collect();
        }

        $activosIds = $curso->inscripcionesActivas()->select('estudiante_id')->toBase();
        $cuenta = fn (string $estado) => "sum(case when estado = '{$estado}' and estudiante_id in ({$activosIds->toSql()}) then 1 else 0 end) as {$estado}s";
        // «Con lista» = la sesión tiene al menos un registro (mismo criterio que Index para permitir eliminarla).
        $conteos = DB::table('asistencias')
            ->whereIn('sesion_id', $sesiones->pluck('id')->all())
            ->selectRaw('sesion_id, count(*) as registradas, '.$cuenta('presente').', '.$cuenta('tardanza').', '.$cuenta('justificado'),
                [...$activosIds->getBindings(), ...$activosIds->getBindings(), ...$activosIds->getBindings()])
            ->groupBy('sesion_id')
            ->get()
            ->keyBy('sesion_id');

        $proximaId = $sesiones->first(fn (SesionClase $sesion) => $sesion->fecha->gt($ahora))?->id;

        return $sesiones->map(function (SesionClase $sesion) use ($conteos, $activos, $ahora, $proximaId) {
            $fila = $conteos->get($sesion->id);
            $presentes = (int) ($fila->presentes ?? 0);
            $tardanzas = (int) ($fila->tardanzas ?? 0);
            $justificados = (int) ($fila->justificados ?? 0);
            $conLista = (int) ($fila->registradas ?? 0) > 0;
            $pasada = $sesion->fecha->lte($ahora);
            $consideradas = $activos - $justificados;

            return [
                'sesion' => $sesion,
                'id' => $sesion->id,
                'aula' => self::aula($sesion),
                'fin' => $sesion->fecha->copy()->addMinutes((int) $sesion->duracion_minutos),
                'pasada' => $pasada,
                // Regla del Profesor (SesionClase::permiteTomarLista): desde las 00:00 del día de la clase.
                'permite_lista' => $sesion->permiteTomarLista($ahora),
                'con_lista' => $conLista,
                'solicitud' => $sesion->solicitudCambioVigente,
                'cambio_pendiente' => $sesion->solicitudCambioVigente?->estado === 'pendiente',
                'presentes' => $presentes,
                'tardanzas' => $tardanzas,
                'justificados' => $justificados,
                // Sin registro = ausente (criterio oficial).
                'ausentes' => max(0, $activos - $presentes - $tardanzas - $justificados),
                'porcentaje' => $conLista && $consideradas > 0 ? round(($presentes + $tardanzas) / $consideradas * 100, 1) : null,
                'estado' => match (true) {
                    $pasada && $conLista => 'realizada',
                    $pasada => 'sin_lista',
                    $sesion->id === $proximaId => 'proxima',
                    default => 'programada',
                },
            ];
        })->values();
    }

    /**
     * Semanas 1..N del curso (Curso::rangoSemana) con sus clases y su estado:
     * completa | pendiente | actual | proxima | programada | sin_clases | sin_fechas.
     *
     * @param  Collection<int, array<string, mixed>>  $clases
     * @return array<int, array<string, mixed>>
     */
    public function semanas(Curso $curso, Collection $clases, int $totalSemanas, Carbon $ahora): array
    {
        $semanas = [];

        foreach (range(1, max(1, $totalSemanas)) as $numero) {
            $rango = $curso->rangoSemana($numero);
            $deLaSemana = $rango
                ? $clases->filter(fn (array $clase) => $clase['sesion']->fecha->between($rango[0], $rango[1]))->values()
                : collect();
            $conLista = $deLaSemana->where('estado', 'realizada');
            $consideradas = $conLista->sum(fn (array $clase) => $clase['presentes'] + $clase['tardanzas'] + $clase['ausentes']);

            $semanas[$numero] = [
                'numero' => $numero,
                'rango' => $rango,
                'clases' => $deLaSemana,
                'con_lista' => $conLista->count(),
                'sin_lista' => $deLaSemana->where('estado', 'sin_lista')->count(),
                // Asistencia de la semana: solo clases con lista (una clase sin lista no tiene datos que promediar).
                'porcentaje' => $consideradas > 0 ? round($conLista->sum(fn (array $clase) => $clase['presentes'] + $clase['tardanzas']) / $consideradas * 100, 1) : null,
                'momento' => match (true) {
                    $rango === null => 'sin_fechas',
                    $rango[1]->lt($ahora) => 'pasada',
                    $rango[0]->gt($ahora) => 'futura',
                    default => 'actual',
                },
            ];
        }

        $proximaSemana = collect($semanas)->first(fn (array $semana) => $semana['momento'] === 'futura')['numero'] ?? null;

        foreach ($semanas as $numero => $semana) {
            $semanas[$numero]['estado'] = match (true) {
                $semana['momento'] === 'sin_fechas' => 'sin_fechas',
                $semana['sin_lista'] > 0 => 'pendiente',
                $semana['momento'] === 'actual' => 'actual',
                $semana['clases']->isEmpty() => 'sin_clases',
                $semana['momento'] === 'pasada' => 'completa',
                $numero === $proximaSemana => 'proxima',
                default => 'programada',
            };
        }

        return $semanas;
    }

    /**
     * Promedio del curso (media de los porcentajes de los estudiantes) y
     * cuántos estudiantes hay en cada nivel. Una sola consulta agregada.
     *
     * @return array{promedio: ?float, conteo: array{riesgo: int, atencion: int, al_dia: int}}
     */
    protected function niveles(Curso $curso, int $pasadas, Carbon $ahora): array
    {
        $fila = DB::query()->fromSub($this->consultaPorcentajes($curso, $pasadas, $ahora), 'r')
            ->selectRaw('avg(porcentaje) as promedio')
            ->selectRaw('sum(case when porcentaje < ? then 1 else 0 end) as riesgo', [self::UMBRAL_RIESGO])
            ->selectRaw('sum(case when porcentaje >= ? and porcentaje < ? then 1 else 0 end) as atencion', [self::UMBRAL_RIESGO, self::UMBRAL_AL_DIA])
            ->selectRaw('sum(case when porcentaje >= ? then 1 else 0 end) as al_dia', [self::UMBRAL_AL_DIA])
            ->first();

        return [
            'promedio' => $fila?->promedio !== null ? round((float) $fila->promedio, 1) : null,
            'conteo' => [
                'riesgo' => (int) ($fila->riesgo ?? 0),
                'atencion' => (int) ($fila->atencion ?? 0),
                'al_dia' => (int) ($fila->al_dia ?? 0),
            ],
        ];
    }

    /**
     * Resumen por estudiante paginado en la base de datos: búsqueda por nombre,
     * filtro por nivel y orden de menor a mayor asistencia (sin datos al final).
     * Cada fila trae sus últimas sesiones pasadas (una consulta para la página).
     *
     * @param  Collection<int, array<string, mixed>>  $clases  clases de resumen()
     */
    public function estudiantes(Curso $curso, Collection $clases, string $buscar = '', string $nivel = 'todos', ?Carbon $ahora = null): LengthAwarePaginator
    {
        $ahora ??= now();
        $pasadas = $clases->where('pasada', true);

        $consulta = DB::query()->fromSub($this->consultaPorcentajes($curso, $pasadas->count(), $ahora), 'r')
            ->when(trim($buscar) !== '', fn (Builder $q) => $q->whereLike('name', '%'.trim($buscar).'%'))
            ->when($nivel === 'riesgo', fn (Builder $q) => $q->where('porcentaje', '<', self::UMBRAL_RIESGO))
            ->when($nivel === 'atencion', fn (Builder $q) => $q->where('porcentaje', '>=', self::UMBRAL_RIESGO)->where('porcentaje', '<', self::UMBRAL_AL_DIA))
            ->when($nivel === 'al_dia', fn (Builder $q) => $q->where('porcentaje', '>=', self::UMBRAL_AL_DIA))
            ->orderByRaw('case when porcentaje is null then 1 else 0 end')
            ->orderBy('porcentaje')
            ->orderBy('name')
            ->orderBy('id');

        $pagina = $consulta->paginate(self::POR_PAGINA, ['*'], 'pagina');

        $ultimas = $pasadas->sortByDesc(fn (array $clase) => $clase['sesion']->fecha)->take(self::ULTIMAS_SESIONES)->reverse()->values();
        $registros = $ultimas->isEmpty() || $pagina->isEmpty() ? collect() : DB::table('asistencias')
            ->whereIn('sesion_id', $ultimas->pluck('id')->all())
            ->whereIn('estudiante_id', $pagina->pluck('id')->all())
            ->get(['sesion_id', 'estudiante_id', 'estado'])
            ->groupBy('estudiante_id')
            ->map(fn (Collection $filas) => $filas->pluck('estado', 'sesion_id'));

        $pagina->getCollection()->transform(function (object $fila) use ($ultimas, $registros, $pasadas) {
            $porcentaje = $fila->porcentaje !== null ? round((float) $fila->porcentaje, 1) : null;
            $propios = $registros->get($fila->id, collect());

            return [
                'id' => (int) $fila->id,
                'nombre' => $fila->name,
                'presentes' => (int) $fila->presentes,
                'tardanzas' => (int) $fila->tardanzas,
                'justificados' => (int) $fila->justificados,
                'ausentes' => max(0, $pasadas->count() - (int) $fila->presentes - (int) $fila->tardanzas - (int) $fila->justificados),
                'porcentaje' => $porcentaje,
                'nivel' => self::nivel($porcentaje),
                'ultimas' => $ultimas->map(fn (array $clase) => [
                    'sesion' => $clase['sesion'],
                    // Sin lista: se distingue en pantalla, pero el cálculo lo cuenta como ausente.
                    'estado' => $propios->get($clase['id']) ?? ($clase['con_lista'] ? 'ausente' : 'sin_lista'),
                ])->all(),
            ];
        });

        return $pagina;
    }

    /**
     * Una fila por estudiante con matrícula activa: id, name, presentes,
     * tardanzas, justificados (solo sesiones pasadas) y porcentaje oficial.
     */
    protected function consultaPorcentajes(Curso $curso, int $pasadas, Carbon $ahora): Builder
    {
        $sesionesPasadas = DB::table('sesiones_clase')->select('id')->where('curso_id', $curso->id)->where('fecha', '<=', $ahora);
        $cuenta = fn (string $estado) => DB::table('asistencias')->selectRaw('count(*)')
            ->whereColumn('asistencias.estudiante_id', 'users.id')
            ->where('asistencias.estado', $estado)
            ->whereIn('asistencias.sesion_id', $sesionesPasadas);

        $base = DB::table('users')
            ->select(['users.id', 'users.name'])
            ->whereIn('users.id', $curso->inscripcionesActivas()->select('estudiante_id')->toBase())
            ->selectSub($cuenta('presente'), 'presentes')
            ->selectSub($cuenta('tardanza'), 'tardanzas')
            ->selectSub($cuenta('justificado'), 'justificados');

        return DB::query()->fromSub($base, 'e')
            ->select(['e.id', 'e.name', 'e.presentes', 'e.tardanzas', 'e.justificados'])
            ->selectRaw('case when ? - e.justificados > 0 then round((e.presentes + e.tardanzas) * 100.0 / (? - e.justificados), 1) end as porcentaje', [$pasadas, $pasadas]);
    }

    /**
     * Cursos virtuales (sin asistencia por clase): lecciones completadas por
     * estudiante, solo lectura, desde lecciones_completadas. Con $semana,
     * solo las lecciones visibles del módulo de esa semana; sin ella, las de
     * todo el curso (mismo criterio de visibilidad que el avance del curso).
     *
     * @return array{total: int, estudiantes: LengthAwarePaginator}
     */
    public function leccionesCompletadas(Curso $curso, ?int $semana, string $buscar = ''): array
    {
        $lecciones = Leccion::publicadas()
            ->whereHas('modulo', fn ($q) => $q->where('curso_id', $curso->id)->visiblesParaEstudiante()
                ->when($semana !== null, fn ($modulos) => $modulos->where('tipo_seccion', Modulo::TIPO_SEMANA)->where('semana', $semana)))
            ->pluck('id')
            ->all();

        $completadas = DB::table('lecciones_completadas')->selectRaw('count(*)')
            ->whereColumn('lecciones_completadas.estudiante_id', 'users.id')
            ->whereIn('lecciones_completadas.leccion_id', $lecciones === [] ? [0] : $lecciones);
        $ultima = DB::table('lecciones_completadas')->selectRaw('max(completada_en)')
            ->whereColumn('lecciones_completadas.estudiante_id', 'users.id')
            ->whereIn('lecciones_completadas.leccion_id', $lecciones === [] ? [0] : $lecciones);

        $estudiantes = DB::table('users')
            ->select(['users.id', 'users.name'])
            ->whereIn('users.id', $curso->inscripcionesActivas()->select('estudiante_id')->toBase())
            ->when(trim($buscar) !== '', fn (Builder $q) => $q->whereLike('users.name', '%'.trim($buscar).'%'))
            ->selectSub($completadas, 'completadas')
            ->selectSub($ultima, 'ultima')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->paginate(self::POR_PAGINA, ['*'], 'pagina');

        return ['total' => count($lecciones), 'estudiantes' => $estudiantes];
    }
}
