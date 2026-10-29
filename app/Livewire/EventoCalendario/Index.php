<?php

namespace App\Livewire\EventoCalendario;

use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EventoCalendario;
use App\Models\Quiz;
use App\Models\SesionClase;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Calendario\ColoresCursos;
use App\Services\Evaluacion\NotasDeEvaluacion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * ERS RF-78/RF-79: calendario de cada usuario con sus eventos propios y las
 * actividades de sus cursos, orientado a lo que necesita cada rol:
 *
 * - Estudiante: tareas, quizzes, evaluaciones y sesiones de los cursos en los
 *   que tiene matrícula activa (solo contenido publicado) + agenda de su semana.
 * - Profesor: lo mismo de los cursos que dicta + sesiones de la semana,
 *   entregas por calificar agrupadas por semana y evaluaciones por registrar.
 * - Supervisor: panorama institucional: evaluaciones (rubros) e inicio/fin de
 *   los cursos publicados, comunicados programados y avance de cada curso.
 *
 * Cada curso tiene un color propio y el calendario se puede filtrar por curso
 * y por tipo de actividad.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    /** Colores de la paleta con buen contraste para texto blanco, uno por curso. */
    public const COLORES_CURSO = ColoresCursos::PALETA;

    /** @var array<string, string> tipo de evento propio => color */
    public const COLORES_PROPIOS = [
        'bloque_estudio' => '#0E7490',
        'recordatorio' => '#9A3412',
        'evento' => '#57534E',
    ];

    /** Orden dentro de un día: primero lo que se evalúa o se entrega. */
    public const ORDEN_TIPOS = ['evaluacion' => 1, 'hito' => 2, 'tarea' => 3, 'quiz' => 4, 'comunicado' => 5, 'propio' => 6, 'sesion' => 7];

    /** Fecha (Y-m-d) en la que abre el calendario, p. ej. desde "Ver en calendario" de una propuesta. */
    #[Url(as: 'fecha', history: true)]
    public string $fechaInicial = '';

    /** Id del evento a resaltar (tarea-12, quiz-3, evento-7...). */
    #[Url(as: 'evento', history: true)]
    public string $eventoResaltado = '';

    /** Pantalla de origen, para ofrecer el regreso (solo "propuestas"). */
    #[Url(as: 'desde')]
    public string $origen = '';

    public string $nuevoTitulo = '';

    public function mount(): void
    {
        // Parámetros de la URL: se descartan los que no tienen el formato esperado.
        $partes = preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $this->fechaInicial, $m) ? [(int) $m[2], (int) $m[3], (int) $m[1]] : null;
        $this->fechaInicial = $partes && checkdate(...$partes) ? $this->fechaInicial : '';
        $this->eventoResaltado = preg_match('/^[a-z]+-\d+(-[a-z]+)?$/', $this->eventoResaltado) ? $this->eventoResaltado : '';
        $this->origen = $this->origen === 'propuestas' ? 'propuestas' : '';
    }

    public string $nuevaFecha = '';

    public string $nuevoTipo = 'bloque_estudio';

    public function crear(): void
    {
        $this->validate([
            'nuevoTitulo' => ['required', 'string', 'max:255'],
            'nuevaFecha' => ['required', 'date'],
            'nuevoTipo' => ['required', 'in:bloque_estudio,recordatorio,evento'],
        ], [
            'nuevoTitulo.required' => 'Escribe un título para el evento.',
            'nuevaFecha.required' => 'Elige la fecha y hora.',
        ]);

        $evento = auth()->user()->eventosCalendario()->create([
            'titulo' => $this->nuevoTitulo,
            'fecha' => $this->nuevaFecha,
            'tipo' => $this->nuevoTipo,
        ]);

        $this->reset(['nuevoTitulo', 'nuevaFecha']);

        $this->dispatch('evento-creado', evento: $this->payloadEvento($evento));
    }

    public function eliminar(int $eventoId): void
    {
        $evento = EventoCalendario::where('usuario_id', auth()->id())->findOrFail($eventoId);
        $evento->delete();

        $this->dispatch('evento-eliminado', id: 'evento-'.$eventoId);
    }

    /**
     * Las horas se envían sin desfase (Y-m-d\TH:i:s): FullCalendar las muestra
     * tal cual, en la zona horaria de la plataforma (RF-108), sin convertirlas
     * a la zona del navegador.
     *
     * @return array<string, mixed>
     */
    protected function payloadEvento(EventoCalendario $e): array
    {
        return [
            'id' => 'evento-'.$e->id,
            'title' => $e->titulo,
            'start' => $e->fecha->format('Y-m-d\TH:i:s'),
            'backgroundColor' => self::COLORES_PROPIOS[$e->tipo] ?? self::COLORES_PROPIOS['evento'],
            'borderColor' => 'transparent',
            'textColor' => '#ffffff',
            'orden' => self::ORDEN_TIPOS['propio'],
            'extendedProps' => ['tipo' => 'propio', 'subtipo' => $e->tipo, 'propio' => true, 'idNumerico' => $e->id, 'cursoId' => null],
        ];
    }

    protected function rol(): string
    {
        $usuario = auth()->user();

        return match (true) {
            $usuario->hasRole('supervisor') => 'supervisor',
            $usuario->hasRole('profesor') => 'profesor',
            default => 'estudiante',
        };
    }

    /**
     * Cursos cuyas actividades aparecen en el calendario del usuario.
     *
     * @return Collection<int, Curso>
     */
    #[Computed]
    public function cursos(): Collection
    {
        return app(ColoresCursos::class)->consultaCursosDe(auth()->user())
            ->with('esquemaEvaluacion.rubros')->orderBy('nombre')->orderBy('id')->get()->values();
    }

    /**
     * Color de cada curso (estable dentro de la lista del usuario y el mismo
     * que muestra la bandeja de propuestas del asistente).
     *
     * @return array<int, string> curso_id => color
     */
    #[Computed]
    public function coloresCursos(): array
    {
        return app(ColoresCursos::class)->asignar($this->cursos);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    protected function evento(string $id, string $titulo, Carbon $inicio, int $cursoId, string $tipo, ?string $url = null, array $extra = [], bool $todoElDia = false): array
    {
        $color = $this->coloresCursos[$cursoId] ?? self::COLORES_CURSO[0];
        // Las clases son frecuentes: estilo secundario (translúcido) para que destaque lo que se entrega o se evalúa.
        $secundario = $tipo === 'sesion';

        return array_filter([
            'id' => $id,
            'title' => $titulo,
            'start' => $todoElDia ? $inicio->toDateString() : $inicio->format('Y-m-d\TH:i:s'),
            'allDay' => $todoElDia ?: null,
            'url' => $url,
            'backgroundColor' => $secundario ? $color.'1F' : $color,
            'borderColor' => $secundario ? $color.'66' : 'transparent',
            'textColor' => $secundario ? $color : '#ffffff',
            'classNames' => ['evento-'.$tipo],
            'orden' => self::ORDEN_TIPOS[$tipo] ?? 9,
            'extendedProps' => ['tipo' => $tipo, 'propio' => false, 'cursoId' => $cursoId, ...$extra],
        ], fn ($valor) => $valor !== null);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function eventos(): array
    {
        $usuario = auth()->user();
        $rol = $this->rol();
        $esEstudiante = $rol === 'estudiante';
        $cursos = $this->cursos;
        $cursoIds = $cursos->pluck('id');

        $propios = $usuario->eventosCalendario()->get()->map(fn (EventoCalendario $e) => $this->payloadEvento($e));

        // Evaluaciones: cada rubro con semana se ubica en el lunes de esa semana del curso.
        $evaluaciones = $cursos
            ->filter(fn (Curso $c) => $c->fecha_inicio && $c->esquemaEvaluacion)
            ->flatMap(fn (Curso $c) => $c->esquemaEvaluacion->rubros
                ->filter(fn ($rubro) => $rubro->semana)
                ->map(fn ($rubro) => $this->evento(
                    'rubro-'.$rubro->id,
                    'Evaluación: '.$rubro->nombre.' · '.($c->codigo ?? $c->nombre).' ('.(float) $rubro->peso.' %)',
                    $this->semanaDelCurso($c, $rubro->semana),
                    $c->id,
                    'evaluacion',
                    match ($rol) {
                        'estudiante' => route('cursos.boleta', $c),
                        default => route('cursos.libro-calificaciones', $c),
                    },
                    todoElDia: true,
                )));

        if ($rol === 'supervisor') {
            return $propios->concat($evaluaciones)->concat($this->hitosDeCursos($cursos))->concat($this->comunicadosProgramados())->values()->all();
        }

        $pendientesPorTarea = $esEstudiante ? collect() : Entrega::where('estado', 'entregado')
            ->whereIn('tarea_id', Tarea::whereIn('curso_id', $cursoIds)->select('id'))
            ->get()
            ->countBy('tarea_id');

        $tareas = Tarea::whereIn('curso_id', $cursoIds)
            ->when($esEstudiante, fn ($q) => $q->visiblesParaEstudiante())
            ->with('curso')
            ->get()
            ->when($esEstudiante, fn ($c) => $c->filter(fn (Tarea $t) => Gate::allows('ver', $t)))
            ->map(fn (Tarea $t) => $this->evento(
                'tarea-'.$t->id,
                'Tarea: '.$t->titulo.(($pendientesPorTarea[$t->id] ?? 0) > 0 ? ' · '.$pendientesPorTarea[$t->id].' por calificar' : ''),
                $t->fecha_entrega,
                $t->curso_id,
                'tarea',
                route('cursos.tareas.show', [$t->curso_id, $t->id]),
                ['porCalificar' => $pendientesPorTarea[$t->id] ?? 0],
            ));

        $quizzes = Quiz::whereIn('curso_id', $cursoIds)
            ->whereNotNull('fecha_limite')
            ->when($esEstudiante, fn ($q) => $q->visiblesParaEstudiante())
            ->get()
            ->map(fn (Quiz $q) => $this->evento(
                'quiz-'.$q->id, 'Quiz: '.$q->tema, $q->fecha_limite, $q->curso_id, 'quiz',
                route('cursos.quizzes.show', [$q->curso_id, $q->id])
            ));

        $codigos = $cursos->mapWithKeys(fn (Curso $c) => [$c->id => $c->codigo ?? $c->nombre]);
        $sesiones = SesionClase::whereIn('curso_id', $cursos->where('usa_asistencia', true)->pluck('id'))
            ->get()
            ->map(fn (SesionClase $s) => $this->evento(
                'sesion-'.$s->id, 'Clase '.$codigos[$s->curso_id].' · '.$s->titulo, $s->fecha, $s->curso_id, 'sesion',
                $esEstudiante ? route('cursos.mi-asistencia', $s->curso_id) : route('asistencia.index', $s->curso_id)
            ));

        return $propios->concat($tareas)->concat($quizzes)->concat($sesiones)->concat($evaluaciones)->values()->all();
    }

    /**
     * Supervisor: inicio y fin de cada curso publicado.
     *
     * @param  Collection<int, Curso>  $cursos
     * @return Collection<int, array<string, mixed>>
     */
    protected function hitosDeCursos(Collection $cursos): Collection
    {
        return $cursos->flatMap(fn (Curso $c) => collect([
            $c->fecha_inicio ? $this->evento('inicio-'.$c->id, 'Inicio: '.$c->nombre, $c->fecha_inicio, $c->id, 'hito', route('cursos.show', $c), todoElDia: true) : null,
            $c->fecha_fin ? $this->evento('fin-'.$c->id, 'Cierre: '.$c->nombre, $c->fecha_fin, $c->id, 'hito', route('cursos.show', $c), todoElDia: true) : null,
        ])->filter());
    }

    /**
     * Supervisor: comunicados institucionales con publicación programada.
     *
     * @return Collection<int, array<string, mixed>>
     */
    protected function comunicadosProgramados(): Collection
    {
        return Comunicado::whereNull('curso_id')->where('activo', true)->whereNotNull('publicar_en')->where('publicar_en', '>=', now()->subMonth())
            ->get()
            ->map(fn (Comunicado $c) => [
                'id' => 'comunicado-'.$c->id,
                'title' => 'Comunicado: '.$c->titulo,
                'start' => $c->publicar_en->format('Y-m-d\TH:i:s'),
                'url' => route('supervisor.comunicados.index'),
                'backgroundColor' => '#57534E',
                'borderColor' => 'transparent',
                'textColor' => '#ffffff',
                'classNames' => ['evento-comunicado'],
                'orden' => self::ORDEN_TIPOS['comunicado'],
                'extendedProps' => ['tipo' => 'comunicado', 'propio' => false, 'cursoId' => null],
            ]);
    }

    protected function semanaDelCurso(Curso $curso, int $semana): Carbon
    {
        return $curso->fecha_inicio->copy()->startOfWeek()->addWeeks($semana - 1);
    }

    /**
     * Semana actual del curso (1..N) o null si no empezó o ya terminó.
     */
    protected function semanaActual(Curso $curso): ?int
    {
        if (! $curso->fecha_inicio || $curso->fecha_inicio->isFuture()) {
            return null;
        }

        $semana = (int) floor($curso->fecha_inicio->copy()->startOfWeek()->diffInDays(now()) / 7) + 1;

        return $curso->semanas_duracion && $semana > $curso->semanas_duracion ? null : $semana;
    }

    /**
     * Panel lateral orientado al rol (agenda de la semana, pendientes, panorama).
     *
     * @return array<string, mixed>
     */
    #[Computed]
    public function agenda(): array
    {
        return match ($this->rol()) {
            'supervisor' => $this->agendaSupervisor(),
            'profesor' => $this->agendaProfesor(auth()->user()),
            default => $this->agendaEstudiante(auth()->user()),
        };
    }

    /**
     * @return array<string, mixed>
     */
    protected function agendaEstudiante(User $estudiante): array
    {
        $cursoIds = $this->cursos->pluck('id');
        $hasta = now()->addDays(7)->endOfDay();

        $tareas = Tarea::whereIn('curso_id', $cursoIds)->visiblesParaEstudiante()
            ->whereBetween('fecha_entrega', [now()->subDays(7), $hasta])
            ->with(['curso', 'entregas' => fn ($q) => $q->where('estudiante_id', $estudiante->id)])
            ->orderBy('fecha_entrega')
            ->get()
            ->filter(fn (Tarea $t) => Gate::allows('ver', $t))
            ->map(fn (Tarea $t) => ['tarea' => $t, 'estado' => $t->estadoPara($t->entregas->first())]);

        return [
            'porEntregar' => $tareas->filter(fn ($item) => $item['estado'] === 'pendiente' || $item['estado'] === 'devuelto')->values(),
            'vencidas' => $tareas->filter(fn ($item) => $item['estado'] === 'no_entregado')->values(),
            'quizzes' => Quiz::whereIn('curso_id', $cursoIds)->visiblesParaEstudiante()
                ->whereBetween('fecha_limite', [now(), $hasta])
                ->whereDoesntHave('resultados', fn ($q) => $q->where('estudiante_id', $estudiante->id))
                ->with('curso')->orderBy('fecha_limite')->get(),
            'sesiones' => SesionClase::whereIn('curso_id', $this->cursos->where('usa_asistencia', true)->pluck('id'))
                ->whereBetween('fecha', [now()->startOfDay(), $hasta])->with('curso')->orderBy('fecha')->limit(8)->get(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function agendaProfesor(User $profesor): array
    {
        $cursos = $this->cursos;
        $inicioSemana = now()->startOfWeek();

        $porCalificar = Entrega::where('estado', 'entregado')
            ->whereHas('tarea', fn ($q) => $q->whereIn('curso_id', $cursos->pluck('id')))
            ->with('tarea.curso')
            ->get()
            ->groupBy(fn (Entrega $e) => $e->tarea->fecha_entrega->copy()->startOfWeek()->toDateString())
            ->sortKeys()
            ->map(fn (Collection $entregas, string $semana) => [
                'semana' => Carbon::parse($semana),
                'total' => $entregas->count(),
                'tareas' => $entregas->groupBy('tarea_id')->map(fn ($grupo) => ['tarea' => $grupo->first()->tarea, 'total' => $grupo->count()])->values(),
            ])
            ->values();

        $evaluaciones = $cursos
            ->filter(fn (Curso $c) => $c->fecha_inicio && $c->esquemaEvaluacion)
            ->flatMap(fn (Curso $c) => $c->esquemaEvaluacion->rubros->map(fn ($rubro) => [
                'curso' => $c,
                'rubro' => $rubro,
                'fecha' => $this->semanaDelCurso($c, (int) $rubro->semana),
            ]))
            ->filter(fn ($item) => $item['fecha']->between($inicioSemana->copy()->subWeek(), $inicioSemana->copy()->addWeeks(2)))
            ->sortBy(fn ($item) => $item['fecha'])
            ->values();

        return [
            'sesiones' => SesionClase::whereIn('curso_id', $cursos->where('usa_asistencia', true)->pluck('id'))
                ->whereBetween('fecha', [now(), now()->addDays(7)->endOfDay()])
                ->with('curso')->orderBy('fecha')->limit(8)->get(),
            'porCalificar' => $porCalificar,
            'evaluaciones' => $evaluaciones,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function agendaSupervisor(): array
    {
        $inicioSemana = now()->startOfWeek();

        $cursos = $this->cursos->map(fn (Curso $c) => [
            'curso' => $c,
            'semana' => $this->semanaActual($c),
            'total' => $c->semanas_duracion,
        ]);

        $evaluaciones = $this->cursos
            ->filter(fn (Curso $c) => $c->fecha_inicio && $c->esquemaEvaluacion)
            ->flatMap(fn (Curso $c) => $c->esquemaEvaluacion->rubros->map(fn ($rubro) => [
                'curso' => $c,
                'rubro' => $rubro,
                'fecha' => $this->semanaDelCurso($c, (int) $rubro->semana),
            ]))
            ->filter(fn ($item) => $item['fecha']->between($inicioSemana, $inicioSemana->copy()->addWeeks(4)->endOfWeek()))
            ->sortBy(fn ($item) => $item['fecha'])
            ->values();

        return [
            'cursos' => $cursos,
            'evaluaciones' => $evaluaciones,
            'notasPendientes' => $this->notasPendientes(),
            'comunicados' => Comunicado::whereNull('curso_id')->where('activo', true)->where('publicar_en', '>', now())->orderBy('publicar_en')->limit(5)->get(),
        ];
    }

    /**
     * Supervisor: evaluaciones cuya semana ya pasó y que aún tienen
     * estudiantes sin nota registrada (seguimiento de RF-57).
     *
     * @return Collection<int, array{curso: Curso, rubro: mixed, faltan: int}>
     */
    protected function notasPendientes(): Collection
    {
        return $this->cursos
            ->filter(fn (Curso $c) => $c->fecha_inicio && $c->esquemaEvaluacion)
            ->flatMap(function (Curso $curso) {
                $estudiantes = $curso->inscripcionesActivas()->pluck('estudiante_id');
                $notas = app(NotasDeEvaluacion::class)
                    ->resolver($curso->esquemaEvaluacion->rubros, $estudiantes);

                return $curso->esquemaEvaluacion->rubros
                    ->filter(fn ($rubro) => $rubro->semana && $this->semanaDelCurso($curso, (int) $rubro->semana)->copy()->endOfWeek()->isPast())
                    ->map(fn ($rubro) => [
                        'curso' => $curso,
                        'rubro' => $rubro,
                        'faltan' => $estudiantes->filter(fn ($id) => ($notas[$id][$rubro->id] ?? null) === null)->count(),
                    ]);
            })
            ->filter(fn ($item) => $item['faltan'] > 0)
            ->values();
    }

    public function render()
    {
        return view('livewire.evento-calendario.index', ['rol' => $this->rol()])
            ->title('Calendario — LearnUp');
    }
}
