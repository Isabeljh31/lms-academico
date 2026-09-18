{{-- Ruta exclusiva del rol estudiante (middleware role:estudiante en routes/bot.php),
     por eso usa la paleta fría directo, sin ternario por rol. --}}
<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-texto dark:text-slate-100">Propuestas del asistente</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400">
                El bot detecta atrasos y sugiere recordatorios, pero nunca cambia tu calendario sin tu aprobación.
            </p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        @forelse ($propuestas as $propuesta)
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-5" wire:key="propuesta-{{ $propuesta->id }}">
                <div class="flex items-start gap-3">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/30 dark:text-lavanda-fondo">
                        <x-icono nombre="bombilla" class="h-5 w-5" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2">
                            <h3 class="font-medium text-texto dark:text-slate-100 truncate">{{ $propuesta->datos_propuesta['curso_nombre'] }}</h3>
                            <x-badge-estado :estado="$propuesta->estado" />
                        </div>

                        <p class="text-sm text-slate-600 dark:text-slate-300 mt-2">{{ $propuesta->datos_propuesta['motivo'] }}</p>
                        <p class="text-xs text-slate-400 mt-1">Tarea: {{ $propuesta->datos_propuesta['tarea_titulo'] }}</p>

                        @if ($propuesta->estado === 'pendiente')
                            <div class="mt-4 flex flex-wrap items-end gap-3">
                                <div>
                                    <x-input-label value="Recordatorio propuesto para…" />
                                    <input type="datetime-local"
                                        wire:model="fechasAjustadas.{{ $propuesta->id }}"
                                        value="{{ \Illuminate\Support\Carbon::parse($propuesta->datos_propuesta['nueva_fecha_recordatorio'])->format('Y-m-d\TH:i') }}"
                                        class="mt-1 rounded-md border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 focus:border-azul-fondo focus:ring-azul-fondo text-sm">
                                </div>
                                <x-primary-button wire:click="aceptar({{ $propuesta->id }})">Aceptar</x-primary-button>
                                <x-secondary-button wire:click="rechazar({{ $propuesta->id }})">Rechazar</x-secondary-button>
                            </div>
                            <p class="text-xs text-slate-400 mt-2">Puedes ajustar la fecha antes de aceptar.</p>
                        @else
                            <p class="text-xs text-slate-400 mt-3">
                                {{ $propuesta->estado === 'aprobado' ? 'Aceptada' : 'Rechazada' }} el {{ $propuesta->fecha_respuesta?->format('d/m/Y H:i') }}
                            </p>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro shadow-sm dark:shadow-none p-6 text-center text-slate-500 dark:text-slate-400 flex items-center justify-center gap-2">
                <x-icono nombre="check-circulo" class="h-5 w-5 text-verde-oscuro" />
                No tienes propuestas pendientes por ahora.
            </div>
        @endforelse
    </div>
</div>
