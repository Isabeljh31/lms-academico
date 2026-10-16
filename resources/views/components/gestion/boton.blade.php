@props([
    'variante' => 'primario',
    'href' => null,
    'icono' => null,
    'tamano' => 'md',
])

{{-- Botón de las pantallas de Profesor y Supervisor. Con href se renderiza como enlace. --}}
@php
    // Estudiante y Profesor comparten la paleta azul; Supervisor conserva su acento cálido.
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;

    $variantes = $esAdmin ? [
        'primario' => 'bg-menta-oscuro text-white hover:bg-menta-oscuro/90 focus:ring-menta-base dark:bg-menta-base dark:text-stone-900 shadow-sm hover:shadow-md hover:-translate-y-0.5',
        'secundario' => 'border border-arena-medio bg-white text-stone-700 hover:bg-arena-claro focus:ring-menta-base dark:border-stone-600 dark:bg-stone-800 dark:text-stone-200 dark:hover:bg-stone-700',
        'fantasma' => 'text-stone-600 hover:bg-arena-claro focus:ring-menta-base dark:text-stone-300 dark:hover:bg-stone-700',
    ] : [
        'primario' => 'bg-azul-oscuro text-white hover:bg-azul-oscuro/90 focus:ring-azul-oscuro dark:bg-azul-fondo dark:text-white shadow-sm hover:shadow-md hover:-translate-y-0.5',
        'secundario' => 'border border-celeste bg-white text-slate-700 hover:bg-celeste focus:ring-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 dark:hover:bg-azul-borde-oscuro',
        'fantasma' => 'text-slate-600 hover:bg-celeste focus:ring-azul-oscuro dark:text-slate-300 dark:hover:bg-azul-borde-oscuro',
    ];
    // Peligro no depende del rol: siempre rojo.
    $variantes['peligro'] = 'bg-red-600 text-white hover:bg-red-600/90 focus:ring-red-500';
    $variantes['fantasma-peligro'] = 'text-red-600 hover:bg-red-50 focus:ring-red-500 dark:text-red-400 dark:hover:bg-'.($esAdmin ? 'stone-700' : 'azul-borde-oscuro');

    $tamanos = [
        'sm' => 'px-2.5 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
    ];
    $clases = 'inline-flex items-center justify-center rounded-lg font-semibold transition active:scale-[0.97] focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 disabled:active:scale-100 '
        .($tamanos[$tamano] ?? $tamanos['md']).' '.($variantes[$variante] ?? $variantes['primario']);
    $claseIcono = $tamano === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4';
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $clases]) }}>
        @if ($icono) <x-icono :nombre="$icono" :class="$claseIcono" /> @endif
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->merge(['type' => 'button', 'class' => $clases]) }}>
        @if ($icono) <x-icono :nombre="$icono" :class="$claseIcono" /> @endif
        {{ $slot }}
    </button>
@endif
