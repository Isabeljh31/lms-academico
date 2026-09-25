<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6">

    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate
                class="text-xs text-stone-400 hover:text-stone-600 flex items-center gap-1 mb-1 transition">
                ← Volver al curso
            </a>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Asistencia</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">{{ $curso->nombre }} · {{ $totalSesiones }} sesiones programadas</p>
        </div>

        <div class="flex gap-2 flex-wrap">
            <div class="flex rounded-lg border border-stone-200 dark:border-stone-700 overflow-hidden text-sm">
                <button wire:click="$set('vista','sesiones')"
                    class="px-4 py-2 transition {{ $vista === 'sesiones' ? 'bg-menta-oscuro text-white' : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    📋 Sesiones
                </button>
                <button wire:click="$set('vista','historial')"
                    class="px-4 py-2 transition border-l border-stone-200 dark:border-stone-700 {{ $vista === 'historial' ? 'bg-menta-oscuro text-white' : 'bg-white dark:bg-stone-800 text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700' }}">
                    📊 Historial
                </button>
            </div>
            @if($vista === 'sesiones')
            <button wire:click="$set('mostrarFormSesion', true)"
                class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                + Nueva sesión
            </button>
            @endif
        </div>
    </div>

    @if(session('estado'))
    <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400">
        {{ session('estado') }}
    </div>
    @endif

    {{-- ============================== VISTA: SESIONES ============================== --}}
    @if($vista === 'sesiones')

    @if($mostrarFormSesion)
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5 space-y-4">
        <h2 class="text-sm font-semibold text-stone-700 dark:text-stone-300">Nueva sesión de clase</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-medium text-stone-600 dark:text-stone-400 mb-1">Título *</label>
                <input wire:model="sesionTitulo" type="text" placeholder="ej. Clase 1: Introducción"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                @error('sesionTitulo') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 dark:text-stone-400 mb-1">Fecha y hora *</label>
                <input wire:model="sesionFecha" type="datetime-local"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                @error('sesionFecha') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 dark:text-stone-400 mb-1">Duración (minutos)</label>
                <input wire:model="sesionDuracion" type="number" min="15" max="480"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-stone-600 dark:text-stone-400 mb-1">Descripción</label>
                <input wire:model="sesionDescripcion" type="text" placeholder="(opcional)"
                    class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
            </div>
        </div>
        <div class="flex justify-end gap-3">
            <button wire:click="$set('mostrarFormSesion', false)"
                class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-600 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                Cancelar
            </button>
            <button wire:click="crearSesion"
                class="rounded-lg bg-menta-oscuro px-4 py-2 text-sm font-semibold text-white hover:bg-menta-base transition">
                Crear sesión
            </button>
        </div>
    </div>
    @endif

    @if($sesiones->isEmpty())
    <div class="text-center py-16 text-stone-400 dark:text-stone-500">
        <p class="text-4xl mb-3">🗓️</p>
        <p class="text-sm">No hay sesiones programadas aún.</p>
    </div>
    @else
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        <table class="w-full text-sm">
            <thead class="border-b border-stone-100 dark:border-stone-700">
                <tr class="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                    <th class="px-4 py-3 text-left">#</th>
                    <th class="px-4 py-3 text-left">Sesión</th>
                    <th class="px-4 py-3 text-center">Fecha</th>
                    <th class="px-4 py-3 text-center">Duración</th>
                    <th class="px-4 py-3 text-center">Asistencias</th>
                    <th class="px-4 py-3 text-center">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                @foreach($sesiones as $sesion)
                <tr wire:key="sesion-{{ $sesion->id }}" class="hover:bg-stone-50 dark:hover:bg-stone-700/20 transition">
                    <td class="px-4 py-3 font-mono text-xs text-stone-400">{{ $sesion->numero_sesion ?? $loop->iteration }}</td>
                    <td class="px-4 py-3">
                        <p class="font-medium text-stone-800 dark:text-stone-200">{{ $sesion->titulo }}</p>
                        @if($sesion->descripcion)
                        <p class="text-xs text-stone-400 truncate mt-0.5">{{ $sesion->descripcion }}</p>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-stone-500 dark:text-stone-400">
                        {{ $sesion->fecha->format('d/m/Y H:i') }}
                    </td>
                    <td class="px-4 py-3 text-center text-xs text-stone-500 dark:text-stone-400">
                        {{ $sesion->duracion_minutos }} min
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="text-sm font-semibold {{ $sesion->asistencias_count > 0 ? 'text-menta-oscuro dark:text-menta-base' : 'text-stone-300 dark:text-stone-600' }}">
                            {{ $sesion->asistencias_count }} / {{ $estudiantesActivos }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button wire:click="abrirPaseLista({{ $sesion->id }})"
                                class="text-xs font-medium text-menta-oscuro hover:text-menta-base px-3 py-1.5 rounded-lg hover:bg-menta-fondo/20 dark:hover:bg-menta-base/10 transition">
                                ✓ Pase de lista
                            </button>
                            <button wire:click="eliminarSesion({{ $sesion->id }})"
                                wire:confirm="¿Eliminar esta sesión? Se perderán los registros de asistencia."
                                class="text-xs text-red-500 hover:text-red-700 px-3 py-1.5 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                Eliminar
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    @endif {{-- fin vista sesiones --}}

    {{-- ============================== VISTA: PASE DE LISTA ============================== --}}
    @if($vista === 'pase_lista' && $sesionActiva)
    <div class="space-y-4">
        <div class="flex items-center gap-3">
            <button wire:click="$set('vista','sesiones')"
                class="text-xs text-stone-400 hover:text-stone-700 flex items-center gap-1 transition">
                ← Volver a sesiones
            </button>
            <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100">
                Pase de lista — {{ $sesionActiva->titulo }}
            </h2>
            <span class="text-xs text-stone-400">{{ $sesionActiva->fecha->format('d/m/Y H:i') }}</span>
        </div>

        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
            <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700 flex gap-6 text-xs text-stone-500 dark:text-stone-400">
                <span>🟢 Presente</span><span>🟡 Tardanza</span><span>🔵 Justificado</span><span>🔴 Ausente</span>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b border-stone-100 dark:border-stone-700">
                    <tr class="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase">
                        <th class="px-4 py-3 text-left">Estudiante</th>
                        <th class="px-4 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @forelse($estudiantesConAsistencia as $fila)
                    @php $est = $fila['estudiante']; @endphp
                    <tr wire:key="pase-{{ $est->id }}" class="hover:bg-stone-50 dark:hover:bg-stone-700/20">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $est->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($est->name).'&background=2F5A78&color=fff&size=28' }}"
                                    class="h-7 w-7 rounded-full object-cover shrink-0" alt="">
                                <p class="font-medium text-stone-800 dark:text-stone-200">{{ $est->name }}</p>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-center gap-2">
                                @foreach(['presente' => ['🟢', 'green'], 'tardanza' => ['🟡', 'amber'], 'justificado' => ['🔵', 'blue'], 'ausente' => ['🔴', 'red']] as $est_val => [$icono, $color])
                                <button wire:click="marcarAsistencia({{ $est->id }}, '{{ $est_val }}')"
                                    class="w-9 h-9 rounded-lg text-sm transition
                                        {{ $fila['estado'] === $est_val
                                            ? 'ring-2 ring-offset-1 ring-'.$color.'-500 dark:ring-offset-stone-800 scale-110'
                                            : 'opacity-40 hover:opacity-80 hover:scale-105' }}">
                                    {{ $icono }}
                                </button>
                                @endforeach
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="2" class="px-4 py-10 text-center text-sm text-stone-400">No hay estudiantes inscritos activos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    {{-- ============================== VISTA: HISTORIAL ============================== --}}
    @if($vista === 'historial')
    <div class="space-y-4">
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4">
            <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-2">Seleccionar estudiante</label>
            <select wire:model.live="estudianteHistorialId"
                class="w-full sm:w-80 rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm">
                <option value="">-- Seleccionar estudiante --</option>
                @foreach($estudiantesParaFiltro as $est)
                <option value="{{ $est->id }}">{{ $est->name }}</option>
                @endforeach
            </select>
        </div>

        @if($estudianteFiltrado && $historialEstudiante->isNotEmpty())
        @php
            $presentes = $historialEstudiante->whereIn('estado', ['presente','tardanza'])->count();
            $total = $historialEstudiante->count();
            $pct = $total > 0 ? round($presentes / $total * 100) : 0;
        @endphp
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-semibold text-stone-800 dark:text-stone-100">{{ $estudianteFiltrado->name }}</h3>
                <span class="text-2xl font-black {{ $pct >= 70 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                    {{ $pct }}% asistencia
                </span>
            </div>
            <table class="w-full text-sm">
                <thead class="border-b border-stone-100 dark:border-stone-700">
                    <tr class="text-xs font-medium text-stone-500 dark:text-stone-400 uppercase">
                        <th class="pb-2 text-left">Sesión</th>
                        <th class="pb-2 text-center">Fecha</th>
                        <th class="pb-2 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @foreach($historialEstudiante as $a)
                    @php $badge = $a->badge(); @endphp
                    <tr>
                        <td class="py-2 text-stone-700 dark:text-stone-300">{{ $a->sesion?->titulo }}</td>
                        <td class="py-2 text-center text-xs text-stone-400">{{ $a->sesion?->fecha->format('d/m/Y') }}</td>
                        <td class="py-2 text-center">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badge['color'] }}">
                                {{ $badge['label'] }}
                            </span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @elseif($estudianteHistorialId)
        <p class="text-center text-sm text-stone-400 py-8">Sin registros de asistencia para este estudiante.</p>
        @endif
    </div>
    @endif

</div>
