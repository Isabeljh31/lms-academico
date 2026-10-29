{{-- Elementos de una sección de contenido del estudiante (prácticas de campo o semana):
     lecciones con el ícono de su tipo de recurso, tareas y quizzes. Recibe $modulo, $seccion,
     $curso y $leccionesCompletadas. --}}
@foreach ($modulo->leccionesVisibles as $leccion)
    @php $tipo = $leccion->tipoRecurso(); @endphp
    <div class="flex gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-800">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-azul-oscuro ring-1 ring-celeste dark:bg-azul-superficie-oscura dark:text-azul-fondo dark:ring-azul-borde-oscuro" title="{{ $tipo['etiqueta'] }}">
            <x-icono :nombre="$tipo['icono']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $tipo['etiqueta'] }}</p>
            <h4 class="font-semibold text-sm text-texto dark:text-slate-200">{{ $leccion->titulo }}</h4>
            @if($leccion->descripcion)
                <p class="text-xs text-slate-500 mt-1">{{ $leccion->descripcion }}</p>
            @endif
            <div class="flex flex-wrap gap-2 mt-3">
                @if($leccion->video_url)
                    <a href="{{ route('cursos.lecciones.recurso', [$curso, $leccion, 'video']) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1.5 bg-azul-oscuro hover:bg-azul-oscuro/90 text-white rounded text-xs font-medium transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Ver Video
                    </a>
                @endif
                @if($leccion->material_url)
                    <a href="{{ route('cursos.lecciones.recurso', [$curso, $leccion, 'material']) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-azul-oscuro text-azul-oscuro hover:bg-celeste/20 rounded text-xs font-medium transition">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Material Adjunto
                    </a>
                @endif
                @php $completada = in_array($leccion->id, $leccionesCompletadas, true); @endphp
                @if ($leccion->seCompletaAlAbrir())
                    {{-- RF-67: se completa sola al abrir el video o el material. --}}
                    <span wire:key="completar-{{ $leccion->id }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-xs font-medium {{ $completada ? 'bg-azul-oscuro text-white' : 'bg-white border border-azul-oscuro text-azul-oscuro' }}">
                        <x-icono nombre="check" class="w-4 h-4" />
                        {{ $completada ? 'Completada' : 'Se completa al abrir el recurso' }}
                    </span>
                @else
                    <button type="button" wire:click="alternarLeccionCompletada({{ $leccion->id }})" wire:key="completar-{{ $leccion->id }}"
                        class="inline-flex items-center gap-1 px-3 py-1.5 rounded text-xs font-medium transition {{ $completada ? 'bg-azul-oscuro hover:bg-azul-oscuro/90 text-white' : 'bg-white border border-azul-oscuro text-azul-oscuro hover:bg-celeste/20' }}">
                        <x-icono nombre="check" class="w-4 h-4" />
                        {{ $completada ? 'Completada' : 'Marcar como completada' }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endforeach

@foreach ($seccion['tareas'] as $fila)
    <a href="{{ route('cursos.tareas.show', [$curso, $fila['tarea']]) }}" wire:navigate wire:key="semana-tarea-{{ $fila['tarea']->id }}"
        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3 transition hover:border-celeste hover:bg-white dark:border-slate-700 dark:bg-slate-800">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-cielo">
            <x-icono nombre="tarea" class="h-5 w-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Tarea</span>
            <span class="block truncate text-sm font-semibold text-texto dark:text-slate-200">{{ $fila['tarea']->titulo }}</span>
            <span class="block text-xs text-slate-500 dark:text-slate-400">Vence: {{ $fila['tarea']->fecha_entrega?->formatoFechaHora() ?? 'Sin fecha límite' }}</span>
        </span>
        <x-badge-estado :estado="$fila['estado']" />
    </a>
@endforeach

@foreach ($seccion['quizzes'] as $fila)
    <a href="{{ route('cursos.quizzes.show', [$curso, $fila['quiz']]) }}" wire:navigate wire:key="semana-quiz-{{ $fila['quiz']->id }}"
        class="flex items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3 transition hover:border-celeste hover:bg-white dark:border-slate-700 dark:bg-slate-800">
        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
            <x-icono nombre="quiz" class="h-5 w-5" />
        </span>
        <span class="min-w-0 flex-1">
            <span class="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Quiz</span>
            <span class="block truncate text-sm font-semibold text-texto dark:text-slate-200">{{ $fila['quiz']->tema }}</span>
            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ count($fila['quiz']->preguntas ?? []) }} preguntas</span>
        </span>
        @if ($fila['resultado'])
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-verde-fondo/50 text-verde-oscuro">{{ $fila['resultado']->puntaje }} / 20</span>
        @else
            <x-badge-estado estado="pendiente" />
        @endif
    </a>
@endforeach
