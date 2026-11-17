{{--
    Contenido de una semana abierta del detalle del curso del Profesor:
    clases (sesiones de la semana con su asistencia), las cuatro secciones
    predefinidas (Leccion::SECCIONES) y las evaluaciones de la semana (tareas
    y quizzes, que se crean y editan en Actividades). Recibe del bucle de
    _semanas: $seccion, $modulo, $numero, $datos y $detalleCurso.
--}}
@php
    $conContenido = $modulo ? $modulo->lecciones->toBase()->map(fn ($leccion) => $leccion->seccion())->intersect(array_keys(\App\Models\Leccion::SECCIONES))->unique()->count() : 0;
    $hayAsistencia = $datos['sesiones']->contains(fn ($sesion) => $sesion->registradas > 0);
    $subtitulo = 'text-[11px] font-bold uppercase tracking-[0.12em] text-[var(--p700)]';
@endphp

@if ($datos['sesiones']->isNotEmpty())
    <div class="flex flex-wrap items-center gap-2.5 border-t border-[#dde9e6] px-1 pb-3 pt-2.5" aria-label="Clases de la {{ $seccion['etiqueta'] }}">
        <span class="mr-0.5 text-xs font-bold text-[var(--p700)]">Clases</span>
        @foreach ($datos['sesiones'] as $sesion)
            <span class="inline-flex items-center gap-2 rounded-[9px] border-[1.5px] border-[#d4e5e0] bg-white px-2.5 py-1 text-[12.5px] text-[#3d5753]" wire:key="semana-{{ $numero }}-sesion-{{ $sesion->id }}">
                {{ \App\Services\Reportes\DetalleCursoProfesor::fechaCorta($sesion->fecha) }} · {{ $sesion->fecha->format('H:i') }}
                @if ($sesion->registradas > 0)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--ok100)] px-2 py-px text-[11px] font-bold text-[var(--ok900)]" title="Presentes o con tardanza / registrados">
                        <x-icono nombre="check" class="h-[13px] w-[13px]" />{{ $sesion->presentes }}/{{ $sesion->registradas }}
                    </span>
                @endif
            </span>
        @endforeach
        @if ($hayAsistencia)
            <span class="text-[12.5px] text-[#5b706c]">asistencia registrada</span>
        @endif
        <a href="{{ route('asistencia.index', $curso) }}" wire:navigate class="ml-auto rounded text-[12.5px] font-bold text-[var(--p700)] hover:underline {{ $enfoque }}">Ver asistencia →</a>
    </div>
@endif

<div class="flex items-baseline gap-2.5 px-1 pb-1 pt-3">
    <h4 class="{{ $subtitulo }}">Contenido de la semana</h4>
    <span class="inline-grid grid-cols-[repeat(4,20px)] gap-[3px] self-center" aria-hidden="true">
        @foreach (range(1, 4) as $segmento)
            <span class="h-1.5 rounded-[3px] {{ $segmento <= $conContenido ? 'bg-[var(--p700)]' : 'bg-[#dce8e4]' }}"></span>
        @endforeach
    </span>
    <small class="text-xs text-[#5b706c]">{{ $conContenido }} de 4 secciones con contenido</small>
</div>

@include('livewire.docente.cursos._secciones', ['modulo' => $modulo, 'semanaClave' => $numero])

<div class="flex items-baseline gap-2.5 px-1 pb-1 pt-[18px]">
    <h4 class="{{ $subtitulo }}">Evaluaciones de la semana</h4>
    <small class="text-xs text-[#5b706c]">se crean y editan en Actividades</small>
