<?php

namespace App\Services\Reportes;

use App\Livewire\Docente\PorCalificar\Index as PorCalificar;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\SesionClase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Datos del detalle del curso del Profesor (/cursos/{id}) que no calcula
 * Cursos\Show: pendientes por semana y por tarea, entregas, resultados de
 * quiz, clases con su asistencia, próxima clase, horario y anuncios.
 *
 * Un número fijo de consultas agregadas por página (no crece con las
 * semanas). "Pendiente" es la misma definición que Por calificar y la
 * insignia de la barra inferior (PanelProfesor::pendientesPorActividad, una
 * vez por grupo), y la urgencia usa las reglas de Por calificar.
 */
class DetalleCursoProfesor
{
    /** Días de la semana ISO (1 = lunes) para el horario. */
    public const DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

    public function __construct(protected PanelProfesor $panel) {}

    /**
     * Resumen por semana a partir de las semanas que ya armó Cursos\Show
     * (seccionesDeGestion: modulo, rango, clases, tareas y quizzes).
     *
     * @param  list<array<string, mixed>>  $semanas
     * @return array{semanas: array<int, array<string, mixed>>, tareas: array<int, array{pendientes: int, mas_antigua: ?Carbon, entregadas: int, total: int}>, quizzes: array<int, array{resultados: int, promedio: ?float}>, filtros: array<string, int>, proxima: ?array{sesion: SesionClase, aula: ?string}}
     */
    public function resumen(Curso $curso, array $semanas, ?Carbon $ahora = null): array
    {
        $ahora ??= now();

        // El módulo de cada actividad ya está cargado (es el de su semana): así la etiqueta de publicación no hace una consulta por actividad.
        foreach ($semanas as $semana) {
            $semana['tareas']->each(fn ($tarea) => $tarea->setRelation('modulo', $semana['modulo']));
            $semana['quizzes']->each(fn ($quiz) => $quiz->setRelation('modulo', $semana['modulo']));
        }

        $tareas = collect($semanas)->flatMap(fn (array $semana) => $semana['tareas']);
        $quizzes = collect($semanas)->flatMap(fn (array $semana) => $semana['quizzes']);

        $pendientes = $tareas->isEmpty() ? collect() : $this->panel->pendientesPorActividad(collect([$curso->id]))->keyBy('tarea_id');
        $entregadas = $tareas->isEmpty() ? collect() : DB::table('entregas')->whereIn('tarea_id', $tareas->pluck('id')->all())
            ->selectRaw('tarea_id, count(distinct grupo_id) + sum(case when grupo_id is null then 1 else 0 end) as unidades')
            ->groupBy('tarea_id')->pluck('unidades', 'tarea_id');
        $resultados = $quizzes->isEmpty() ? collect() : DB::table('resultados_quiz')->whereIn('quiz_id', $quizzes->pluck('id')->all())
            ->selectRaw('quiz_id, count(distinct estudiante_id) as estudiantes, avg(puntaje) as promedio')
            ->groupBy('quiz_id')->get()->keyBy('quiz_id');
        $conteos = Curso::whereKey($curso->id)->withCount(['inscripcionesActivas', 'grupos'])->first();
        $sesiones = $curso->sesionesClase()
            ->withCount(['asistencias as registradas', 'asistencias as presentes' => fn ($q) => $q->whereIn('estado', ['presente', 'tardanza'])])
            ->orderBy('fecha')->get();

        $porTarea = $tareas->mapWithKeys(fn ($tarea) => [$tarea->id => [
            'pendientes' => (int) ($pendientes[$tarea->id]['pendientes'] ?? 0),
            'mas_antigua' => $pendientes[$tarea->id]['mas_antigua'] ?? null,
            'entregadas' => (int) ($entregadas[$tarea->id] ?? 0),
            'total' => $tarea->esGrupal() ? (int) $conteos->grupos_count : (int) $conteos->inscripciones_activas_count,
        ]])->all();

        $porSemana = [];
        foreach ($semanas as $semana) {
            $modulo = $semana['modulo'];
            $deLaSemana = collect($porTarea)->only($semana['tareas']->pluck('id')->all());
            $masAntigua = $deLaSemana->pluck('mas_antigua')->filter()->min();
            [$inicio, $fin] = $semana['rango'] ?? [null, null];
            $elementos = $modulo ? $modulo->lecciones_count + $modulo->tareas_count + $modulo->quizzes_count : 0;

            $porSemana[$semana['semana']] = [
                'pendientes' => (int) $deLaSemana->sum('pendientes'),
                'mas_antigua' => $masAntigua,
                'dias' => $masAntigua ? PorCalificar::diasEsperando($masAntigua, $ahora) : null,
                'entregadas' => (int) $deLaSemana->sum('entregadas'),
                'sin_contenido' => $elementos === 0,
                'programada' => $modulo && $modulo->estado_contenido === 'programado' && $modulo->publicar_en?->isAfter($ahora) ? $modulo->publicar_en : null,
                'borrador' => $modulo && $modulo->estado_contenido === 'borrador',
                'momento' => match (true) {
                    $inicio === null => 'sin_fechas',
                    $fin->lt($ahora) => 'pasada',
                    $inicio->gt($ahora) => 'futura',
                    default => 'actual',
                },
                'sesiones' => $inicio ? $sesiones->filter(fn (SesionClase $sesion) => $sesion->fecha && $sesion->fecha->between($inicio, $fin))->values() : collect(),
            ];
        }

        $proxima = $sesiones->first(fn (SesionClase $sesion) => $sesion->fecha && $sesion->fecha->gte($ahora));

        return [
            'semanas' => $porSemana,
            'tareas' => $porTarea,
            'quizzes' => $quizzes->mapWithKeys(fn ($quiz) => [$quiz->id => [
                'resultados' => (int) ($resultados[$quiz->id]->estudiantes ?? 0),
                'promedio' => isset($resultados[$quiz->id]) ? round((float) $resultados[$quiz->id]->promedio, 1) : null,
            ]])->all(),
            'matriculados' => (int) $conteos->inscripciones_activas_count,
            'filtros' => [
                'todas' => count($porSemana),
                'por_calificar' => collect($porSemana)->where('pendientes', '>', 0)->count(),
                'sin_contenido' => collect($porSemana)->where('sin_contenido', true)->count(),
                'programadas' => collect($porSemana)->whereNotNull('programada')->count(),
            ],
            'proxima' => $proxima ? ['sesion' => $proxima, 'aula' => $this->aulaDe($curso, $proxima->fecha)] : null,
        ];
    }

