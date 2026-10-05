@props(['placeholder' => 'Elige fecha y hora'])

@php
    // Selector de fecha y hora del sistema (el mismo Flatpickr del Calendario),
    // en lugar del input nativo datetime-local. El valor que recibe Livewire
    // conserva el formato del input nativo (Y-m-d\TH:i); solo cambia lo que ve
    // el usuario ("28 de septiembre de 2026, 06:00 PM"). wire:ignore evita que
    // Livewire borre el campo visible que agrega Flatpickr al volver a renderizar.
    $paletaFria = ! auth()->check() || auth()->user()->hasRole('estudiante');
    $clase = $paletaFria
        ? 'block w-full rounded-lg border-slate-300 bg-white py-2 pl-3 pr-10 text-sm text-texto shadow-sm focus:border-azul-fondo focus:ring-azul-fondo dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200 cursor-pointer'
        : 'block w-full rounded-md border-stone-300 bg-white py-2 pl-3 pr-10 text-sm shadow-sm focus:border-menta-base focus:ring-menta-base dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 cursor-pointer';
    $icono = $paletaFria ? 'text-azul-oscuro/70 dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base';
@endphp

<div wire:ignore class="relative"
    x-data="{ listo: false }"
    x-init="window.cargarFlatpickr().then(() => {
        window.flatpickr($refs.entrada, {
            enableTime: true,
            time_24hr: false,
            minuteIncrement: 15,
            dateFormat: 'Y-m-d\\TH:i',
            altInput: true,
            altFormat: 'j \\d\\e F \\d\\e Y, h:i K',
            altInputClass: @js($clase),
            disableMobile: true,
            locale: window.flatpickr.l10ns.es,
        });
        listo = true;
    })">
    <input x-ref="entrada" type="text" x-bind:class="listo ? '' : 'opacity-0'"
        {{ $attributes->merge(['class' => $clase, 'placeholder' => $placeholder, 'autocomplete' => 'off']) }}>
    <x-icono nombre="calendario" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 {{ $icono }}" />
</div>
