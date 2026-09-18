@props(['disabled' => false])

@php
    // Compartido entre roles; Estudiante usa la paleta fría nueva y
    // Profesor/Administrador conservan la anterior — ver ARQUITECTURA.md §
    // nueva paleta de color.
    $paletaFria = auth()->check() && auth()->user()->hasRole('estudiante');
@endphp

<input @disabled($disabled) {{ $attributes->merge(['class' => $paletaFria
    ? 'border-slate-300 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-300 focus:border-azul-fondo dark:focus:border-azul-fondo focus:ring-azul-fondo dark:focus:ring-azul-fondo rounded-md shadow-sm'
    : 'border-stone-300 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-300 focus:border-menta-base dark:focus:border-menta-base focus:ring-menta-base dark:focus:ring-menta-base rounded-md shadow-sm']) }}>
