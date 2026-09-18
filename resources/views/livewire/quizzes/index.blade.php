<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm {{ $this->puedeGestionar ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo' }} hover:underline">&larr; {{ $curso->nombre }}</a>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Quizzes</h1>
            </div>

            @if ($this->puedeGestionar)
                <a href="{{ route('cursos.quizzes.crear', $curso) }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nuevo quiz
                </a>
            @endif
        </div>

        @if ($quizzes->isEmpty())
            <p class="text-stone-500 dark:text-stone-400">Todavía no hay quizzes en este curso.</p>
        @elseif ($this->puedeGestionar)
            {{-- Vista de profesor/administrador: sin cambios respecto a la versión anterior. --}}
            <div class="space-y-3">
                @foreach ($quizzes as $quiz)
                    <a href="{{ route('cursos.quizzes.show', [$curso, $quiz]) }}" wire:navigate wire:key="quiz-{{ $quiz->id }}"
                        class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 hover:shadow-md transition">
                        <div>
                            <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $quiz->tema }}</h3>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">
                                {{ $quiz->modulo?->titulo }} · {{ count($quiz->preguntas) }} preguntas
                            </p>
                        </div>

                        <div>
                            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $quiz->resultados->count() }} resueltos</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            {{-- Rediseño visual del rol estudiante: tarjetas con jerarquía por urgencia (ver ARQUITECTURA.md). --}}
            @php
                $tarjetas = $quizzes->map(function ($quiz) {
                    $resultado = $quiz->resultados->first();

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
                                        {{ $t['quiz']->modulo?->titulo }} · {{ count($t['quiz']->preguntas) }} preguntas
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
