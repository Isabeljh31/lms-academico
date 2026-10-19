@props([
    'etiqueta',
    'valor',
    'icono' => 'grafico',
    'tono' => 'menta',
    'detalle' => null,
])

{{-- Tarjeta de indicador (KPI) para dashboards de Profesor y Supervisor. --}}
@php
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;

    $tonos = $esAdmin ? [
        'menta' => 'bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base',
        'terracota' => 'bg-arena-claro text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo',
        'cafe' => 'bg-arena-medio/60 text-stone-700 dark:bg-stone-700 dark:text-stone-200',
        'exito' => 'bg-exito-base/15 text-exito-oscuro dark:bg-stone-700 dark:text-green-300',
        'alerta' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
    ] : [
        'menta' => 'bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo',
        'terracota' => 'bg-cielo/10 text-cielo dark:bg-cielo/20 dark:text-cielo',
        'cafe' => 'bg-celeste/60 text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200',
        'exito' => 'bg-exito-base/15 text-exito-oscuro dark:bg-azul-borde-oscuro dark:text-green-300',
        'alerta' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300',
    ];

    $borde = $esAdmin ? 'border-arena-medio dark:border-stone-700' : 'border-celeste dark:border-azul-borde-oscuro';
    $superficie = $esAdmin ? 'dark:bg-stone-800' : 'dark:bg-azul-superficie-oscura';
    $textoEtiqueta = $esAdmin ? 'text-stone-500 dark:text-stone-400' : 'text-slate-500 dark:text-slate-400';
    $textoValor = $esAdmin ? 'text-stone-900 dark:text-stone-100' : 'text-texto dark:text-slate-100';
@endphp

<div {{ $attributes->merge(['class' => "rounded-xl border $borde bg-white p-4 shadow-sm $superficie dark:shadow-none"]) }}>
    <div class="flex items-center gap-3">
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg {{ $tonos[$tono] ?? $tonos['menta'] }}">
            <x-icono :nombre="$icono" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="text-xs font-medium uppercase tracking-wide {{ $textoEtiqueta }}">{{ $etiqueta }}</p>
            <p class="text-2xl font-bold {{ $textoValor }}">{{ $valor }}</p>
        </div>
    </div>
    @if ($detalle)
        <p class="mt-2 text-xs {{ $textoEtiqueta }}">{{ $detalle }}</p>
    @endif
</div>
