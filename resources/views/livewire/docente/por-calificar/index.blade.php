<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Por calificar" icono="tarea"
            descripcion="Entregas pendientes de todos tus cursos, de la más antigua a la más reciente." />

        <x-gestion.alertas />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <x-gestion.indicador etiqueta="Pendientes" :valor="$totalPendientes" icono="reloj" tono="terracota" detalle="Entregas esperando nota" />
            <x-gestion.indicador etiqueta="Con más de 3 días" :valor="$conAtraso" icono="alerta" :tono="$conAtraso > 0 ? 'alerta' : 'exito'"
                :detalle="$conAtraso > 0 ? 'Requieren atención prioritaria' : 'Al día con las revisiones'" />
            <x-gestion.indicador etiqueta="Cursos a cargo" :valor="$cursos->count()" icono="libro" tono="cafe" />
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                <x-gestion.control id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Estudiante o tarea" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Curso" nombre="cursoFiltroId">
                <x-gestion.control tipo="select" id="cursoFiltroId" wire:model.live="cursoFiltroId">
                    <option value="">Todos los cursos</option>
                    @foreach ($cursos as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
        </div>

        <x-gestion.tarjeta :relleno="false">
            @if ($entregas->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="check-circulo" titulo="No hay entregas pendientes"
                        descripcion="Cuando tus estudiantes entreguen tareas aparecerán aquí." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-left text-sm">
                        <thead class="bg-arena-claro/50 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-3">Estudiante / grupo</th>
                                <th class="px-4 py-3">Curso</th>
                                <th class="px-4 py-3">Tarea</th>
                                <th class="px-4 py-3">Entregado</th>
                                <th class="px-4 py-3 text-right">Acción</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($entregas as $entrega)
                                @php $esUrgente = $entrega->updated_at->diffInDays(now()) >= 3; @endphp
                                <tr wire:key="ent-{{ $entrega->id }}" class="{{ $esUrgente ? 'bg-red-50/40 dark:bg-red-950/10' : '' }}">
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">{{ $entrega->estudiante?->name }}</p>
                                        @if ($entrega->grupo)
                                            <span class="inline-flex items-center gap-1 text-xs text-stone-500"><x-icono nombre="usuarios" class="h-3 w-3" /> {{ $entrega->grupo->nombre }}</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-stone-600 dark:text-stone-300">{{ $entrega->tarea->curso->nombre }}</td>
                                    <td class="px-4 py-3">
                                        <a href="{{ route('cursos.tareas.show', [$entrega->tarea->curso_id, $entrega->tarea_id]) }}" wire:navigate class="font-medium text-menta-oscuro hover:underline dark:text-menta-base">{{ $entrega->tarea->titulo }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-stone-500">
                                        {{ $entrega->updated_at->formatoFechaHora() }}
                                        @if ($esUrgente)
                                            <span class="mt-0.5 flex items-center gap-1 font-semibold text-red-600"><x-icono nombre="alerta" class="h-3 w-3" /> {{ $entrega->updated_at->diffInDays(now()) }} días</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <x-gestion.boton tamano="sm" icono="lapiz" wire:click="abrirCalificar({{ $entrega->id }})">Calificar</x-gestion.boton>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-arena-medio px-4 py-3 dark:border-stone-700">{{ $entregas->links() }}</div>
            @endif
        </x-gestion.tarjeta>
    </div>

    @if ($mostrarModalCalificar && $entregaSeleccionada)
        <x-gestion.modal titulo="Calificar entrega" cerrar="$set('mostrarModalCalificar', false)"
            descripcion="{{ $entregaSeleccionada->tarea->titulo }} · {{ $entregaSeleccionada->grupo ? $entregaSeleccionada->grupo->nombre.' (se aplica a todo el grupo)' : $entregaSeleccionada->estudiante?->name }}">
            <div class="space-y-2 rounded-lg bg-arena-claro/50 p-3 text-sm dark:bg-stone-900">
                @if ($entregaSeleccionada->archivo_url)
                    <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($entregaSeleccionada->archivo_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-menta-oscuro hover:underline"><x-icono nombre="material" class="h-4 w-4" /> Ver archivo</a>
                @endif
                @if ($entregaSeleccionada->enlace)
                    <a href="{{ $entregaSeleccionada->enlace }}" target="_blank" rel="noopener noreferrer" class="ml-3 inline-flex items-center gap-1 text-menta-oscuro hover:underline"><x-icono nombre="enlace" class="h-4 w-4" /> Ver enlace</a>
                @endif
                @if ($entregaSeleccionada->respuesta_texto)
                    <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">{{ $entregaSeleccionada->respuesta_texto }}</p>
                @endif
                @if ($entregaSeleccionada->comentario)
                    <p class="text-stone-500">Comentario: "{{ $entregaSeleccionada->comentario }}"</p>
                @endif
            </div>
            <x-gestion.campo etiqueta="Nota (0 a 20)" nombre="nota" requerido>
                <x-gestion.control id="nota" type="number" step="0.01" min="0" max="20" wire:model="nota" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Comentario para el estudiante" nombre="feedback">
                <x-gestion.control tipo="textarea" id="feedback" wire:model="feedback" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCalificar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardarCalificacion">Guardar calificación</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
