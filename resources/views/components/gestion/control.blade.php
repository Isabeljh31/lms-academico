@props([
    'tipo' => 'input',
    'icono' => null,
])

{{-- input / select / textarea con el estilo de las pantallas de Profesor y Supervisor. --}}
{{-- ERS RNF-07: si el campo enlazado con wire:model tiene un error, se marca en rojo. --}}
@php
    $campo = $attributes->wire('model')->value();
    $invalido = $campo && $errors->has($campo);
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;

    $clases = $esAdmin
        ? 'block w-full rounded-lg bg-white text-sm text-stone-800 shadow-sm disabled:bg-arena-claro dark:bg-stone-900 dark:text-stone-100 '
            .($invalido
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-400'
                : 'border-arena-medio focus:border-menta-base focus:ring-menta-base dark:border-stone-600')
        : 'block w-full rounded-lg bg-white text-sm text-texto shadow-sm disabled:bg-celeste dark:bg-azul-superficie-oscura dark:text-slate-100 '
            .($invalido
                ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-400'
                : 'border-celeste focus:border-azul-oscuro focus:ring-azul-oscuro dark:border-azul-borde-oscuro');

    if ($icono) {
        $clases .= ' pl-9';
    }

    $atributos = $attributes->merge(['class' => $clases]);

    if ($invalido) {
        $atributos = $atributos->merge(['aria-invalid' => 'true']);
    }

    $colorIcono = $esAdmin ? 'text-stone-400 dark:text-stone-500' : 'text-slate-400 dark:text-slate-500';
@endphp

@if ($icono)
    <div class="relative">
        <x-icono :nombre="$icono" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 {{ $colorIcono }}" />
        @if ($tipo === 'select')
            <select {{ $atributos }}>{{ $slot }}</select>
        @else
            <input {{ $atributos->merge(['type' => 'text']) }}>
        @endif
    </div>
@elseif ($tipo === 'select')
    <select {{ $atributos }}>{{ $slot }}</select>
@elseif ($tipo === 'textarea')
    <textarea {{ $atributos->merge(['rows' => 3]) }}>{{ $slot }}</textarea>
@else
    <input {{ $atributos->merge(['type' => 'text']) }}>
@endif
