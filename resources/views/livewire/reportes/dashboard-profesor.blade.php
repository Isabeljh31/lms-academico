@php
    $etiquetas = $filas->map(fn ($f) => $f['curso']->nombre)->values();
    $promedios = $filas->map(fn ($f) => $f['promedio'] ?? 0)->values();
    $tasas = $filas->map(fn ($f) => $f['tasa_entrega'])->values();
@endphp

<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Dashboard de cursos</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Promedio y tasa de entrega por curso.</p>
        </div>

        @if ($filas->isEmpty())
            <p class="text-stone-500 dark:text-stone-400">No hay cursos para mostrar todavía.</p>
        @else
            <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4"
                wire:ignore
                x-data="{
                    init() {
                        new window.Chart(this.$refs.grafico, {
                            type: 'bar',
                            data: {
                                labels: @js($etiquetas),
                                datasets: [
                                    { label: 'Promedio de notas (0-20)', data: @js($promedios), backgroundColor: '#7FB3AE' },
                                    { label: 'Tasa de entrega (%)', data: @js($tasas), backgroundColor: '#B2967D' },
                                ],
                            },
                            options: { responsive: true },
                        });
                    },
                }"
            >
                <canvas x-ref="grafico" height="90"></canvas>
            </div>

            <div class="space-y-2">
                @foreach ($filas as $fila)
                    <div class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-lg border border-arena-medio dark:border-stone-700 px-4 py-3 text-sm" wire:key="curso-{{ $fila['curso']->id }}">
                        <div>
                            <span class="font-medium text-stone-800 dark:text-stone-100">{{ $fila['curso']->nombre }}</span>
                            <span class="text-stone-400 text-xs"> · {{ $fila['inscritos'] }} inscritos</span>
                        </div>
                        <div class="flex items-center gap-4 text-xs text-stone-500 dark:text-stone-400">
                            <span>Promedio: {{ $fila['promedio'] ?? '—' }}</span>
                            <span>Entrega: {{ $fila['tasa_entrega'] }}%</span>
                            <a href="{{ route('cursos.show', $fila['curso']) }}" wire:navigate class="text-menta-oscuro dark:text-menta-base hover:underline">Ver curso</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
