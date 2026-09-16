<div class="py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 mb-6">
            {{ $curso ? 'Editar curso' : 'Nuevo curso' }}
        </h1>

        <form wire:submit="guardar" class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 space-y-6">
            <div>
                <x-input-label for="nombre" value="Nombre del curso" />
                <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="descripcion" value="Descripción" />
                <textarea wire:model="descripcion" id="descripcion" rows="4"
                    class="mt-1 block w-full rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base"></textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="portada" value="Portada" />

                @if ($curso?->imagen_portada_url && ! $portada)
                    <img src="{{ $curso->imagen_portada_url }}" class="mt-2 h-24 rounded-md object-cover">
                @endif
                @if ($portada)
                    <img src="{{ $portada->temporaryUrl() }}" class="mt-2 h-24 rounded-md object-cover">
                @endif

                <input wire:model="portada" id="portada" type="file" accept="image/*"
                    class="mt-2 block w-full text-sm text-stone-600 dark:text-stone-400 file:me-4 file:rounded-md file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-menta-oscuro/90" />
                <x-input-error :messages="$errors->get('portada')" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <x-primary-button>Guardar</x-primary-button>
                <a href="{{ $curso ? route('cursos.show', $curso) : route('cursos.index') }}" wire:navigate class="text-sm text-stone-500 hover:underline">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>
