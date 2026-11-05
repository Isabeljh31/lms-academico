{{--
    Menú "⋯" de una semana en el detalle del
    curso del Profesor: las mismas acciones que livewire/cursos/_menu-semana
    (editar y vaciar), con área táctil de 44 px. Recibe $modulo (null si la
    semana aún no tiene contenido), $numero y $etiqueta.
    Teclado: Enter/Espacio abre, flechas recorren, Esc cierra.
--}}
@php
    $claseItem = 'flex min-h-11 w-full items-center gap-2 px-3 text-left text-sm focus:outline-none';
@endphp
<div class="relative shrink-0" x-data="{ abierto: false }"
    x-on:keydown.escape.stop="if (abierto) { abierto = false; $refs.boton.focus() }"
    x-on:click.outside="abierto = false">
    <button type="button" x-ref="boton"
        x-on:click="abierto = ! abierto; if (abierto) $nextTick(() => $focus.within($refs.menu).first())"
        aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="abierto.toString()"
        aria-label="Más acciones de {{ $etiqueta }}"
        class="flex h-11 w-11 items-center justify-center rounded-lg text-slate-500 transition hover:bg-celeste hover:text-texto focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo motion-reduce:transition-none">
        <x-icono nombre="puntos" class="h-5 w-5" />
    </button>
    <div x-ref="menu" x-show="abierto" style="display: none" role="menu" aria-label="Acciones de {{ $etiqueta }}"
        x-on:keydown.arrow-down.prevent="$focus.within($refs.menu).wrap().next()"
        x-on:keydown.arrow-up.prevent="$focus.within($refs.menu).wrap().previous()"
        class="absolute right-0 z-20 mt-1 w-56 overflow-hidden rounded-xl border border-celeste bg-white p-1 shadow-xl shadow-texto/15">
        <button type="button" role="menuitem" x-on:click="abierto = false"
            wire:click="{{ $modulo ? 'abrirEditarModulo('.$modulo->id.')' : 'prepararSeccion('.$numero.')' }}"
            class="{{ $claseItem }} rounded-lg text-texto hover:bg-fondo focus:bg-fondo">
            <x-icono nombre="lapiz" class="h-4 w-4 text-slate-500" /> Editar semana
        </button>
        @if ($modulo)
            <button type="button" role="menuitem" x-on:click="abierto = false" wire:click="confirmarVaciarModulo({{ $modulo->id }})"
                class="{{ $claseItem }} rounded-lg text-red-700 hover:bg-red-50 focus:bg-red-50">
                <x-icono nombre="papelera" class="h-4 w-4" /> Vaciar semana
            </button>
        @endif
    </div>
</div>
