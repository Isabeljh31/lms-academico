{{--
    Menú "⋯" de una semana (o de las prácticas de campo) del Profesor: editar y
    vaciar. Vaciar pide confirmación con el modal de confirmación. Recibe
    $modulo (null si la semana aún no tiene contenido), $numero (0 =
    prácticas) y $etiqueta. Teclado: Enter/Espacio abre, flechas recorren, Esc cierra.
--}}
@php
    $esPracticas = $numero === \App\Livewire\Cursos\Show::CLAVE_PRACTICAS;
    $claseItem = 'flex w-full items-center gap-2 px-3 py-2 text-left text-sm focus:outline-none';
@endphp
<div class="relative shrink-0" x-data="{ abierto: false }"
    x-on:keydown.escape.stop="if (abierto) { abierto = false; $refs.boton.focus() }"
    x-on:click.outside="abierto = false">
    <button type="button" x-ref="boton"
        x-on:click="abierto = ! abierto; if (abierto) $nextTick(() => $focus.within($refs.menu).first())"
        aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="abierto.toString()"
        aria-label="Más acciones de {{ $etiqueta }}"
        class="rounded-lg p-1.5 text-slate-500 transition hover:bg-celeste hover:text-slate-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:text-slate-300 dark:hover:bg-azul-borde-oscuro">
        <x-icono nombre="puntos" class="h-5 w-5 rotate-90" />
    </button>
    <div x-ref="menu" x-show="abierto" style="display: none" role="menu" aria-label="Acciones de {{ $etiqueta }}"
        x-on:keydown.arrow-down.prevent="$focus.within($refs.menu).wrap().next()"
        x-on:keydown.arrow-up.prevent="$focus.within($refs.menu).wrap().previous()"
        class="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-lg border border-celeste bg-white py-1 shadow-lg dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
        <button type="button" role="menuitem" x-on:click="abierto = false"
            wire:click="{{ $modulo ? 'abrirEditarModulo('.$modulo->id.')' : 'prepararSeccion('.$numero.')' }}"
            class="{{ $claseItem }} text-slate-700 hover:bg-celeste focus:bg-celeste dark:text-slate-200 dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
            <x-icono nombre="lapiz" class="h-4 w-4" /> {{ $esPracticas ? 'Editar prácticas de campo' : 'Editar semana' }}
        </button>
        @if ($modulo)
            <button type="button" role="menuitem" x-on:click="abierto = false" wire:click="confirmarVaciarModulo({{ $modulo->id }})"
                class="{{ $claseItem }} text-red-600 hover:bg-red-50 focus:bg-red-50 dark:text-red-400 dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
                <x-icono nombre="papelera" class="h-4 w-4" /> {{ $esPracticas ? 'Quitar prácticas de campo' : 'Vaciar semana' }}
            </button>
        @endif
    </div>
</div>
