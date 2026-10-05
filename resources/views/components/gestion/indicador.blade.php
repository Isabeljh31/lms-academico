@props([
    'etiqueta',
    'valor',
    'icono' => 'grafico',
    'tono' => 'menta',
    'detalle' => null,
])

{{-- Tarjeta de indicador (KPI) para dashboards de Profesor y Supervisor. --}}
@php
    $tonos = [
        'menta' => 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base',
        'terracota' => 'bg-arena-claro text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo',
        'cafe' => 'bg-arena-medio/60 text-stone-700 dark:bg-stone-700 dark:text-stone-200',
        'exito' => 'bg-exito-base/15 text-exito-oscuro dark:bg-stone-700 dark:text-green-300',
        'alerta' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'rounded-xl border border-arena-medio bg-white p-4 shadow-sm dark:border-stone-700 dark:bg-stone-800']) }}>
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tonos[$tono] ?? $tonos['menta'] }}">
            <x-icono :nombre="$icono" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide text-stone-500 dark:text-stone-400">{{ $etiqueta }}</p>
            <p class="text-2xl font-bold text-stone-900 dark:text-stone-100">{{ $valor }}</p>
        </div>
    </div>
    @if ($detalle)
        <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">{{ $detalle }}</p>
    @endif
</div>
