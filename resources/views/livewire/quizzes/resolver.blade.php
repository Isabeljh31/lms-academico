<div class="py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.quizzes.index', $quiz->curso) }}" wire:navigate class="text-sm text-menta-oscuro dark:text-menta-base hover:underline">&larr; Quizzes</a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">{{ $quiz->tema }}</h1>
        </div>

        @if ($this->resultado)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 text-center">
                <p class="text-sm text-stone-500 dark:text-stone-400">Ya resolviste este quiz</p>
                <p class="text-4xl font-bold text-menta-oscuro dark:text-menta-base mt-2">{{ $this->resultado->puntaje }} / 20</p>
            </div>
        @else
            <form wire:submit="enviar" class="space-y-4">
                @foreach ($quiz->preguntas as $i => $pregunta)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6" wire:key="pregunta-{{ $i }}">
                        <p class="font-medium text-stone-800 dark:text-stone-100 mb-3">{{ $i + 1 }}. {{ $pregunta['enunciado'] }}</p>
                        <div class="space-y-2">
                            @foreach ($pregunta['opciones'] as $j => $opcion)
                                <label class="flex items-center gap-2 text-sm text-stone-600 dark:text-stone-300 cursor-pointer">
                                    <input type="radio" wire:model="respuestas.{{ $i }}" value="{{ $j }}" class="text-menta-oscuro focus:ring-menta-base">
                                    {{ $opcion }}
                                </label>
                            @endforeach
                        </div>
                        <x-input-error :messages="$errors->get("respuestas.$i")" class="mt-2" />
                    </div>
                @endforeach

                <x-input-error :messages="$errors->get('respuestas')" class="mt-2" />

                <x-primary-button>Enviar respuestas</x-primary-button>
            </form>
        @endif
    </div>
</div>
