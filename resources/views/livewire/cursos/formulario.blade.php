<div class="py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 mb-6">
            {{ $curso ? 'Editar curso' : 'Nuevo curso' }}
        </h1>

        <form wire:submit="guardar" class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-6 space-y-6">

            {{-- Nombre --}}
            <div>
                <x-input-label for="nombre" value="Nombre del curso *" />
                <x-text-input wire:model="nombre" id="nombre" class="mt-1 block w-full" required autofocus />
                <x-input-error :messages="$errors->get('nombre')" class="mt-2" />
            </div>

            {{-- Profesor (solo Supervisor) --}}
            @if(auth()->user()->hasRole('supervisor'))
            <div>
                <x-input-label for="profesor_id" value="Profesor titular asignado" />
                <select wire:model="profesor_id" id="profesor_id"
                    class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                    <option value="">-- Seleccionar profesor titular --</option>
                    @foreach($profesores as $prof)
                    <option value="{{ $prof->id }}">{{ $prof->name }} ({{ $prof->email }})</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-stone-500">Como supervisor, puedes asignar o reasignar el docente a cargo.</p>
                <x-input-error :messages="$errors->get('profesor_id')" class="mt-2" />
            </div>
            @endif

            {{-- Descripción --}}
            <div>
                <x-input-label for="descripcion" value="Descripción" />
                <textarea wire:model="descripcion" id="descripcion" rows="4"
                    class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base"></textarea>
                <x-input-error :messages="$errors->get('descripcion')" class="mt-2" />
            </div>

            {{-- Portada --}}
            <div>
                <x-input-label for="portada" value="Portada" />
                @if($curso?->imagen_portada_url && !$portada)
                    <img src="{{ $curso->imagen_portada_url }}" class="mt-2 h-24 rounded-lg object-cover">
                @endif
                @if($portada)
                    <img src="{{ $portada->temporaryUrl() }}" class="mt-2 h-24 rounded-lg object-cover">
                @endif
                <input wire:model="portada" id="portada" type="file" accept="image/*"
                    class="mt-2 block w-full text-sm text-stone-600 dark:text-stone-400
                           file:mr-4 file:rounded-lg file:border-0 file:bg-terracota-base
                           file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-terracota-oscuro">
                <x-input-error :messages="$errors->get('portada')" class="mt-2" />
            </div>

            {{-- Separador: configuración académica --}}
            <div class="border-t border-stone-200 dark:border-stone-700 pt-6">
                <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300 mb-4">
                    Configuración académica
                </h2>

                {{-- ERS RF-22: Estado del curso --}}
                <div class="mb-4">
                    <x-input-label for="estado" value="Estado del curso *" />
                    <select wire:model="estado" id="estado"
                        class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        @foreach($estadosCurso as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('estado')" class="mt-2" />
                </div>

                {{-- ERS RF-19: Fechas --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
                    <div>
                        <x-input-label for="fecha_inicio" value="Fecha de inicio" />
                        <input wire:model="fecha_inicio" id="fecha_inicio" type="date"
                            class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <x-input-error :messages="$errors->get('fecha_inicio')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="fecha_fin" value="Fecha de fin" />
                        <input wire:model="fecha_fin" id="fecha_fin" type="date"
                            class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <x-input-error :messages="$errors->get('fecha_fin')" class="mt-2" />
                    </div>
                </div>

                {{-- ERS RF-19: Semanas y sesiones --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="semanas_duracion" value="Duración (semanas)" />
                        <input wire:model="semanas_duracion" id="semanas_duracion" type="number" min="1" max="104"
                            placeholder="ej. 16"
                            class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <x-input-error :messages="$errors->get('semanas_duracion')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sesiones_clase_count" value="N.° de sesiones de clase" />
                        <input wire:model="sesiones_clase_count" id="sesiones_clase_count" type="number" min="1" max="999"
                            placeholder="ej. 32"
                            class="mt-1 block w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <x-input-error :messages="$errors->get('sesiones_clase_count')" class="mt-2" />
                    </div>
                </div>
            </div>

            {{-- Botones --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <a href="{{ route('cursos.index') }}" wire:navigate
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </a>
                <x-primary-button type="submit">
                    {{ $curso ? 'Guardar cambios' : 'Crear curso' }}
                </x-primary-button>
            </div>
        </form>
    </div>
</div>
