@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = auth()->user()->hasRole('estudiante');
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.quizzes.index', $quiz->curso) }}" wire:navigate
                class="text-sm {{ $paletaFria ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base' }} hover:underline">&larr; Quizzes</a>
            <h1 class="text-2xl font-bold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-900 dark:text-stone-100' }}">{{ $quiz->tema }}</h1>
        </div>

        @if ($this->resultado)
            <div class="bg-white rounded-xl border p-6 text-center {{ $paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">Ya resolviste este quiz</p>
                <p class="text-4xl font-bold {{ $paletaFria ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base' }} mt-2">{{ $this->resultado->puntaje }} / 20</p>
            </div>
        @else
            <form wire:submit="enviar" class="space-y-4">
                @foreach ($quiz->preguntas as $i => $pregunta)
                    <div class="bg-white rounded-xl border p-6 {{ $paletaFria ? 'dark:bg-azul-superficie-oscura border-celeste dark:border-azul-borde-oscuro' : 'dark:bg-stone-800 border-arena-medio dark:border-stone-700' }}" wire:key="pregunta-{{ $i }}">
                        <p class="font-medium {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-800 dark:text-stone-100' }} mb-3">{{ $i + 1 }}. {{ $pregunta['enunciado'] }}</p>
                        <div class="space-y-2">
                            @foreach ($pregunta['opciones'] as $j => $opcion)
                                <label class="flex items-center gap-2 text-sm {{ $paletaFria ? 'text-slate-600 dark:text-slate-300' : 'text-stone-600 dark:text-stone-300' }} cursor-pointer">
                                    <input type="radio" wire:model="respuestas.{{ $i }}" value="{{ $j }}"
                                        class="{{ $paletaFria ? 'text-azul-oscuro focus:ring-azul-fondo' : 'text-menta-oscuro focus:ring-menta-base' }}">
                                    {{ $opcion }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get('respuestas.'.$i)" class="mt-2" />
                    </div>
                @endforeach

                <x-input-error :messages="$errors->get('respuestas')" class="mt-2" />

                <x-primary-button>Enviar respuestas</x-primary-button>
            </form>
        @endif
    </div>
</div>
