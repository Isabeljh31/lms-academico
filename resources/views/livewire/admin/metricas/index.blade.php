<div class="py-6 px-4 sm:px-6 lg:px-8 space-y-6 max-w-7xl mx-auto">
    
    <div>
        <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Métricas y Analítica Avanzada</h1>
        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Dashboard de engagement y riesgo de abandono (Fase 3)</p>
    </div>

    <!-- Engagement Overview -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-stone-800 rounded-xl p-5 border border-stone-200 dark:border-stone-700">
            <h3 class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider mb-2">Total Estudiantes</h3>
            <p class="text-3xl font-bold text-stone-800 dark:text-stone-100">{{ $totalEstudiantes }}</p>
            <p class="text-xs text-stone-500 mt-1">Activos en la plataforma</p>
        </div>
        <div class="bg-white dark:bg-stone-800 rounded-xl p-5 border border-stone-200 dark:border-stone-700">
            <h3 class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider mb-2">Prom. Inscripciones</h3>
            <p class="text-3xl font-bold text-stone-800 dark:text-stone-100">{{ $promedioInscripciones }}</p>
            <p class="text-xs text-stone-500 mt-1">Cursos por estudiante</p>
        </div>
        <div class="bg-white dark:bg-stone-800 rounded-xl p-5 border border-stone-200 dark:border-stone-700">
            <h3 class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider mb-2">Interacciones Mensajería</h3>
            <p class="text-3xl font-bold text-stone-800 dark:text-stone-100">{{ $totalMensajes }}</p>
            <p class="text-xs text-stone-500 mt-1">Mensajes enviados totales</p>
        </div>
        <div class="bg-white dark:bg-stone-800 rounded-xl p-5 border border-stone-200 dark:border-stone-700">
            <h3 class="text-xs font-semibold text-stone-500 dark:text-stone-400 uppercase tracking-wider mb-2">Uso Asistente IA</h3>
            <p class="text-3xl font-bold text-stone-800 dark:text-stone-100">{{ $totalInteraccionesIA }}</p>
            <p class="text-xs text-stone-500 mt-1">Conversaciones iniciadas</p>
        </div>
    </div>

    <!-- Riesgo de Abandono -->
    <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-stone-200 dark:border-stone-700 bg-stone-50 dark:bg-stone-800/50">
            <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100 flex items-center gap-2">
                ⚠️ Alertas de Riesgo de Abandono
                <span class="inline-flex rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-semibold text-red-800 dark:text-red-400">
                    {{ $estudiantesEnRiesgo->count() }}
                </span>
            </h2>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">Estudiantes con asistencia crítica (< 50%) o notas bajas (< 10).</p>
        </div>
        
        @if($estudiantesEnRiesgo->isEmpty())
            <div class="p-8 text-center text-stone-500 dark:text-stone-400">
                <span class="text-4xl">🎉</span>
                <p class="mt-4">¡Excelente! No se detectan estudiantes en riesgo en este momento.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-stone-50 dark:bg-stone-800/50 border-b border-stone-200 dark:border-stone-700">
                        <tr>
                            <th class="px-6 py-3 font-semibold text-stone-600 dark:text-stone-300">Estudiante</th>
                            <th class="px-6 py-3 font-semibold text-stone-600 dark:text-stone-300">Nivel de Riesgo</th>
                            <th class="px-6 py-3 font-semibold text-stone-600 dark:text-stone-300">Motivos</th>
                            <th class="px-6 py-3 font-semibold text-stone-600 dark:text-stone-300 text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100 dark:divide-stone-700">
                        @foreach($estudiantesEnRiesgo as $riesgo)
                            <tr class="hover:bg-stone-50/50 dark:hover:bg-stone-700/20">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-stone-800 dark:text-stone-200">{{ $riesgo['estudiante']->name }}</div>
                                    <div class="text-xs text-stone-500">{{ $riesgo['estudiante']->email }}</div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold 
                                        {{ $riesgo['nivel'] === 'Alto' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                                        <span class="h-1.5 w-1.5 rounded-full {{ $riesgo['nivel'] === 'Alto' ? 'bg-red-500' : 'bg-amber-500' }}"></span>
                                        {{ $riesgo['nivel'] }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-stone-600 dark:text-stone-400 space-y-1">
                                    @foreach($riesgo['motivos'] as $motivo)
                                        <p>• {{ $motivo }}</p>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="mailto:{{ $riesgo['estudiante']->email }}" 
                                        class="text-xs font-semibold text-terracota-base hover:text-terracota-oscuro dark:text-terracota-claro transition">
                                        Contactar →
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
