@props([
    'texto',
])

{{--
    Ayuda contextual: icono «i» que muestra el texto al pasar el cursor, al
    enfocarlo con el teclado o al tocarlo. Reemplaza los párrafos explicativos.
--}}
<span class="relative inline-flex align-middle" x-data="{ abierto: false }"
    x-on:mouseenter="abierto = true" x-on:mouseleave="abierto = false" x-on:keydown.escape.stop="abierto = false">
    <button type="button" aria-label="Ayuda: {{ $texto }}"
        x-on:focus="abierto = true" x-on:blur="abierto = false" x-on:click="abierto = ! abierto"
        class="rounded-full text-slate-400 hover:text-azul-oscuro focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:text-slate-500 dark:hover:text-azul-fondo">
        <x-icono nombre="info" class="h-4 w-4" />
    </button>
    <span x-show="abierto" style="display: none" role="tooltip" aria-hidden="true"
        class="absolute bottom-full left-1/2 z-30 mb-2 w-max max-w-[16rem] -translate-x-1/2 rounded-lg bg-imperial px-2.5 py-1.5 text-left text-xs font-medium normal-case leading-snug tracking-normal text-white shadow-lg">
        {{ $texto }}
    </span>
</span>
