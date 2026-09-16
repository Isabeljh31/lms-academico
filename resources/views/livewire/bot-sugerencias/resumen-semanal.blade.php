<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Resumen semanal del asistente</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">
                Propuestas generadas para tus estudiantes en los últimos 7 días. Solo ellos pueden aceptarlas o rechazarlas.
            </p>
        </div>

        <div class="grid grid-cols-3 gap-4">
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                <p class="text-2xl font-bold text-stone-800 dark:text-stone-100">{{ $pendientes->count() }}</p>
                <p class="text-xs text-stone-500 dark:text-stone-400">Pendientes</p>
            </div>
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                <p class="text-2xl font-bold text-exito-oscuro">{{ $aprobadas->count() }}</p>
                <p class="text-xs text-stone-500 dark:text-stone-400">Aceptadas</p>
            </div>
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 text-center">
                <p class="text-2xl font-bold text-terracota-oscuro">{{ $rechazadas->count() }}</p>
                <p class="text-xs text-stone-500 dark:text-stone-400">Rechazadas</p>
            </div>
        </div>

        <div class="space-y-2">
            @forelse ($pendientes->concat($aprobadas)->concat($rechazadas)->sortByDesc('created_at') as $propuesta)
                <div class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-lg border border-arena-medio dark:border-stone-700 px-4 py-3 text-sm" wire:key="resumen-{{ $propuesta->id }}">
                    <div>
                        <span class="font-medium text-stone-800 dark:text-stone-100">{{ $propuesta->usuario->name }}</span>
                        <span class="text-stone-500 dark:text-stone-400"> — {{ $propuesta->datos_propuesta['tarea_titulo'] ?? '' }}</span>
                    </div>
                    <x-badge-estado :estado="$propuesta->estado" />
                </div>
            @empty
                <p class="text-stone-500 dark:text-stone-400">Sin actividad del asistente esta semana.</p>
            @endforelse
        </div>
    </div>
</div>
