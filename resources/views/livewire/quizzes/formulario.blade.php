<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 mb-6">
            {{ $quiz ? 'Editar quiz' : 'Nuevo quiz' }} — {{ $curso->nombre }}
        </h1>

        <form wire:submit="guardar" class="space-y-6">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 space-y-4">
                <div>
                    <x-input-label for="tema" value="Tema del quiz" />
                    <x-text-input wire:model="tema" id="tema" class="mt-1 block w-full" required autofocus />
                    <x-input-error :messages="$errors->get('tema')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="modulo_id" value="Unidad" />
                    <select wire:model="modulo_id" id="modulo_id"
                        class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base">
                        <option value="">Selecciona una unidad…</option>
                        @foreach ($modulos as $modulo)
                            <option value="{{ $modulo->id }}">{{ $modulo->titulo }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('modulo_id')" class="mt-2" />
                </div>
            </div>

            @foreach ($preguntas as $i => $pregunta)
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 space-y-3" wire:key="pregunta-{{ $i }}">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold uppercase text-stone-400">Pregunta {{ $i + 1 }}</span>
                        @if (count($preguntas) > 1)
                            <button type="button" wire:click="eliminarPregunta({{ $i }})" class="text-xs text-terracota-oscuro hover:underline">Quitar pregunta</button>
                        @endif
                    </div>

                    <input type="text" wire:model="preguntas.{{ $i }}.enunciado" placeholder="Enunciado de la pregunta…"
                        class="block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base">
                    <x-input-error :messages="$errors->get('preguntas.'.$i.'.enunciado')" class="mt-1" />

                    <div class="space-y-2">
                        @foreach ($pregunta['opciones'] as $j => $opcion)
                            <div class="flex items-center gap-2" wire:key="opcion-{{ $i }}-{{ $j }}">
                                <input type="radio" wire:model="preguntas.{{ $i }}.respuesta_correcta" value="{{ $j }}"
                                    class="text-menta-oscuro focus:ring-menta-base" title="Marcar como correcta">
                                <input type="text" wire:model="preguntas.{{ $i }}.opciones.{{ $j }}" placeholder="Opción {{ $j + 1 }}"
                                    class="flex-1 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                                @if (count($pregunta['opciones']) > 2)
                                    <button type="button" wire:click="eliminarOpcion({{ $i }}, {{ $j }})" class="text-xs text-stone-400 hover:text-terracota-oscuro">✕</button>
                                @endif
                            </div>
                        @endforeach
                        <x-input-error :messages="$errors->get('preguntas.'.$i.'.opciones')" class="mt-1" />
                    </div>

                    <button type="button" wire:click="agregarOpcion({{ $i }})" class="text-xs font-semibold text-menta-oscuro dark:text-menta-base hover:underline">
                        + Opción
                    </button>
                    <p class="text-xs text-stone-400">Marca el círculo junto a la opción correcta.</p>
                </div>
            @endforeach

            <button type="button" wire:click="agregarPregunta"
                class="w-full py-2 rounded-md border-2 border-dashed border-arena-medio dark:border-stone-700 text-sm text-stone-500 dark:text-stone-400 hover:border-menta-base hover:text-menta-oscuro">
                + Agregar pregunta
            </button>

            <div class="flex items-center gap-2">
                <input wire:model="visible" id="visible" type="checkbox"
                    class="rounded border-stone-300 dark:border-stone-600 text-menta-oscuro focus:ring-menta-base">
                <x-input-label for="visible" value="Visible para los estudiantes" class="!mb-0" />
            </div>
            <p class="text-xs text-stone-400">
                Desmárcalo si todavía lo estás preparando: quedará guardado pero oculto hasta que lo publiques desde el listado de quizzes.
            </p>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar quiz</x-primary-button>
                <a href="{{ route('cursos.quizzes.index', $curso) }}" wire:navigate class="text-sm text-stone-500 hover:underline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