    /**
     * Anuncios del curso con su estado: activos y vencidos (inactivos), el más reciente primero.
     *
     * @return Collection<int, Comunicado>
     */
    public function anuncios(Curso $curso): Collection
    {
        return $curso->comunicados()->with('autor')->orderByRaw('coalesce(publicar_en, created_at) desc')->get();
    }

    /** «Lun y Mié · 08:00 – 10:00» si todos los bloques tienen la misma hora; si no, «Lun 08:00 – 10:00 · Jue 16:00 – 18:00». */
    public static function horario(Curso $curso): ?string
    {
        $bloques = collect($curso->horario_bloques ?? [])->filter(fn ($bloque) => isset(self::DIAS[(int) ($bloque['dia'] ?? 0)], $bloque['hora_inicio'], $bloque['hora_fin']))->sortBy('dia')->values();

        if ($bloques->isEmpty()) {
            return null;
        }

        $horas = $bloques->map(fn ($bloque) => "{$bloque['hora_inicio']} – {$bloque['hora_fin']}")->unique();
        $dias = $bloques->map(fn ($bloque) => self::DIAS[(int) $bloque['dia']]);

        return $horas->count() === 1
            ? $dias->join(', ', ' y ').' · '.$horas->first()
            : $bloques->map(fn ($bloque) => self::DIAS[(int) $bloque['dia']]." {$bloque['hora_inicio']} – {$bloque['hora_fin']}")->join(' · ');
    }

    /** Aulas del horario («Aula A-201» o «Aulas A-201 y B-101»); null si no hay. */
    public static function aulas(Curso $curso): ?string
    {
        $aulas = collect($curso->horario_bloques ?? [])->pluck('aula')->filter()->unique()->values();

        return match ($aulas->count()) {
            0 => null,
            1 => 'Aula '.$aulas->first(),
            default => 'Aulas '.$aulas->join(', ', ' y '),
        };
    }

    /** Aula del bloque del horario que cae ese día de la semana. */
    protected function aulaDe(Curso $curso, Carbon $fecha): ?string
    {
        $bloque = collect($curso->horario_bloques ?? [])->first(fn ($bloque) => (int) ($bloque['dia'] ?? 0) === $fecha->isoWeekday());

        return filled($bloque['aula'] ?? null) ? 'Aula '.$bloque['aula'] : null;
    }

    /** «Dom 15 nov»: día corto, número y mes corto, sin puntos. */
    public static function fechaCorta(Carbon $fecha): string
    {
        return ucfirst(str_replace('.', '', $fecha->copy()->locale('es')->isoFormat('ddd D MMM')));
    }

    /**
     * Clases de Tailwind por urgencia de Por calificar (alta > 7 días, media 3 a 7, baja < 3).
     *
     * @return array{pastilla: string, punto: string}
     */
    public static function estiloUrgencia(int $dias): array
    {
        return match (PorCalificar::urgencia($dias)) {
            'alta' => ['pastilla' => 'bg-[var(--err100)] text-[var(--err700)]', 'punto' => 'bg-[#E04B55]'],
            'media' => ['pastilla' => 'bg-[var(--warn200)] text-[var(--warn800)]', 'punto' => 'bg-[#F2B233]'],
            default => ['pastilla' => 'bg-[var(--ok100)] text-[var(--ok900)]', 'punto' => 'bg-[#8CC63F]'],
        };
    }
}
