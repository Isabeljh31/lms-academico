@props([
    'href',
    'texto' => 'Volver al curso',
])

{{-- Enlace de retroceso encima del encabezado de una pantalla (mismo estilo que Actividades). --}}
@php
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;
    $color = $esAdmin ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo';
@endphp

<a href="{{ $href }}" wire:navigate
    {{ $attributes->merge(['class' => "inline-flex min-h-11 items-center gap-1 rounded-md text-sm font-medium hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-cielo {$color}"]) }}>
    <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> {{ $texto }}
</a>
