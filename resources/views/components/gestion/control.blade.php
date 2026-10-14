@props([
    'tipo' => 'input',
])

{{-- input / select / textarea con el estilo de las pantallas de Profesor y Supervisor. --}}
{{-- ERS RNF-07: si el campo enlazado con wire:model tiene un error, se marca en rojo. --}}
@php
    $campo = $attributes->wire('model')->value();
    $invalido = $campo && $errors->has($campo);

    $clases = 'block w-full rounded-lg bg-white text-sm text-stone-800 shadow-sm disabled:bg-arena-claro dark:bg-stone-900 dark:text-stone-100 '
        .($invalido
            ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-400'
            : 'border-arena-medio focus:border-menta-base focus:ring-menta-base dark:border-stone-600');

    $atributos = $attributes->merge(['class' => $clases]);

    if ($invalido) {
        $atributos = $atributos->merge(['aria-invalid' => 'true']);
    }
@endphp

@if ($tipo === 'select')
    <select {{ $atributos }}>{{ $slot }}</select>
@elseif ($tipo === 'textarea')
    <textarea {{ $atributos->merge(['rows' => 3]) }}>{{ $slot }}</textarea>
@else
    <input {{ $atributos->merge(['type' => 'text']) }}>
@endif
