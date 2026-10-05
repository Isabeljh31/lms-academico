@php $soloLectura = ! $this->editable; @endphp

<div class="py-8">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Grupos" icono="usuarios" :descripcion="$curso->nombre.' · Organiza a los estudiantes matriculados para los trabajos grupales.'"
            :volver="route('cursos.show', $curso)" volver-texto="Volver al curso" />

        <x-gestion.alertas />

        @unless ($soloLectura)
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.tarjeta titulo="Nuevo grupo">
                    <form wire:submit="crearGrupo" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <label for="nuevoGrupoNombre" class="sr-only">Nombre del grupo</label>
                            <x-gestion.control id="nuevoGrupoNombre" wire:model="nuevoGrupoNombre" placeholder="Nombre del grupo" />
                            @error('nuevoGrupoNombre') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.boton type="submit" icono="mas">Crear</x-gestion.boton>
                    </form>
                </x-gestion.tarjeta>

                <x-gestion.tarjeta titulo="Generar grupos automáticamente" descripcion="Reparte solo a los estudiantes que aún no tienen grupo.">
                    <form wire:submit="generarAutomatico" class="flex flex-col gap-2 sm:flex-row sm:items-start">
                        <div class="flex-1">
                            <label for="tamanoGrupoAuto" class="sr-only">Estudiantes por grupo</label>
                            <x-gestion.control id="tamanoGrupoAuto" type="number" min="2" max="20" wire:model="tamanoGrupoAuto" />
                            @error('tamanoGrupoAuto') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <x-gestion.boton type="submit" variante="secundario" icono="refrescar">Repartir</x-gestion.boton>
                    </form>
                </x-gestion.tarjeta>
            </div>
        @endunless

        @if ($sinGrupo->isNotEmpty())
            <div class="rounded-xl border border-arena-medio bg-arena-claro p-4 text-sm dark:border-stone-700 dark:bg-stone-800/60">
                <p class="font-medium text-stone-700 dark:text-stone-200">Sin grupo todavía ({{ $sinGrupo->count() }})</p>
                <p class="mt-1 text-stone-600 dark:text-stone-400">{{ $sinGrupo->pluck('name')->implode(', ') }}</p>
            </div>
        @endif

        @forelse ($grupos as $grupo)
            <x-gestion.tarjeta wire:key="grupo-{{ $grupo->id }}">
                <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    @if ($grupoRenombrarId === $grupo->id)
                        <form wire:submit="renombrar" class="flex flex-1 gap-2">
                            <label for="renombrar-{{ $grupo->id }}" class="sr-only">Nuevo nombre</label>
                            <x-gestion.control id="renombrar-{{ $grupo->id }}" wire:model="nombreRenombrar" />
                            <x-gestion.boton type="submit" tamano="sm" icono="check">Guardar</x-gestion.boton>
                            <x-gestion.boton variante="fantasma" tamano="sm" wire:click="$set('grupoRenombrarId', null)">Cancelar</x-gestion.boton>
                        </form>
                    @else
                        <h2 class="font-semibold text-stone-800 dark:text-stone-100">{{ $grupo->nombre }} <span class="text-sm font-normal text-stone-500">· {{ $grupo->estudiantes->count() }} integrantes</span></h2>
                    @endif
                    @unless ($soloLectura || $grupoRenombrarId === $grupo->id)
                        <div class="flex gap-1">
                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="empezarRenombrar({{ $grupo->id }})">Renombrar</x-gestion.boton>
                            <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarGrupo({{ $grupo->id }})"
                                wire:confirm="¿Eliminar el grupo {{ $grupo->nombre }}? Las entregas ya hechas por sus integrantes no se borran.">Eliminar</x-gestion.boton>
                        </div>
                    @endunless
                </div>
                @error('nombreRenombrar') @if ($grupoRenombrarId === $grupo->id) <p class="mb-2 text-xs text-red-600">{{ $message }}</p> @endif @enderror

                @if ($grupo->estudiantes->isEmpty())
                    <p class="mb-3 text-sm text-stone-500">Todavía no tiene integrantes.</p>
                @else
                    <div class="mb-3 flex flex-wrap gap-2">
                        @foreach ($grupo->estudiantes as $estudiante)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-menta-fondo py-1 pl-3 pr-1.5 text-sm text-menta-oscuro dark:bg-menta-oscuro/20 dark:text-menta-fondo" wire:key="miembro-{{ $grupo->id }}-{{ $estudiante->id }}">
                                {{ $estudiante->name }}
                                @unless ($soloLectura)
                                    <button type="button" wire:click="quitar({{ $grupo->id }}, {{ $estudiante->id }})" wire:confirm="¿Quitar a {{ $estudiante->name }} de {{ $grupo->nombre }}?"
                                        class="flex h-5 w-5 items-center justify-center rounded-full hover:bg-menta-oscuro/20" aria-label="Quitar a {{ $estudiante->name }}">
                                        <x-icono nombre="cerrar" class="h-3 w-3" />
                                    </button>
                                @endunless
                            </span>
                        @endforeach
                    </div>
                @endif

                @if (! $soloLectura && $matriculados->isNotEmpty())
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <label for="seleccion-{{ $grupo->id }}" class="sr-only">Agregar o mover estudiante</label>
                        <x-gestion.control tipo="select" id="seleccion-{{ $grupo->id }}" wire:model="seleccion.{{ $grupo->id }}">
                            <option value="">Agregar o mover un estudiante…</option>
                            @foreach ($matriculados as $estudiante)
                                @continue(($grupoDe[$estudiante->id] ?? null) === $grupo->nombre)
                                <option value="{{ $estudiante->id }}">{{ $estudiante->name }}{{ isset($grupoDe[$estudiante->id]) ? ' (ahora en '.$grupoDe[$estudiante->id].')' : '' }}</option>
                            @endforeach
                        </x-gestion.control>
                        <x-gestion.boton variante="secundario" icono="usuario-mas" wire:click="asignar({{ $grupo->id }})">Agregar</x-gestion.boton>
                    </div>
                @endif
            </x-gestion.tarjeta>
        @empty
            <x-gestion.vacio icono="usuarios" titulo="Todavía no hay grupos en este curso"
                descripcion="Crea un grupo o genera varios automáticamente con los estudiantes matriculados." />
        @endforelse
    </div>
</div>
