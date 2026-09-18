<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm text-menta-oscuro dark:text-menta-base hover:underline">&larr; {{ $curso->nombre }}</a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Grupos</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Organiza a los estudiantes inscritos en grupos para asignarles trabajos en equipo.</p>
        </div>

        {{-- Crear grupo + generar automático --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <form wire:submit="crearGrupo" class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 space-y-2">
                <x-input-label for="nuevoGrupoNombre" value="Nuevo grupo" />
                <div class="flex gap-2">
                    <x-text-input wire:model="nuevoGrupoNombre" id="nuevoGrupoNombre" class="flex-1" placeholder="Nombre del grupo…" />
                    <x-secondary-button type="submit">Crear</x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('nuevoGrupoNombre')" />
            </form>

            <form wire:submit="generarAutomatico" class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 space-y-2">
                <x-input-label for="tamanoGrupoAuto" value="Generar grupos automáticos" />
                <div class="flex items-center gap-2">
                    <span class="text-sm text-stone-500 dark:text-stone-400 whitespace-nowrap">Estudiantes por grupo:</span>
                    <x-text-input wire:model="tamanoGrupoAuto" id="tamanoGrupoAuto" type="number" min="2" max="20" class="w-20" />
                    <x-secondary-button type="submit">Repartir</x-secondary-button>
                </div>
                <x-input-error :messages="$errors->get('tamanoGrupoAuto')" />
                <p class="text-xs text-stone-400">Reparte solo a los estudiantes que todavía no están en ningún grupo.</p>
            </form>
        </div>

        {{-- Sin grupo --}}
        @if ($sinGrupo->isNotEmpty())
            <div class="bg-arena-claro dark:bg-stone-800/60 rounded-xl border border-arena-medio dark:border-stone-700 p-4">
                <p class="text-sm font-medium text-stone-700 dark:text-stone-200 mb-1">Sin grupo todavía ({{ $sinGrupo->count() }})</p>
                <p class="text-sm text-stone-500 dark:text-stone-400">{{ $sinGrupo->pluck('name')->implode(', ') }}</p>
            </div>
        @endif

        {{-- Lista de grupos --}}
        @forelse ($grupos as $grupo)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5" wire:key="grupo-{{ $grupo->id }}">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-semibold text-stone-800 dark:text-stone-100">{{ $grupo->nombre }}</h2>
                    <button wire:click="eliminarGrupo({{ $grupo->id }})" wire:confirm="¿Eliminar este grupo? Las entregas ya hechas por sus integrantes no se borran."
                        class="text-xs text-terracota-oscuro hover:underline">Eliminar grupo</button>
                </div>

                @if ($grupo->estudiantes->isEmpty())
                    <p class="text-sm text-stone-400 mb-3">Todavía no tiene integrantes.</p>
                @else
                    <div class="flex flex-wrap gap-2 mb-3">
                        @foreach ($grupo->estudiantes as $estudiante)
                            <span class="inline-flex items-center gap-1.5 pl-3 pr-1.5 py-1 rounded-full bg-menta-fondo dark:bg-menta-oscuro/20 text-sm text-menta-oscuro dark:text-menta-fondo" wire:key="miembro-{{ $grupo->id }}-{{ $estudiante->id }}">
                                {{ $estudiante->name }}
                                <button wire:click="quitar({{ $grupo->id }}, {{ $estudiante->id }})" wire:confirm="¿Quitar a {{ $estudiante->name }} de {{ $grupo->nombre }}?"
                                    class="h-4 w-4 flex items-center justify-center rounded-full hover:bg-menta-oscuro/20" title="Quitar">
                                    <x-icono nombre="cerrar" class="h-3 w-3" />
                                </button>
                            </span>
                        @endforeach
                    </div>
                @endif

                @if ($sinGrupo->isNotEmpty())
                    <div class="flex items-center gap-2">
                        <select wire:model="seleccion.{{ $grupo->id }}"
                            class="flex-1 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                            <option value="">Agregar estudiante…</option>
                            @foreach ($sinGrupo as $estudiante)
                                <option value="{{ $estudiante->id }}">{{ $estudiante->name }}</option>
                            @endforeach
                        </select>
                        <x-secondary-button wire:click="asignar({{ $grupo->id }})">Agregar</x-secondary-button>
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-stone-500 dark:text-stone-400">Todavía no hay grupos en este curso. Crea uno arriba.</p>
        @endforelse
    </div>
</div>
