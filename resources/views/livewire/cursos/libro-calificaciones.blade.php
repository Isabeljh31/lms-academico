<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- Encabezado --}}
    <div>
        <a href="{{ route('cursos.show', $curso) }}" wire:navigate
            class="text-xs font-semibold text-stone-400 hover:text-stone-700 dark:hover:text-stone-200 flex items-center gap-1 mb-2 w-fit transition">
            ← Volver a {{ $curso->nombre }}
        </a>
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 flex items-center gap-2">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-menta-oscuro/10 text-menta-oscuro">📊</span>
                    Libro de Calificaciones
                </h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Basado en el Esquema de Evaluación por Rubros · {{ $curso->nombre }}
                </p>
            </div>

            {{-- Selector de vista --}}
            <div class="flex rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden text-sm">
                <button wire:click="$set('vistaActiva','rubros')"
                    class="px-4 py-2 transition font-medium
                        {{ $vistaActiva === 'rubros'
                            ? 'bg-menta-oscuro text-white'
                            : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    📋 Por Rubros
                </button>
                <button wire:click="$set('vistaActiva','actividades')"
                    class="px-4 py-2 transition font-medium border-l border-stone-200 dark:border-stone-700
                        {{ $vistaActiva === 'actividades'
                            ? 'bg-menta-oscuro text-white'
                            : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    📝 Actividades
                </button>
            </div>
        </div>
    </div>

    {{-- Flash estado --}}
    @if(session('estado'))
    <div class="rounded-xl bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400 flex items-center justify-between">
        <span>{{ session('estado') }}</span>
        <button onclick="this.parentElement.remove()" class="text-green-400 hover:text-green-700">×</button>
    </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase text-stone-400 tracking-wide">Promedio General</span>
            <div class="text-3xl font-black mt-1 {{ ($promedioCurso ?? 0) >= $notaMinima ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $promedioCurso !== null ? $promedioCurso . ' / 20' : '—' }}
            </div>
            <span class="text-xs text-stone-400 block mt-1">Ponderado por rubros</span>
        </div>

        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase text-stone-400 tracking-wide">Tasa de Aprobación</span>
            <div class="text-3xl font-black text-stone-900 dark:text-stone-100 mt-1">{{ $tasaAprobacion }}%</div>
            <span class="text-xs text-stone-400 block mt-1">{{ $aprobados }} con nota ≥ {{ $notaMinima }}</span>
        </div>

        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase text-stone-400 tracking-wide">Nota Máxima</span>
            <div class="text-3xl font-black text-green-600 dark:text-green-400 mt-1">{{ $notaMax ?? '—' }}</div>
            <span class="text-xs text-stone-400 block mt-1">Mayor nota registrada</span>
        </div>

        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 shadow-sm">
            <span class="text-xs font-semibold uppercase text-stone-400 tracking-wide">Nota Mínima</span>
            <div class="text-3xl font-black text-red-600 dark:text-red-400 mt-1">{{ $notaMin ?? '—' }}</div>
            <span class="text-xs text-stone-400 block mt-1">Menor nota registrada</span>
        </div>
    </div>


    {{-- ============================================================ --}}
    {{-- VISTA: RUBROS (ERS RF-57 a RF-65) --}}
    {{-- ============================================================ --}}
    @if($vistaActiva === 'rubros')

        {{-- Alerta si el esquema no tiene rubros o los pesos no suman 100% --}}
        @if(!$esquema || $rubros->isEmpty())
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
            <span class="text-xl shrink-0">⚠️</span>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Esquema de Evaluación no configurado</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                    El Supervisor debe definir los rubros de evaluación antes de registrar notas.
                    @role('supervisor')
                    <a href="{{ route('cursos.esquema', $curso) }}" wire:navigate
                        class="underline font-medium hover:text-amber-900">Configurar esquema →</a>
                    @endrole
                </p>
            </div>
        </div>
        @elseif(!$pesosOk)
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-900/20 p-4 flex items-start gap-3">
            <span class="text-xl shrink-0">⚠️</span>
            <div>
                <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">Suma de pesos: {{ $sumaPesos }}% — debe ser exactamente 100%</p>
                <p class="text-xs text-amber-700 dark:text-amber-400 mt-1">
                    Las notas finales no son confiables hasta que el esquema sume 100%.
                    @role('supervisor')
                    <a href="{{ route('cursos.esquema', $curso) }}" wire:navigate class="underline font-medium">Corregir →</a>
                    @endrole
                </p>
            </div>
        </div>
        @endif

        @if($rubros->isNotEmpty())
        {{-- Rubros: chips de referencia --}}
        <div class="flex flex-wrap gap-2">
            @foreach($rubros as $rubro)
            <span class="inline-flex items-center gap-1.5 rounded-full border border-terracota-base/30 bg-terracota-base/10 px-3 py-1 text-xs font-medium text-terracota-oscuro dark:text-terracota-claro">
                {{ $rubro->nombre }}
                <span class="font-bold">{{ $rubro->peso }}%</span>
                @if($rubro->semana) · Sem. {{ $rubro->semana }} @endif
            </span>
            @endforeach
        </div>

        {{-- Tabla de notas por Rubro (ERS RF-59, RF-63, RF-64) --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-stone-50 dark:bg-stone-700/50 border-b border-stone-200 dark:border-stone-700">
                        <tr class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                            <th class="px-4 py-3 text-left sticky left-0 bg-stone-50 dark:bg-stone-700/50 min-w-[200px]">
                                Estudiante
                            </th>
                            @foreach($rubros as $rubro)
                            <th class="px-3 py-3 text-center min-w-[120px]">
                                <div class="truncate max-w-[110px] mx-auto font-semibold text-stone-700 dark:text-stone-200">
                                    {{ $rubro->nombre }}
                                </div>
                                <span class="text-[10px] font-normal text-stone-400">{{ $rubro->peso }}%</span>
                            </th>
                            @endforeach
                            <th class="px-4 py-3 text-center sticky right-0 bg-stone-100 dark:bg-stone-700 min-w-[120px] font-bold text-stone-900 dark:text-stone-100">
                                Nota Final
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-700/50">
                        @forelse($filasRubros as $fila)
                        @php $est = $fila['estudiante']; @endphp
                        {{-- ERS RF-64: fila roja si la nota final es inferior a la mínima --}}
                        <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20 transition
                            {{ $fila['nota_final'] !== null && !$fila['aprobado'] ? 'bg-red-50/50 dark:bg-red-900/10' : '' }}"
                            wire:key="rubro-fila-{{ $est->id }}">

                            {{-- Estudiante --}}
                            <td class="px-4 py-3 sticky left-0 bg-white dark:bg-stone-800 border-r border-stone-100 dark:border-stone-700">
                                <div class="flex items-center gap-2.5">
                                    <img src="{{ $est->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est->name).'&background=2F5A78&color=fff&size=32' }}"
                                        class="h-7 w-7 rounded-full object-cover shrink-0" alt="">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-stone-900 dark:text-stone-100 text-xs truncate">{{ $est->name }}</div>
                                        <div class="text-[10px] text-stone-400 truncate">{{ $est->email }}</div>
                                    </div>
                                    @if($fila['nota_final'] !== null && !$fila['aprobado'])
                                    <span class="shrink-0 ml-1 text-xs" title="Por debajo de {{ $notaMinima }}">🔴</span>
                                    @endif
                                </div>
                            </td>

                            {{-- Celdas por rubro --}}
                            @foreach($rubros as $rubro)
                            @php $nota = $fila['notas'][$rubro->id] ?? null; @endphp
                            <td class="px-3 py-3 text-center">
                                <button wire:click="abrirModalNota({{ $rubro->id }}, {{ $est->id }})"
                                    class="w-16 rounded-lg text-sm font-semibold px-2 py-1.5 transition
                                        {{ $nota?->nota !== null
                                            ? ($nota->nota >= $notaMinima
                                                ? 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400 hover:ring-2 hover:ring-green-400'
                                                : 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 hover:ring-2 hover:ring-red-400')
                                            : 'bg-stone-100 dark:bg-stone-700 text-stone-400 hover:ring-2 hover:ring-terracota-base' }}">
                                    {{ $nota?->nota !== null ? number_format($nota->nota, 1) : '—' }}
                                </button>
                            </td>
                            @endforeach

                            {{-- Nota Final (ERS RF-58, RF-64) --}}
                            <td class="px-4 py-3 text-center sticky right-0 bg-white dark:bg-stone-800">
                                @if($fila['nota_final'] !== null)
                                <span class="inline-flex items-center justify-center w-16 rounded-full px-3 py-1 text-sm font-black
                                    {{ $fila['aprobado']
                                        ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                        : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                                    {{ number_format($fila['nota_final'], 1) }}
                                </span>
                                @else
                                <span class="text-stone-300 dark:text-stone-600 text-sm">—</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="{{ $rubros->count() + 2 }}" class="px-4 py-12 text-center text-sm text-stone-400">
                                No hay estudiantes inscritos en este curso.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Leyenda de colores --}}
            <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700 flex flex-wrap gap-4 text-xs text-stone-500 dark:text-stone-400">
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded bg-green-100 dark:bg-green-900/20 inline-block"></span>
                    Aprobado (≥ {{ $notaMinima }})
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded bg-red-100 dark:bg-red-900/20 inline-block"></span>
                    Reprobado (< {{ $notaMinima }})
                </span>
                <span class="flex items-center gap-1.5">
                    <span class="w-4 h-4 rounded bg-stone-100 dark:bg-stone-700 inline-block"></span>
                    Sin nota registrada
                </span>
                <span class="ml-auto italic">Haz clic en cualquier celda para registrar o editar la nota</span>
            </div>
        </div>
        @endif

    @endif {{-- fin vista rubros --}}


    {{-- ============================================================ --}}
    {{-- VISTA: ACTIVIDADES (tareas + quizzes — datos de práctica) --}}
    {{-- ============================================================ --}}
    @if($vistaActiva === 'actividades')

    <div class="rounded-xl border border-stone-200 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50 px-4 py-3">
        <p class="text-xs text-stone-500 dark:text-stone-400">
            ℹ️ Esta sección muestra las notas de <strong>tareas y quizzes</strong> como referencia de práctica.
            La <strong>nota oficial del curso</strong> se calcula desde los rubros del Esquema de Evaluación.
        </p>
    </div>

    @if($tareas->isEmpty() && $quizzes->isEmpty())
    <div class="text-center py-16 text-stone-400 dark:text-stone-500 text-sm">
        No hay tareas ni quizzes asignados en este curso.
    </div>
    @else
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-stone-50 dark:bg-stone-700/50 border-b border-stone-200 dark:border-stone-700">
                    <tr class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                        <th class="px-4 py-3 text-left sticky left-0 bg-stone-50 dark:bg-stone-700/50 min-w-[200px]">Estudiante</th>
                        @foreach($tareas as $t)
                        <th class="px-3 py-3 text-center min-w-[120px]">
                            <div class="truncate max-w-[110px] mx-auto">{{ $t->titulo }}</div>
                            <span class="text-[10px] font-normal text-stone-400">Peso: {{ $t->peso_nota }}</span>
                        </th>
                        @endforeach
                        @foreach($quizzes as $q)
                        <th class="px-3 py-3 text-center min-w-[120px]">
                            <div class="truncate max-w-[110px] mx-auto">Quiz: {{ $q->tema }}</div>
                            <span class="text-[10px] font-normal text-stone-400">20 pts</span>
                        </th>
                        @endforeach
                        <th class="px-4 py-3 text-center sticky right-0 bg-stone-100 dark:bg-stone-700 min-w-[110px]">Prom. Final</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 dark:divide-stone-700/50">
                    @forelse($filasActividades as $fila)
                    @php $est = $fila['estudiante']; @endphp
                    <tr class="hover:bg-stone-50 dark:hover:bg-stone-700/20 transition" wire:key="act-fila-{{ $est->id }}">
                        <td class="px-4 py-3 sticky left-0 bg-white dark:bg-stone-800 border-r border-stone-100 dark:border-stone-700">
                            <div class="flex items-center gap-2.5">
                                <img src="{{ $est->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est->name).'&background=2F5A78&color=fff&size=32' }}"
                                    class="h-7 w-7 rounded-full object-cover shrink-0" alt="">
                                <div class="min-w-0">
                                    <div class="font-semibold text-stone-900 dark:text-stone-100 text-xs truncate">{{ $est->name }}</div>
                                    <div class="text-[10px] text-stone-400 truncate">{{ $est->email }}</div>
                                </div>
                            </div>
                        </td>

                        @foreach($tareas as $t)
                        @php $celda = $fila['notasTareas'][$t->id] ?? null; @endphp
                        <td class="px-3 py-3 text-center">
                            @if($celda && $celda['nota'] !== null)
                            <button wire:click="abrirEdicionNota({{ $est->id }}, {{ $t->id }})"
                                class="inline-flex items-center justify-center font-bold px-2 py-1 rounded-lg text-xs transition
                                    {{ $celda['nota'] >= 10.5
                                        ? 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400 hover:ring-2 hover:ring-green-400'
                                        : 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400 hover:ring-2 hover:ring-red-400' }}">
                                {{ number_format($celda['nota'], 1) }}
                            </button>
                            @else
                            <button wire:click="abrirEdicionNota({{ $est->id }}, {{ $t->id }})"
                                class="inline-flex items-center justify-center px-2 py-1 rounded-lg text-xs text-stone-400 bg-stone-100 dark:bg-stone-700 hover:ring-2 hover:ring-terracota-base transition">
                                @if(($celda['estado'] ?? '') === 'pendiente') ⏳ @elseif(($celda['estado'] ?? '') === 'sin_entrega') — @else {{ $celda['estado'] ?? '—' }} @endif
                            </button>
                            @endif
                        </td>
                        @endforeach

                        @foreach($quizzes as $q)
                        @php $celdaQ = $fila['notasQuizzes'][$q->id] ?? null; @endphp
                        <td class="px-3 py-3 text-center">
                            @if($celdaQ && $celdaQ['nota'] !== null)
                            <span class="inline-flex items-center justify-center font-bold px-2 py-1 rounded-lg text-xs
                                {{ $celdaQ['nota'] >= 10.5
                                    ? 'bg-green-100 dark:bg-green-900/20 text-green-700 dark:text-green-400'
                                    : 'bg-red-100 dark:bg-red-900/20 text-red-700 dark:text-red-400' }}">
                                {{ number_format($celdaQ['nota'], 1) }}
                            </span>
                            @else
                            <span class="text-stone-300 dark:text-stone-600 text-xs">—</span>
                            @endif
                        </td>
                        @endforeach

                        <td class="px-4 py-3 text-center sticky right-0 bg-white dark:bg-stone-800">
                            @if($fila['promedioFinal'] !== null)
                            <span class="inline-flex items-center justify-center w-12 rounded-full px-2 py-1 text-sm font-black
                                {{ $fila['promedioFinal'] >= 10.5
                                    ? 'bg-green-100 text-green-700 dark:bg-green-900/20 dark:text-green-400'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/20 dark:text-red-400' }}">
                                {{ $fila['promedioFinal'] }}
                            </span>
                            @else
                            <span class="text-stone-300 dark:text-stone-600">—</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="99" class="px-4 py-12 text-center text-sm text-stone-400">Sin estudiantes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
    @endif {{-- fin vista actividades --}}


    {{-- ============================================================ --}}
    {{-- MODAL: Nota de Rubro --}}
    {{-- ============================================================ --}}
    @if($mostrarModalNota)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-1">Registrar Nota de Rubro</h3>
            <p class="text-xs text-stone-500 dark:text-stone-400 mb-4">Escala 0 – 20. Este cambio quedará registrado en la bitácora de auditoría.</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Nota *</label>
                    <input wire:model="notaInput" type="number" min="0" max="20" step="0.1"
                        placeholder="ej. 14.5"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-terracota-base focus:border-terracota-base">
                    @error('notaInput') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Motivo del ajuste</label>
                    <input wire:model="motivoInput" type="text" placeholder="(opcional)"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-terracota-base focus:border-terracota-base">
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModalNota', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="guardarNotaRubro"
                    class="rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
                    Guardar nota
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- ============================================================ --}}
    {{-- MODAL: Nota de Tarea (actividades legacy) --}}
    {{-- ============================================================ --}}
    @if($mostrarModalTarea)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-sm p-6">
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-4">Calificar Actividad</h3>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Nota (0–20) *</label>
                    <input wire:model="notaTareaInput" type="number" min="0" max="20" step="0.1"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base focus:border-menta-base">
                    @error('notaTareaInput') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Retroalimentación</label>
                    <textarea wire:model="feedbackTareaInput" rows="3"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-menta-base focus:border-menta-base"></textarea>
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModalTarea', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="guardarNotaTarea"
                    class="rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
                    Guardar
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
