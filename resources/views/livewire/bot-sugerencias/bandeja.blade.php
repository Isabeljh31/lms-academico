<div class="py-8">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Propuestas del asistente</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">
                El bot detecta atrasos y sugiere recordatorios, pero nunca cambia tu calendario sin tu aprobación.
            </p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        @forelse ($propuestas as $propuesta)
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-5" wire:key="propuesta-{{ $propuesta->id }}">
                <div class="flex items-center justify-between">
                    <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $propuesta->datos_propuesta['curso_nombre'] }}</h3>
                    <x-badge-estado :estado="$propuesta->estado" />
                </div>

                <p class="text-sm text-stone-600 dark:text-stone-300 mt-2">{{ $propuesta->datos_propuesta['motivo'] }}</p>
                <p class="text-xs text-stone-400 mt-1">Tarea: {{ $propuesta->datos_propuesta['tarea_titulo'] }}</p>

                @if ($propuesta->estado === 'pendiente')
                    <div class="mt-4 flex flex-wrap items-end gap-3">
                        <div>
                            <x-input-label value="Recordatorio propuesto para…" />
                            <input type="datetime-local"
                                wire:model="fechasAjustadas.{{ $propuesta->id }}"
                                value="{{ \Illuminate\Support\Carbon::parse($propuesta->datos_propuesta['nueva_fecha_recordatorio'])->format('Y-m-d\TH:i') }}"
                                class="mt-1 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
                        </div>
                        <x-primary-button wire:click="aceptar({{ $propuesta->id }})">Aceptar</x-primary-button>
                        <x-secondary-button wire:click="rechazar({{ $propuesta->id }})">Rechazar</x-secondary-button>
                    </div>
                    <p class="text-xs text-stone-400 mt-2">Puedes ajustar la fecha antes de aceptar.</p>
                @else
                    <p class="text-xs text-stone-400 mt-3">
                        {{ $propuesta->estado === 'aprobado' ? 'Aceptada' : 'Rechazada' }} el {{ $propuesta->fecha_respuesta?->format('d/m/Y H:i') }}
                    </p>
                @endif
            </div>
        @empty
            <p class="text-stone-500 dark:text-stone-400">No tienes propuestas pendientes por ahora.</p>
        @endforelse
    </div>
</div>
