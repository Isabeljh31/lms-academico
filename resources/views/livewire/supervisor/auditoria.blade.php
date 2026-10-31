@php
    $tonos = [
        'cambio_nota' => 'bg-arena-claro text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo',
        'baja_estudiante' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        'desactivacion_cuenta' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
        'creacion_cuenta' => 'bg-exito-base/15 text-exito-oscuro',
        'reactivacion_cuenta' => 'bg-exito-base/15 text-exito-oscuro',
        'matricula_manual' => 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base',
        'matricula_api' => 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base',
    ];
@endphp

<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Bitácora de auditoría" icono="escudo"
            descripcion="Registro inmutable de cambios de notas y acciones administrativas. Solo lectura y solo para el Supervisor.">
            <x-gestion.boton variante="secundario" icono="descargar" wire:click="exportarExcel">Exportar a Excel</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.tarjeta titulo="Filtros">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-gestion.campo etiqueta="Tipo de evento" nombre="filtroTipo">
                    <x-gestion.control tipo="select" id="filtroTipo" wire:model.live="filtroTipo">
                        <option value="">Todos</option>
                        @foreach ($tiposEvento as $clave => $etiqueta)
                            <option value="{{ $clave }}">{{ $etiqueta }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Curso" nombre="filtroCursoId">
                    <x-gestion.control tipo="select" id="filtroCursoId" wire:model.live="filtroCursoId">
                        <option value="">Todos</option>
                        @foreach ($cursos as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Ejecutado por" nombre="filtroUsuario">
                    <x-gestion.control id="filtroUsuario" type="search" wire:model.live.debounce.300ms="filtroUsuario" placeholder="Nombre o correo" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Desde" nombre="filtroDesde">
                    <x-gestion.control id="filtroDesde" type="date" wire:model.live="filtroDesde" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Hasta" nombre="filtroHasta">
                    <x-gestion.control id="filtroHasta" type="date" wire:model.live="filtroHasta" />
                </x-gestion.campo>
            </div>
            <div class="mt-3 flex justify-end">
                <x-gestion.boton variante="fantasma" tamano="sm" icono="refrescar" wire:click="limpiarFiltros">Limpiar filtros</x-gestion.boton>
            </div>
        </x-gestion.tarjeta>

        <x-gestion.tarjeta :relleno="false" :titulo="$registros->total().' registros'">
            @if ($registros->isEmpty())
                <div class="p-4"><x-gestion.vacio icono="escudo" titulo="No hay registros con estos filtros" /></div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[900px] text-sm">
                        <thead class="bg-arena-claro/50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-900/40 dark:text-stone-400">
                            <tr>
                                <th class="px-4 py-3">Fecha</th>
                                <th class="px-3 py-3">Ejecutado por</th>
                                <th class="px-3 py-3">Evento</th>
                                <th class="px-3 py-3">Detalle</th>
                                <th class="px-3 py-3">Antes → después</th>
                                <th class="px-3 py-3">IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 align-top dark:divide-stone-700">
                            @foreach ($registros as $r)
                                <tr wire:key="audit-{{ $r->id }}">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-stone-500">{{ $r->created_at?->formatoFechaHora(true) }}</td>
                                    <td class="px-3 py-3">
                                        <p class="font-medium text-stone-800 dark:text-stone-100">{{ $r->ejecutadoPor?->name ?? 'Sistema externo (API)' }}</p>
                                        @if ($r->ejecutadoPor) <p class="text-xs text-stone-500">{{ $r->ejecutadoPor->email }}</p> @endif
                                    </td>
                                    <td class="px-3 py-3">
                                        <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium {{ $tonos[$r->tipo_evento] ?? 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200' }}">{{ $r->etiquetaTipo() }}</span>
                                    </td>
                                    <td class="max-w-sm px-3 py-3 text-xs text-stone-600 dark:text-stone-300">
                                        @if ($r->descripcion) <p>{{ $r->descripcion }}</p> @endif
                                        @if ($r->usuarioAfectado) <p class="text-stone-500">Afectado: {{ $r->usuarioAfectado->name }}</p> @endif
                                        @if ($r->curso) <p class="text-stone-500">Curso: {{ $r->curso->nombre }}</p> @endif
                                        @if ($r->motivo) <p class="text-stone-500">Motivo: {{ $r->motivo }}</p> @endif
                                    </td>
                                    <td class="max-w-xs break-words px-3 py-3 font-mono text-xs text-stone-600 dark:text-stone-300">
                                        @if ($r->valor_anterior !== null || $r->valor_nuevo !== null)
                                            {{ \Illuminate\Support\Str::limit($r->valor_anterior ?? '—', 120) }} → {{ \Illuminate\Support\Str::limit($r->valor_nuevo ?? '—', 120) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="px-3 py-3 font-mono text-xs text-stone-500">{{ $r->ip }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-arena-medio px-4 py-3 dark:border-stone-700">{{ $registros->links() }}</div>
            @endif
        </x-gestion.tarjeta>
    </div>
</div>
