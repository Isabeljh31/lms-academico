@php
    $tipoColores = [
        'cambio_nota'          => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        'baja_estudiante'      => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'creacion_cuenta'      => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        'desactivacion_cuenta' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        'matricula_manual'     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        'matricula_api'        => 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-300',
        'modificacion_esquema' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-300',
        'otro_admin'           => 'bg-stone-100 text-stone-700 dark:bg-stone-700 dark:text-stone-200',
    ];
@endphp

<div class="p-6 space-y-6">
    {{-- Título --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-800 dark:text-stone-100">Bitácora de Auditoría</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                Registro inmutable de acciones críticas del sistema (solo lectura).
            </p>
        </div>
        <span class="inline-flex items-center gap-1.5 rounded-full bg-stone-100 dark:bg-stone-800 px-3 py-1 text-xs font-medium text-stone-600 dark:text-stone-300">
            Solo Supervisor
        </span>
    </div>

    {{-- Filtros (ERS RF-98) --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4">
        <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300 mb-3">Filtros</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
            <div>
                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Tipo de evento</label>
                <select wire:model.live="filtroTipo"
                    class="w-full rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                    <option value="">Todos los tipos</option>
                    @foreach($tiposEvento as $clave => $etiqueta)
                        <option value="{{ $clave }}">{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Curso</label>
                <select wire:model.live="filtroCursoId"
                    class="w-full rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                    <option value="">Todos los cursos</option>
                    @foreach($cursos as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Desde</label>
                <input type="date" wire:model.live="filtroDesde"
                    class="w-full rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
            </div>
            <div>
                <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Hasta</label>
                <input type="date" wire:model.live="filtroHasta"
                    class="w-full rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
            </div>
        </div>
        <div class="mt-3">
            <label class="block text-xs text-stone-500 dark:text-stone-400 mb-1">Usuario ejecutor</label>
            <input type="text" wire:model.live.debounce.400ms="filtroUsuario" placeholder="Nombre o correo..."
                class="w-full sm:w-64 rounded-lg border border-stone-200 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
        </div>
    </div>

    {{-- Tabla de registros --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        @if($registros->isEmpty())
            <div class="py-16 text-center">
                <p class="text-stone-500 dark:text-stone-400 text-sm">Sin registros de auditoría para los filtros seleccionados.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-stone-200 dark:border-stone-700">
                        <tr class="text-left text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                            <th class="px-4 py-3">Fecha</th>
                            <th class="px-4 py-3">Evento</th>
                            <th class="px-4 py-3">Ejecutado por</th>
                            <th class="px-4 py-3">Afectado</th>
                            <th class="px-4 py-3">Curso</th>
                            <th class="px-4 py-3">Detalle</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-700/50">
                        @foreach($registros as $r)
                        <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/30 transition-colors">
                            <td class="px-4 py-3 text-xs text-stone-500 dark:text-stone-400 whitespace-nowrap">
                                {{ $r->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $tipoColores[$r->tipo_evento] ?? 'bg-stone-100 text-stone-700' }}">
                                    {{ $tiposEvento[$r->tipo_evento] ?? $r->tipo_evento }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-stone-700 dark:text-stone-300">
                                {{ $r->ejecutadoPor?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-stone-600 dark:text-stone-400">
                                {{ $r->usuarioAfectado?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-stone-600 dark:text-stone-400">
                                {{ $r->curso?->nombre ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-stone-600 dark:text-stone-400 max-w-xs">
                                @if($r->valor_anterior !== null || $r->valor_nuevo !== null)
                                    <span class="text-xs">
                                        <span class="line-through text-red-500">{{ $r->valor_anterior ?? '—' }}</span>
                                        → <span class="text-green-600 dark:text-green-400">{{ $r->valor_nuevo ?? '—' }}</span>
                                    </span>
                                @elseif($r->descripcion)
                                    <span class="text-xs truncate block max-w-xs" title="{{ $r->descripcion }}">
                                        {{ Str::limit($r->descripcion, 60) }}
                                    </span>
                                @else
                                    —
                                @endif
                                @if($r->motivo)
                                    <span class="block text-xs text-stone-400 dark:text-stone-500 italic">{{ $r->motivo }}</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700">
                {{ $registros->links() }}
            </div>
        @endif
    </div>
</div>
