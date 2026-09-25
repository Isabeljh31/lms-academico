<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    {{-- Encabezado --}}
    <div>
        <a href="{{ route('cursos.show', $curso) }}" wire:navigate
            class="text-xs text-stone-400 hover:text-stone-700 dark:hover:text-stone-200 flex items-center gap-1 mb-2 w-fit transition">
            ← Volver a {{ $curso->nombre }}
        </a>
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Gestión de Matrícula</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">{{ $curso->nombre }}</p>
            </div>
        </div>
    </div>

    {{-- Flash --}}
    @if(session('estado'))
    <div class="rounded-xl {{ str_starts_with(session('estado'), '✅') ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700 text-green-700 dark:text-green-400' : 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-700 text-amber-700 dark:text-amber-400' }} border px-4 py-3 text-sm">
        {{ session('estado') }}
    </div>
    @endif

    {{-- KPIs --}}
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
            <p class="text-2xl font-black text-green-600 dark:text-green-400">{{ $totalActivos }}</p>
            <p class="text-xs text-stone-500 mt-1">Inscritos activos</p>
        </div>
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
            <p class="text-2xl font-black text-red-500 dark:text-red-400">{{ $totalBajas }}</p>
            <p class="text-xs text-stone-500 mt-1">Dados de baja</p>
        </div>
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
            <p class="text-2xl font-black text-purple-600 dark:text-purple-400">{{ $totalApi }}</p>
            <p class="text-xs text-stone-500 mt-1">Desde API externa</p>
        </div>
    </div>

    {{-- ============================================================ --}}
    {{-- AGREGAR ESTUDIANTE (ERS RF-26) --}}
    {{-- ============================================================ --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5">
        <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300 mb-4">
            ➕ Matricular estudiante manualmente
        </h2>

        <div class="flex gap-3 items-start flex-wrap">
            <div class="relative flex-1 min-w-[260px]">
                <input wire:model.live.debounce.350ms="busquedaEstudiante"
                    type="text"
                    placeholder="Buscar estudiante por nombre o correo..."
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-terracota-base focus:border-terracota-base">

                {{-- Dropdown de resultados --}}
                @if($estudiantesBusqueda->isNotEmpty())
                <div class="absolute top-full left-0 right-0 z-20 mt-1 bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-600 shadow-lg overflow-hidden">
                    @foreach($estudiantesBusqueda as $est)
                    <button wire:click="seleccionarEstudiante({{ $est->id }})"
                        class="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-stone-50 dark:hover:bg-stone-700 transition border-b border-stone-100 dark:border-stone-700/50 last:border-0">
                        <img src="{{ $est->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est->name).'&background=2F5A78&color=fff&size=32' }}"
                            class="h-8 w-8 rounded-full object-cover shrink-0" alt="">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-stone-800 dark:text-stone-200 truncate">{{ $est->name }}</p>
                            <p class="text-xs text-stone-400 dark:text-stone-500 truncate">{{ $est->email }}</p>
                        </div>
                    </button>
                    @endforeach
                </div>
                @elseif(strlen($busquedaEstudiante) >= 2 && $estudiantesBusqueda->isEmpty())
                <div class="absolute top-full left-0 right-0 z-20 mt-1 bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-600 shadow-lg px-4 py-3">
                    <p class="text-sm text-stone-400 dark:text-stone-500">Sin resultados. El estudiante ya puede estar inscrito o no tiene el rol adecuado.</p>
                </div>
                @endif
            </div>

            <button wire:click="matricularEstudiante"
                @if(!$estudianteSeleccionadoId) disabled @endif
                class="rounded-lg px-4 py-2 text-sm font-semibold transition
                    {{ $estudianteSeleccionadoId
                        ? 'bg-terracota-base text-white hover:bg-terracota-oscuro'
                        : 'bg-stone-200 dark:bg-stone-700 text-stone-400 cursor-not-allowed' }}">
                Matricular
            </button>
        </div>

        @if($estudianteSeleccionadoId)
        @php $estSel = \App\Models\User::find($estudianteSeleccionadoId); @endphp
        <div class="mt-3 flex items-center gap-2 rounded-lg bg-terracota-base/10 dark:bg-terracota-base/20 border border-terracota-base/30 px-3 py-2">
            <img src="{{ $estSel?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($estSel?->name ?? '').'&background=2F5A78&color=fff&size=28' }}"
                class="h-7 w-7 rounded-full object-cover shrink-0" alt="">
            <p class="text-sm text-terracota-oscuro dark:text-terracota-claro font-medium">
                ✓ Seleccionado: {{ $estSel?->name }}
            </p>
            <button wire:click="$set('estudianteSeleccionadoId', null)" class="ml-auto text-xs text-stone-400 hover:text-stone-600">✕</button>
        </div>
        @endif

        @error('estudianteSeleccionadoId')
        <p class="text-red-500 text-xs mt-2">{{ $message }}</p>
        @enderror

        <p class="text-xs text-stone-400 dark:text-stone-500 mt-3">
            💡 Al matricular manualmente se registrará el origen como "manual" y se enviará una notificación al estudiante.
        </p>
    </div>

    {{-- ============================================================ --}}
    {{-- LISTA DE INSCRITOS (ERS RF-29, RF-30) --}}
    {{-- ============================================================ --}}
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">

        {{-- Encabezado con filtros --}}
        <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700 flex items-center justify-between flex-wrap gap-3">
            <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Lista de matriculados</h2>
            <div class="flex rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden text-xs">
                @foreach(['activo' => 'Activos', 'baja' => 'Bajas', 'api' => 'API'] as $val => $label)
                <button wire:click="$set('filtroEstado', '{{ $val }}')"
                    class="px-3 py-1.5 transition {{ $filtroEstado === '{{ $val }}'
                        ? 'bg-terracota-base text-white'
                        : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}
                    {{ !$loop->first ? 'border-l border-stone-200 dark:border-stone-700' : '' }}">
                    {{ $label }}
                </button>
                @endforeach
            </div>
        </div>

        @if($inscripciones->isEmpty())
        <div class="py-14 text-center text-sm text-stone-400 dark:text-stone-500">
            @if($filtroEstado === 'baja') No hay estudiantes dados de baja en este curso.
            @elseif($filtroEstado === 'api') No hay matrículas desde API externa.
            @else No hay estudiantes inscritos actualmente.
            @endif
        </div>
        @else
        <table class="w-full text-sm">
            <thead class="border-b border-stone-100 dark:border-stone-700">
                <tr class="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left">Estudiante</th>
                    <th class="px-4 py-3 text-center">Origen</th>
                    <th class="px-4 py-3 text-center">Estado</th>
                    <th class="px-4 py-3 text-center">Inscrito</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                @foreach($inscripciones as $inscripcion)
                @php $est = $inscripcion->estudiante; @endphp
                <tr wire:key="insc-{{ $inscripcion->id }}"
                    class="hover:bg-stone-50 dark:hover:bg-stone-700/20 transition {{ !$inscripcion->estaActiva() ? 'opacity-60' : '' }}">

                    <td class="px-4 py-3">
                        <div class="flex items-center gap-3">
                            <img src="{{ $est?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est?->name ?? '?').'&background=2F5A78&color=fff&size=32' }}"
                                class="h-8 w-8 rounded-full object-cover shrink-0" alt="">
                            <div class="min-w-0">
                                <p class="font-medium text-stone-800 dark:text-stone-200 truncate">{{ $est?->name ?? '—' }}</p>
                                <p class="text-xs text-stone-400 dark:text-stone-500 truncate">{{ $est?->email ?? '—' }}</p>
                            </div>
                        </div>
                    </td>

                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                            {{ $inscripcion->origen === 'api'
                                ? 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300'
                                : 'bg-stone-100 text-stone-600 dark:bg-stone-700 dark:text-stone-300' }}">
                            {{ $inscripcion->origen === 'api' ? '🔗 API' : '✋ Manual' }}
                        </span>
                    </td>

                    <td class="px-4 py-3 text-center">
                        @if($inscripcion->estaActiva())
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Activo
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Baja
                            </span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-center text-xs text-stone-400 dark:text-stone-500">
                        {{ $inscripcion->fecha_inscripcion?->format('d/m/Y') ?? '—' }}
                        @if($inscripcion->fecha_baja)
                        <br><span class="text-red-400">Baja: {{ $inscripcion->fecha_baja->format('d/m/Y') }}</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-center">
                        @if($inscripcion->estaActiva())
                        <button wire:click="confirmarBaja({{ $inscripcion->id }})"
                            class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 font-medium px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                            Dar de baja
                        </button>
                        @else
                        <button wire:click="matricularEstudiante"
                            class="text-xs text-green-600 hover:text-green-800 font-medium px-3 py-1.5 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20 transition"
                            wire:click="seleccionarEstudiante({{ $est?->id }}), matricularEstudiante()">
                            Reactivar
                        </button>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700">
            {{ $inscripciones->links() }}
        </div>
        @endif
    </div>

    {{-- ============================================================ --}}
    {{-- MODAL: Confirmar baja (ERS RF-31) --}}
    {{-- ============================================================ --}}
    @if($mostrarModalBaja)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <div class="flex items-start gap-4 mb-4">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 shrink-0">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100">Dar de baja del curso</h3>
                    <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                        El estudiante perderá acceso al curso pero su <strong>historial académico se conservará</strong>.
                        Esta acción puede revertirse reactivando la matrícula.
                    </p>
                </div>
            </div>

            <div class="mb-4">
                <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">
                    Motivo de la baja <span class="text-stone-400 font-normal">(opcional)</span>
                </label>
                <textarea wire:model="motivoBaja" rows="2"
                    placeholder="Ej. Retiro voluntario, incumplimiento de normas..."
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-2 focus:ring-red-400 focus:border-red-400"></textarea>
            </div>

            <div class="p-3 rounded-lg bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 mb-5">
                <p class="text-xs text-amber-700 dark:text-amber-400">
                    ⚠️ Esta acción quedará registrada en la <strong>Bitácora de Auditoría</strong> con la hora y el usuario que la ejecutó.
                </p>
            </div>

            <div class="flex gap-3">
                <button wire:click="$set('mostrarModalBaja', false)"
                    class="flex-1 rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="ejecutarBaja"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Sí, dar de baja
                </button>
            </div>
        </div>
    </div>
    @endif

</div>
