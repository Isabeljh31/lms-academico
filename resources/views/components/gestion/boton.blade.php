@props([
    'variante' => 'primario',
    'href' => null,
    'icono' => null,
    'tamano' => 'md',
])

{{-- Botón de las pantallas de Profesor y Supervisor. Con href se renderiza como enlace. --}}
@php
    $variantes = [
        'primario' => 'bg-menta-oscuro text-white hover:bg-menta-oscuro/90 focus:ring-menta-base dark:bg-menta-base dark:text-stone-900',
        'secundario' => 'border border-arena-medio bg-white text-stone-700 hover:bg-arena-claro focus:ring-menta-base dark:border-stone-600 dark:bg-stone-800 dark:text-stone-200 dark:hover:bg-stone-700',
        'peligro' => 'bg-terracota-oscuro text-white hover:bg-terracota-oscuro/90 focus:ring-terracota-base',
        'fantasma' => 'text-stone-600 hover:bg-arena-claro focus:ring-menta-base dark:text-stone-300 dark:hover:bg-stone-700',
        'fantasma-peligro' => 'text-terracota-oscuro hover:bg-terracota-fondo/40 focus:ring-terracota-base dark:text-terracota-fondo dark:hover:bg-stone-700',
    ];
    $tamanos = [
        'sm' => 'px-2.5 py-1.5 text-xs gap-1.5',
        'md' => 'px-4 py-2 text-sm gap-2',
    ];
    $clases = 'inline-flex items-center justify-center rounded-lg font-semibold transition focus:outline-none focus:ring-2 focus:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50 '
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
