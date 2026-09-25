<div class="p-6 space-y-6">
    @if(session('estado'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-300">
            {{ session('estado') }}
        </div>
    @endif
    @if(session('advertencia'))
        <div class="rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 px-4 py-3 text-sm text-amber-700 dark:text-amber-300">
            ⚠️ {{ session('advertencia') }}
        </div>
    @endif

    {{-- Encabezado --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-stone-800 dark:text-stone-100">
                Esquema de Evaluación
            </h1>
            <p class="text-sm text-stone-500 mt-1">{{ $curso->nombre }}</p>
        </div>
        @role('supervisor')
        @if(!$curso->estaFinalizado())
        <button wire:click="abrirModalNuevo"
            class="inline-flex items-center gap-2 rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
            + Agregar Rubro
        </button>
        @endif
        @endrole
    </div>

    {{-- Estado del esquema --}}
    <div class="flex items-center gap-3 rounded-xl border {{ $pesosOk ? 'border-green-200 bg-green-50 dark:border-green-700 dark:bg-green-900/10' : 'border-amber-200 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/10' }} px-4 py-3">
        <span class="text-lg">{{ $pesosOk ? '✅' : '⚠️' }}</span>
        <div>
            <p class="text-sm font-medium {{ $pesosOk ? 'text-green-700 dark:text-green-300' : 'text-amber-700 dark:text-amber-300' }}">
                Suma de pesos: {{ $sumaPesos }}% {{ $pesosOk ? '— Esquema válido' : '— Debe ser exactamente 100%' }}
            </p>
            <p class="text-xs text-stone-500 dark:text-stone-400">
                Nota mínima aprobatoria: {{ $notaMinima }} / 20
            </p>
        </div>
    </div>

    {{-- Tabla de Rubros --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700">
            <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Rubros de Evaluación</h2>
        </div>
        @if($rubros->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-stone-400 dark:text-stone-500">
                Sin rubros definidos. Agrega el primero con el botón de arriba.
            </p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-stone-400 uppercase tracking-wider border-b border-stone-100 dark:border-stone-700">
                        <th class="px-4 py-2">#</th>
                        <th class="px-4 py-2">Nombre</th>
                        <th class="px-4 py-2">Semana</th>
                        <th class="px-4 py-2">Peso %</th>
                        @role('supervisor')
                        @if(!$curso->estaFinalizado())
                        <th class="px-4 py-2">Acciones</th>
                        @endif
                        @endrole
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @foreach($rubros as $rubro)
                    <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20">
                        <td class="px-4 py-3 text-stone-400">{{ $rubro->orden }}</td>
                        <td class="px-4 py-3 font-medium text-stone-800 dark:text-stone-200">{{ $rubro->nombre }}</td>
                        <td class="px-4 py-3 text-stone-600 dark:text-stone-400">
                            {{ $rubro->semana ? "Semana {$rubro->semana}" : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex items-center rounded-full bg-terracota-base/10 text-terracota-oscuro dark:text-terracota-claro px-2 py-0.5 text-xs font-semibold">
                                {{ $rubro->peso }}%
                            </span>
                        </td>
                        @role('supervisor')
                        @if(!$curso->estaFinalizado())
                        <td class="px-4 py-3 flex items-center gap-2">
                            <button wire:click="abrirModalEditar({{ $rubro->id }})"
                                class="text-xs text-stone-500 hover:text-stone-800 dark:hover:text-stone-200 transition">
                                Editar
                            </button>
                            <button wire:click="eliminarRubro({{ $rubro->id }})"
                                wire:confirm="¿Eliminar el rubro '{{ $rubro->nombre }}'? Esta acción no se puede deshacer."
                                class="text-xs text-red-500 hover:text-red-700 transition">
                                Eliminar
                            </button>
                        </td>
                        @endif
                        @endrole
                    </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Tabla de calificaciones por Rubro (ERS RF-57, RF-59, RF-63, RF-64) --}}
    @if($rubros->isNotEmpty() && $filas->isNotEmpty())
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700">
            <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Notas por Rubro</h2>
            <p class="text-xs text-stone-400 dark:text-stone-500 mt-0.5">
                Haz clic en una celda de nota para registrar o editar.
            </p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-medium text-stone-400 uppercase tracking-wider border-b border-stone-100 dark:border-stone-700">
                        <th class="px-4 py-2 sticky left-0 bg-white dark:bg-stone-800">Estudiante</th>
                        @foreach($rubros as $rubro)
                        <th class="px-3 py-2 text-center">
                            {{ $rubro->nombre }}<br>
                            <span class="normal-case font-normal text-stone-400">({{ $rubro->peso }}%)</span>
                        </th>
                        @endforeach
                        <th class="px-4 py-2 text-center font-bold">Nota Final</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @foreach($filas as $fila)
                    <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20">
                        <td class="px-4 py-3 font-medium text-stone-800 dark:text-stone-200 sticky left-0 bg-white dark:bg-stone-800">
                            {{ $fila['estudiante']->name }}
                        </td>
                        @foreach($rubros as $rubro)
                        <td class="px-3 py-3 text-center">
                            @php $nota = $fila['notas'][$rubro->id] ?? null; @endphp
                            <button wire:click="abrirModalNota({{ $rubro->id }}, {{ $fila['estudiante']->id }})"
                                class="w-14 rounded-lg {{ $nota?->nota !== null ? 'bg-menta-fondo dark:bg-menta-oscuro/20 text-menta-oscuro dark:text-menta-base font-semibold' : 'bg-stone-100 dark:bg-stone-700 text-stone-400' }} px-2 py-1 text-sm hover:ring-2 hover:ring-terracota-base transition">
                                {{ $nota?->nota !== null ? number_format($nota->nota, 1) : '—' }}
                            </button>
                        </td>
                        @endforeach
                        {{-- Nota Final (ERS RF-58): resaltar en rojo si < nota mínima (RF-64) --}}
                        <td class="px-4 py-3 text-center">
                            @if($fila['nota_final'] !== null)
                                <span class="inline-flex items-center rounded-full px-3 py-1 text-sm font-bold
                                    {{ $fila['aprobado'] ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                                    {{ number_format($fila['nota_final'], 1) }}
                                </span>
                            @else
                                <span class="text-stone-300 dark:text-stone-600">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- Modal: Rubro --}}
    @if($mostrarModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-4">
                {{ $rubroId ? 'Editar Rubro' : 'Nuevo Rubro de Evaluación' }}
            </h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Nombre *</label>
                    <input wire:model="nombre" type="text" placeholder="ej. Evaluación 1, Examen Final..."
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                    @error('nombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Semana</label>
                        <input wire:model="semana" type="number" min="1" max="52" placeholder="ej. 4"
                            class="w-full rounded-lg border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Peso % *</label>
                        <input wire:model="peso" type="number" min="0.01" max="100" step="0.01" placeholder="ej. 30"
                            class="w-full rounded-lg border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                        @error('peso') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModal', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="guardarRubro"
                    class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                    Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Nota de Rubro --}}
    @if($mostrarModalNota)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-4">Registrar Nota (0–20)</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Nota *</label>
                    <input wire:model="notaInput" type="number" min="0" max="20" step="0.1" placeholder="ej. 14.5"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                    @error('notaInput') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Motivo del ajuste</label>
                    <input wire:model="motivoInput" type="text" placeholder="(opcional)"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 bg-white dark:bg-stone-700 px-3 py-2 text-sm text-stone-900 dark:text-stone-100">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModalNota', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 transition">
                    Cancelar
                </button>
                <button wire:click="guardarNota"
                    class="rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
                    Guardar nota
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
