<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if ($this->esVistaDocente)
        <x-gestion.encabezado titulo="Quizzes" icono="quiz" :descripcion="$curso->nombre.', las notas de quizzes son de práctica y no entran en la nota final ponderada.'">
            @if ($this->puedeGestionar)
                <x-gestion.boton variante="secundario" icono="base-datos" :href="route('cursos.banco-preguntas', $curso)">Banco de preguntas</x-gestion.boton>
            @endif
            @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                <x-gestion.boton icono="mas" :href="route('cursos.quizzes.crear', $curso)">Nuevo quiz</x-gestion.boton>
            @endif
        </x-gestion.encabezado>
        <x-gestion.alertas />
        @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm {{ $this->puedeGestionar ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo' }} hover:underline">&larr; {{ $curso->nombre }}</a>
                <h1 class="text-2xl font-bold {{ $this->puedeGestionar ? 'text-stone-900 dark:text-stone-100' : 'text-texto dark:text-slate-100' }}">Quizzes</h1>
            </div>

            @if ($this->puedeGestionar)
                <a href="{{ route('cursos.quizzes.crear', $curso) }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nuevo quiz
                </a>
            @endif
        </div>

        @endif

        @if ($quizzes->isEmpty() && ! $this->esVistaDocente)
            <p class="text-stone-500 dark:text-stone-400">Todavía no hay quizzes en este curso.</p>
        @elseif ($quizzes->isEmpty())
            <x-gestion.vacio icono="quiz" titulo="Todavía no hay quizzes en este curso" descripcion="Crea un quiz con preguntas propias o tomadas del banco de preguntas." />
        @elseif ($this->esVistaDocente)
            {{-- Vista de Profesor / Supervisor (este último en solo lectura). --}}
            <x-gestion.tarjeta :relleno="false">
                <ul class="divide-y {{ auth()->user()->hasRole('supervisor') ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
                    @foreach ($quizzes as $quiz)
                        @php
                            $visibleEstudiante = $quiz->esVisibleParaEstudiante();
                            $esAdminVista = auth()->user()->hasRole('supervisor');
                            $claseBadgeNeutra = $esAdminVista ? 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200' : 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200';
                        @endphp
                        <li wire:key="quiz-{{ $quiz->id }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                            <a href="{{ route('cursos.quizzes.show', [$curso, $quiz]) }}" wire:navigate class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-medium {{ $esAdminVista ? 'text-stone-800 dark:text-stone-100' : 'text-texto dark:text-slate-100' }}">{{ $quiz->tema }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $visibleEstudiante ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">{{ $quiz->etiquetaEstadoPublicacion() }}</span>
                                </div>
                                <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                    {{ $quiz->modulo?->nombreCompleto() }} - {{ count($quiz->preguntas ?? []) }} preguntas - {{ $quiz->resultados->unique('estudiante_id')->count() }} estudiantes lo rindieron
                                    @if ($quiz->fecha_limite) - cierra {{ $quiz->fecha_limite->formatoFechaHora() }} @endif
                                </p>
                            </a>
                            <div class="flex shrink-0 flex-wrap gap-1">
                                @if ($quiz->resultados->isNotEmpty())
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="grafico" :href="route('cursos.quizzes.estadisticas', [$curso, $quiz])">Estadísticas</x-gestion.boton>
                                @endif
                                @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" :href="route('cursos.quizzes.editar', [$curso, $quiz])">Editar</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma" tamano="sm" :icono="$quiz->visible ? 'ojo-tachado' : 'ojo'" wire:click="alternarVisibilidad({{ $quiz->id }})">
                                        {{ $quiz->visible ? 'Ocultar' : 'Publicar' }}
                                    </x-gestion.boton>
                                    @if ($quiz->resultados->isEmpty())
                                        <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $quiz->id }})"
                                            wire:confirm="¿Eliminar el quiz {{ $quiz->tema }}? Esta acción no se puede deshacer.">Eliminar</x-gestion.boton>
                                    @endif
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @else
            {{-- Rediseño visual del rol estudiante: tarjetas con jerarquía por urgencia (ver ARQUITECTURA.md). --}}
            @php
                $tarjetas = $quizzes->map(function ($quiz) {
                    $resultado = $quiz->resultadoDe(auth()->user());

                    return ['quiz' => $quiz, 'resultado' => $resultado];
                })->sortBy(fn ($t) => $t['resultado'] ? 1 : 0);
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($tarjetas as $t)
                    <a href="{{ route('cursos.quizzes.show', [$curso, $t['quiz']]) }}" wire:navigate wire:key="quiz-card-{{ $t['quiz']->id }}"
                        class="rounded-xl border p-4 shadow-sm dark:shadow-none transition hover:shadow-md
                            {{ $t['resultado']
                                ? 'border-celeste dark:border-azul-borde-oscuro bg-celeste/20 dark:bg-azul-superficie-oscura/60 opacity-80'
                                : 'border-lavanda-oscuro/40 dark:border-lavanda-oscuro/30 bg-white dark:bg-azul-superficie-oscura' }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                    {{ $t['resultado'] ? 'bg-celeste/50 text-slate-600 dark:bg-azul-borde-oscuro dark:text-slate-300' : 'bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo' }}">
                                    <x-icono nombre="quiz" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="font-medium text-texto dark:text-slate-100 truncate">{{ $t['quiz']->tema }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ $t['quiz']->modulo?->nombreCompleto() }} - {{ count($t['quiz']->preguntas) }} preguntas
                                    </p>
                                </div>
                            </div>

                            @if ($t['resultado'])
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-verde-fondo/50 text-verde-oscuro shrink-0">
                                    {{ $t['resultado']->puntaje }} / 20
                                </span>
                            @else
                                <x-badge-estado estado="pendiente" />
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
