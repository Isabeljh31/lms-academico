<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Comunicados institucionales" icono="megafono"
            descripcion="Avisos oficiales para estudiantes, profesores o ambos. Se muestran en su panel y se notifican al publicarse.">
            <x-gestion.boton :icono="$mostrarFormulario ? 'cerrar' : 'mas'" wire:click="$toggle('mostrarFormulario')">
                {{ $mostrarFormulario ? 'Cerrar formulario' : 'Nuevo comunicado' }}
            </x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if ($mostrarFormulario)
            <x-gestion.tarjeta titulo="Nuevo comunicado">
                <form wire:submit="crearComunicado" class="space-y-4">
                    <x-gestion.campo etiqueta="Título" nombre="titulo" requerido>
                        <x-gestion.control id="titulo" wire:model="titulo" placeholder="Cierre de actas del ciclo" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Contenido" nombre="contenido" requerido>
                        <x-gestion.control tipo="textarea" id="contenido" rows="4" wire:model="contenido" />
                    </x-gestion.campo>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-gestion.campo etiqueta="Destinatarios" nombre="destinatario" requerido>
                            <x-gestion.control tipo="select" id="destinatario" wire:model="destinatario">
                                <option value="todos">Estudiantes y profesores</option>
                                <option value="estudiantes">Solo estudiantes</option>
                                <option value="profesores">Solo profesores</option>
                            </x-gestion.control>
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Prioridad" nombre="prioridad" requerido>
                            <x-gestion.control tipo="select" id="prioridad" wire:model="prioridad">
                                <option value="informativo">Informativo</option>
                                <option value="urgente">Urgente</option>
                            </x-gestion.control>
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Publicar el" nombre="publicarEn" ayuda="Vacío = publicar ahora.">
                            <x-gestion.control id="publicarEn" type="datetime-local" wire:model="publicarEn" />
                        </x-gestion.campo>
                    </div>
                    <div class="flex justify-end gap-2">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarFormulario', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton type="submit" icono="megafono">Publicar comunicado</x-gestion.boton>
                    </div>
                </form>
            </x-gestion.tarjeta>
        @endif

        @if ($comunicados->isEmpty())
            <x-gestion.vacio icono="megafono" titulo="Aún no hay comunicados" descripcion="Redacta el primero con el botón Nuevo comunicado." />
        @else
            <div class="space-y-3">
                @foreach ($comunicados as $com)
                    @php
                        $etiquetaDestino = ['todos' => 'Estudiantes y profesores', 'estudiantes' => 'Estudiantes', 'profesores' => 'Profesores'][$com->destinatario] ?? $com->destinatario;
                    @endphp
                    <x-gestion.tarjeta wire:key="com-{{ $com->id }}" class="{{ $com->activo ? '' : 'opacity-70' }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    @if ($com->prioridad === 'urgente')
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300"><x-icono nombre="alerta" class="h-3 w-3" /> Urgente</span>
                                    @endif
                                    <span class="rounded-full bg-arena-claro px-2 py-0.5 text-stone-600 dark:bg-stone-700 dark:text-stone-300">{{ $etiquetaDestino }}</span>
                                    @if (! $com->activo)
                                        <span class="rounded-full bg-arena-medio px-2 py-0.5 text-stone-700">Inactivo</span>
                                    @elseif (! $com->estaPublicado())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-arena-claro px-2 py-0.5 text-terracota-oscuro"><x-icono nombre="reloj" class="h-3 w-3" /> Programado {{ $com->publicar_en->formatoFechaHora() }}</span>
                                    @else
                                        <span class="rounded-full bg-exito-base/15 px-2 py-0.5 text-exito-oscuro">Publicado</span>
                                    @endif
                                </div>
                                <h3 class="font-semibold text-stone-800 dark:text-stone-100">{{ $com->titulo }}</h3>
                                <p class="whitespace-pre-line text-sm text-stone-600 dark:text-stone-300">{{ $com->contenido }}</p>
                                <p class="text-xs text-stone-500">{{ $com->autor?->name }} · {{ $com->created_at->formatoFechaHora() }}</p>
                            </div>
                            <div class="flex shrink-0 gap-1">
                                <x-gestion.boton variante="fantasma" tamano="sm" :icono="$com->activo ? 'ojo-tachado' : 'ojo'" wire:click="toggleActivo({{ $com->id }})">
                                    {{ $com->activo ? 'Desactivar' : 'Activar' }}
                                </x-gestion.boton>
                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $com->id }})"
                                    wire:confirm="¿Eliminar el comunicado {{ $com->titulo }}? Esta acción no se puede deshacer.">Eliminar</x-gestion.boton>
                            </div>
                        </div>
                    </x-gestion.tarjeta>
                @endforeach
            </div>
            <div>{{ $comunicados->links() }}</div>
        @endif
    </div>
</div>