</div>
@if ($seccion['tareas']->isNotEmpty() || $seccion['quizzes']->isNotEmpty())
    <ul class="mt-1 rounded-[14px] border-[1.5px] border-[#d9e8e4] bg-white" aria-label="Evaluaciones de la {{ $seccion['etiqueta'] }}">
        @foreach ($seccion['tareas'] as $tarea)
            @php
                $estado = $detalleCurso['tareas'][$tarea->id];
                $diasCierre = \App\Services\Reportes\PanelProfesor::diasHasta($tarea->fecha_entrega);
            @endphp
            <li class="grid grid-cols-[44px_1fr_auto] items-center gap-3.5 border-t border-[#e6efec] px-3.5 py-3 first:border-t-0" wire:key="semana-{{ $numero }}-tarea-{{ $tarea->id }}" data-tarea="{{ $tarea->id }}">
                <span class="grid h-11 w-11 place-items-center rounded-[13px] border-[1.5px] border-[#d4e5e0] bg-[var(--mint)] text-[var(--p700)]" aria-hidden="true"><x-icono nombre="tarea" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-[var(--p900)]">
                        {{ $tarea->titulo }}
                        <span class="rounded-full bg-[#ECF1F0] px-[9px] py-0.5 text-[11px] font-bold text-[#4b625e]">{{ $tarea->esGrupal() ? 'Grupal' : 'Individual' }}</span>
                        @unless ($tarea->esVisibleParaEstudiante())
                            <span class="rounded-full bg-[var(--warn200)] px-[9px] py-0.5 text-[11px] font-bold text-[var(--warn800)]">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                        @endunless
                    </p>
                    <p class="mt-[3px] text-[12.5px] text-[#5b706c]">
                        {{ $tarea->estaVencida() ? 'Venció' : 'Vence' }} {{ mb_strtolower(\App\Services\Reportes\DetalleCursoProfesor::fechaCorta($tarea->fecha_entrega)) }} · {{ $tarea->fecha_entrega->format('H:i') }}
                        @if (! $tarea->estaVencida() && $diasCierre <= 1)
                            <span class="ml-1 rounded-full bg-[var(--warn200)] px-[9px] py-0.5 text-[11.5px] font-bold text-[var(--warn800)]">{{ \App\Services\Reportes\PanelProfesor::textoDias($diasCierre) }}</span>
                        @endif
                        · {{ $estado['entregadas'] }} de {{ $estado['total'] }} {{ $tarea->esGrupal() ? 'grupos entregaron' : 'entregadas' }}
                    </p>
                    <p class="mt-0.5 flex flex-wrap gap-x-3 text-xs">
                        <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate class="rounded font-semibold text-[var(--p700)] hover:underline {{ $enfoque }}" aria-label="Ver entregas de {{ $tarea->titulo }}">Ver entregas</a>
                        @if ($editable)
                            <a href="{{ route('cursos.tareas.editar', [$curso, $tarea]) }}" wire:navigate class="rounded font-semibold text-[#4b625e] hover:underline {{ $enfoque }}" aria-label="Editar {{ $tarea->titulo }}">Editar</a>
                        @endif
                    </p>
                </div>
                @if ($estado['pendientes'] > 0)
                    <a href="{{ route('docente.por-calificar', ['tarea' => $tarea->id]) }}" wire:navigate
                        class="inline-flex h-[38px] min-w-[132px] items-center justify-center gap-[7px] whitespace-nowrap rounded-[10px] bg-[var(--p900)] px-3.5 text-[12.5px] font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                        Calificar {{ $estado['pendientes'] }} <x-icono nombre="flecha-derecha" class="h-4 w-4" />
                    </a>
                @elseif ($estado['entregadas'] > 0)
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-[var(--ok100)] px-[9px] py-0.5 text-[11.5px] font-bold text-[var(--ok900)]"><x-icono nombre="check" class="h-[13px] w-[13px]" /> Todo calificado</span>
                @else
                    <span class="text-[12.5px] text-[#5b706c]">Sin entregas</span>
                @endif
            </li>
        @endforeach
        @foreach ($seccion['quizzes'] as $quiz)
            @php $resultado = $detalleCurso['quizzes'][$quiz->id]; @endphp
            <li class="grid grid-cols-[44px_1fr_auto] items-center gap-3.5 border-t border-[#e6efec] px-3.5 py-3 first:border-t-0" wire:key="semana-{{ $numero }}-quiz-{{ $quiz->id }}" data-quiz="{{ $quiz->id }}">
                <span class="grid h-11 w-11 place-items-center rounded-[13px] border-[1.5px] border-[#d4e5e0] bg-[var(--mint)] text-[var(--p700)]" aria-hidden="true"><x-icono nombre="quiz" class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-2 text-sm font-bold text-[var(--p900)]">
                        {{ $quiz->tema }}
                        <span class="rounded-full bg-[#ECF1F0] px-[9px] py-0.5 text-[11px] font-bold text-[#4b625e]">Se califica solo</span>
                        @unless ($quiz->esVisibleParaEstudiante())
                            <span class="rounded-full bg-[var(--warn200)] px-[9px] py-0.5 text-[11px] font-bold text-[var(--warn800)]">{{ $quiz->etiquetaEstadoPublicacion() }}</span>
                        @endunless
                    </p>
                    <p class="mt-[3px] text-[12.5px] text-[#5b706c]">
                        @if ($quiz->fecha_limite)
                            {{ $quiz->fecha_limite->isPast() ? 'Cerró' : 'Cierra' }} {{ mb_strtolower(\App\Services\Reportes\DetalleCursoProfesor::fechaCorta($quiz->fecha_limite)) }} ·
                        @else
                            Sin fecha límite ·
                        @endif
                        {{ $resultado['resultados'] }} de {{ $detalleCurso['matriculados'] }} {{ $resultado['promedio'] !== null ? 'resultados · promedio '.number_format($resultado['promedio'], 1, ',', '') : 'resultados' }}
                    </p>
                    @if ($editable)
                        <p class="mt-0.5 text-xs"><a href="{{ route('cursos.quizzes.editar', [$curso, $quiz]) }}" wire:navigate class="rounded font-semibold text-[#4b625e] hover:underline {{ $enfoque }}" aria-label="Editar {{ $quiz->tema }}">Editar</a></p>
                    @endif
                </div>
                <a href="{{ route('cursos.quizzes.estadisticas', [$curso, $quiz]) }}" wire:navigate
                    class="inline-flex h-[38px] min-w-[132px] items-center justify-center whitespace-nowrap rounded-[10px] border-[1.5px] border-[#c9ddd8] bg-white px-3.5 text-[12.5px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoque }}">Ver resultados</a>
            </li>
        @endforeach
    </ul>
@else
    <p class="mt-1 rounded-[14px] border-[1.5px] border-dashed border-[#c9ddd8] px-3.5 py-3 text-[12.5px] text-[#5b706c]">Esta semana no tiene tareas ni quizzes.</p>
@endif
<div class="mt-0.5 flex justify-between gap-3 border-t border-[#e6efec] px-1.5 pt-2.5 text-[12.5px] font-bold text-[var(--p700)]">
    <span>¿Crear o editar tareas y quizzes de esta semana?</span>
    <a href="{{ route('cursos.actividades', $curso) }}" wire:navigate class="rounded hover:underline {{ $enfoque }}">Ir a Actividades →</a>
</div>
