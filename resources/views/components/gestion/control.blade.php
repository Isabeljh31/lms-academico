@props([
    'tipo' => 'input',
])

{{-- input / select / textarea con el estilo de las pantallas de Profesor y Supervisor. --}}
@php
    $clases = 'block w-full rounded-lg border-arena-medio bg-white text-sm text-stone-800 shadow-sm focus:border-menta-base focus:ring-menta-base disabled:bg-arena-claro dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100';
@endphp

@if ($tipo === 'select')
    <select {{ $attributes->merge(['class' => $clases]) }}>{{ $slot }}</select>
@elseif ($tipo === 'textarea')
    <textarea {{ $attributes->merge(['class' => $clases, 'rows' => 3]) }}>{{ $slot }}</textarea>
@else
    <input {{ $attributes->merge(['type' => 'text', 'class' => $clases]) }}>
@endif
