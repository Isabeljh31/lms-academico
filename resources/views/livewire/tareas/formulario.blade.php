<div class="py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 mb-6">
            {{ $tarea ? 'Editar tarea' : 'Nueva tarea' }} — {{ $curso->nombre }}
        </h1>

        <form wire:submit="guardar" class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 space-y-6">
            <div>
                <x-input-label for="titulo" value="Título" />
                <x-text-input wire:model="titulo" id="titulo" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('titulo')" class="mt-2" />
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

            <div>
                <x-input-label for="descripcion" value="Descripción / instrucciones" />
                <textarea wire:model="descripcion" id="descripcion" rows="4"
                    class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base"></textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <x-input-label for="fecha_entrega" value="Fecha límite" />
                    <input wire:model="fecha_entrega" id="fecha_entrega" type="datetime-local"
                        class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base">
                    <x-input-error :messages="$errors->get('fecha_entrega')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="peso_nota" value="Peso en la nota final" />
                    <x-text-input wire:model="peso_nota" id="peso_nota" type="number" step="0.01" min="0" max="100" class="mt-1 block w-full" />
                    <x-input-error :messages="$errors->get('peso_nota')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar</x-primary-button>
                <a href="{{ route('cursos.tareas.index', $curso) }}" wire:navigate class="text-sm text-stone-500 hover:underline">Cancelar</a>
            </div>
        </form>
    </div>
</div>
