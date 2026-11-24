{{--
    Pestañas del curso en píldoras (pantallas Actividades y Evaluación del Profesor), con los
    mismos nombres y contadores que la barra del detalle del curso (encabezado-curso).
    Recibe $curso y $activa (clave de la pestaña actual).
--}}
@php
    // Contadores de las pestañas en una sola consulta (subconsultas agregadas).
    $conteosCurso = \App\Models\Curso::whereKey($curso->id)->withCount(['inscripcionesActivas', 'tareas', 'quizzes', 'grupos', 'debates'])->first();
    $pestanasCurso = [
        ['clave' => 'contenido', 'texto' => 'Contenido', 'ruta' => route('cursos.show', $curso)],
        ['clave' => 'actividades', 'texto' => 'Actividades', 'ruta' => route('cursos.actividades', $curso), 'cuenta' => $conteosCurso->tareas_count + $conteosCurso->quizzes_count],
        ['clave' => 'evaluacion', 'texto' => 'Evaluación', 'ruta' => route('cursos.evaluacion', $curso)],
        ['clave' => 'matricula', 'texto' => 'Matrícula', 'ruta' => route('cursos.matriculas', $curso), 'cuenta' => $conteosCurso->inscripciones_activas_count],
        ['clave' => 'grupos', 'texto' => 'Grupos', 'ruta' => route('cursos.grupos.index', $curso), 'cuenta' => $conteosCurso->grupos_count],
        ['clave' => 'debates', 'texto' => 'Debates', 'ruta' => route('cursos.debates.index', $curso), 'cuenta' => $conteosCurso->debates_count],
        ['clave' => 'asistencia', 'texto' => 'Asistencia', 'ruta' => route('asistencia.index', $curso)],
        ['clave' => 'libro', 'texto' => 'Libro de calificaciones', 'ruta' => route('cursos.libro-calificaciones', $curso)],
        ['clave' => 'mensajeria', 'texto' => 'Mensajería', 'ruta' => route('cursos.mensajeria', $curso)],
    ];
    $pildora = 'inline-flex min-h-10 shrink-0 items-center gap-1.5 whitespace-nowrap rounded-lg px-3 text-sm font-medium transition-colors motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2';
@endphp

{{-- flex-wrap: si no cabe en el ancho de la pantalla, pasa a otra línea en vez de cortarse. --}}
<nav aria-label="Secciones del curso" class="flex w-fit max-w-full flex-wrap items-center gap-0.5 rounded-xl border border-celeste bg-white p-1">
    @foreach ($pestanasCurso as $pestanaCurso)
        @php $esActiva = $pestanaCurso['clave'] === $activa; @endphp
        <a href="{{ $pestanaCurso['ruta'] }}" wire:navigate @if ($esActiva) aria-current="page" @endif
            class="{{ $pildora }} {{ $esActiva ? 'bg-texto text-white' : 'text-slate-600 hover:bg-fondo hover:text-texto' }}">
            {{ $pestanaCurso['texto'] }}
            @if (($pestanaCurso['cuenta'] ?? 0) > 0)
                <span class="rounded-full px-1.5 py-px text-[11px] font-bold {{ $esActiva ? 'bg-white/20 text-white' : 'bg-celeste text-slate-600' }}">{{ $pestanaCurso['cuenta'] }}</span>
            @endif
        </a>
    @endforeach
</nav>
