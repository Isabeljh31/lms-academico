<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Métricas globales</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Visión general de cuentas, cursos y uso del asistente.</p>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Usuarios registrados</h2>
            <div class="grid grid-cols-3 gap-4">
                @foreach ($usuariosPorRol as $rol => $total)
                    <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                        <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $total }}</p>
                        <p class="text-xs text-stone-500 dark:text-stone-400 capitalize">{{ $rol }}</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Cursos e inscripciones</h2>
            <div class="grid grid-cols-2 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $totalCursos }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Cursos publicados</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $totalInscripciones }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Inscripciones totales</p>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Cursos con más atraso</h2>
            @if ($cursosConMasAtraso->isEmpty())
                <p class="text-sm text-stone-500 dark:text-stone-400">Ningún curso tiene entregas atrasadas por ahora.</p>
            @else
                <div class="space-y-2">
                    @foreach ($cursosConMasAtraso as $fila)
                        <div class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-lg border border-arena-medio dark:border-stone-700 px-4 py-3 text-sm">
                            <span class="text-stone-800 dark:text-stone-100">{{ $fila['curso']->nombre }}</span>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-terracota-fondo text-terracota-oscuro">
                                {{ $fila['atrasos'] }} entregas atrasadas
                            </span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div>
            <h2 class="text-sm font-semibold uppercase text-stone-400 mb-3">Uso del bot de sugerencias</h2>
            <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['conversaciones'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Conversaciones</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['mensajes'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Mensajes</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-stone-800 dark:text-stone-100">{{ $usoBot['propuestas_pendientes'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Propuestas pendientes</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-exito-oscuro">{{ $usoBot['propuestas_aprobadas'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Aceptadas</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                    <p class="text-xl font-bold text-terracota-oscuro">{{ $usoBot['propuestas_rechazadas'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Rechazadas</p>
                </div>
            </div>
        </div>

        {{-- =====================================================
             ERS RF-54/RF-65: Métricas del Esquema de Evaluación
             ===================================================== --}}
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-stone-700 dark:text-stone-300">
                📋 Esquema de Evaluación (Rubros)
            </h2>

            {{-- KPIs del esquema --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black text-menta-oscuro dark:text-menta-base">{{ $esquema['cursos_con_esquema'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Cursos con esquema</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black {{ $esquema['cursos_sin_esquema'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-stone-400' }}">
                        {{ $esquema['cursos_sin_esquema'] }}
                    </p>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Sin esquema definido</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black text-stone-800 dark:text-stone-100">{{ $esquema['total_notas_rubros'] }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Notas por rubro</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black {{ ($esquema['tasa_aprobacion_rubros'] ?? 0) >= 70 ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400' }}">
                        {{ $esquema['tasa_aprobacion_rubros'] !== null ? $esquema['tasa_aprobacion_rubros'].'%' : '—' }}
                    </p>
                    <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">Tasa aprobación (rubros)</p>
                </div>
            </div>

            {{-- Distribución de notas --}}
            @if($esquema['total_notas_rubros'] > 0)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-5">
                <h3 class="text-sm font-semibold text-stone-700 dark:text-stone-300 mb-4">Distribución de notas por rango</h3>
                @php $total = $esquema['total_notas_rubros']; @endphp
                @foreach([
                    ['rango' => '18–20 (Excelente)', 'key' => '18-20', 'color' => 'bg-green-500'],
                    ['rango' => '14–17 (Bueno)',     'key' => '14-17', 'color' => 'bg-blue-500'],
                    ['rango' => '10–13 (Regular)',   'key' => '10-13', 'color' => 'bg-amber-400'],
                    ['rango' => '0–9 (Reprobado)',   'key' => '0-9',   'color' => 'bg-red-500'],
                ] as $rango)
                @php
                    $count = $esquema['distribucion_notas'][$rango['key']] ?? 0;
                    $pct   = $total > 0 ? round($count / $total * 100) : 0;
                @endphp
                <div class="flex items-center gap-3 mb-2">
                    <span class="w-32 text-xs text-stone-600 dark:text-stone-400 shrink-0">{{ $rango['rango'] }}</span>
                    <div class="flex-1 bg-stone-100 dark:bg-stone-700 rounded-full h-3">
                        <div class="{{ $rango['color'] }} rounded-full h-3 transition-all"
                            style="width: {{ $pct }}%"></div>
                    </div>
                    <span class="text-xs font-semibold text-stone-700 dark:text-stone-300 w-12 text-right">{{ $count }} ({{ $pct }}%)</span>
                </div>
                @endforeach
            </div>
            @endif

            {{-- Auditoría reciente --}}
            @if($esquema['auditorias_recientes']->isNotEmpty())
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
                <div class="px-4 py-3 border-b border-stone-100 dark:border-stone-700">
                    <h3 class="text-sm font-semibold text-stone-700 dark:text-stone-300">
                        Actividad reciente de auditoría
                        <a href="{{ route('admin.auditoria') }}" wire:navigate
                            class="ml-2 text-xs text-menta-oscuro hover:underline font-normal">Ver todo →</a>
                    </h3>
                </div>
                <ul class="divide-y divide-stone-50 dark:divide-stone-700/50">
                    @foreach($esquema['auditorias_recientes'] as $log)
                    <li class="px-4 py-3 flex items-start gap-3 text-sm">
                        <span class="text-base shrink-0">
                            @if($log->tipo_evento === 'cambio_nota') 📝
                            @elseif($log->tipo_evento === 'creacion_cuenta') 👤
                            @elseif($log->tipo_evento === 'baja_estudiante') 🚫
                            @elseif($log->tipo_evento === 'matricula_api') 🔗
                            @else ⚙️ @endif
                        </span>
                        <div class="min-w-0">
                            <p class="text-stone-700 dark:text-stone-300 truncate">
                                <strong>{{ $log->ejecutadoPor?->name }}</strong> ·
                                {{ $log->descripcion ?? ucfirst(str_replace('_', ' ', $log->tipo_evento)) }}
                            </p>
                            <p class="text-xs text-stone-400 dark:text-stone-500 mt-0.5">
                                {{ $log->created_at->diffForHumans() }}
                                @if($log->curso) · {{ $log->curso->nombre }} @endif
                            </p>
                        </div>
                    </li>
                    @endforeach
                </ul>
            </div>
            @endif
        </div>

        {{-- Estado de cuentas (ERS RF-15) --}}
        <div class="space-y-4">
            <h2 class="text-base font-semibold text-stone-700 dark:text-stone-300">👥 Estado de Cuentas</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black text-green-600 dark:text-green-400">{{ $usuariosActivos }}</p>
                    <p class="text-xs text-stone-500 mt-1">Cuentas activas</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black text-red-500 dark:text-red-400">{{ $usuariosInactivos }}</p>
                    <p class="text-xs text-stone-500 mt-1">Desactivadas</p>
                </div>
                <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4 text-center">
                    <p class="text-2xl font-black text-amber-500 dark:text-amber-400">{{ $usuariosPrimerLogin }}</p>
                    <p class="text-xs text-stone-500 mt-1">1er login pendiente</p>
                </div>
            </div>
        </div>

    </div>
</div>
