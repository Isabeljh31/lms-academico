@props([
    'id',
    'opciones' => [],
    'etiqueta' => null,
    'seleccionado' => null,
    'placeholder' => 'Selecciona una opción',
])

{{--
    Desplegable propio (reemplazo accesible de <select>) para las pantallas de
    Profesor y Supervisor. Se enlaza con wire:model (o wire:model.live) igual
    que x-gestion.control. "opciones" es [valor => etiqueta].

    - Teclado: flechas, Inicio/Fin, Enter o Espacio para elegir, Esc para
      cerrar (sin cerrar el modal que lo contiene) y Tab para salir.
    - La lista se posiciona con "fixed" a partir del botón, así no queda
      cortada dentro de un modal con scroll; se recoloca al hacer scroll y se
      abre hacia arriba si no hay espacio abajo.
    - ARIA: botón con aria-haspopup="listbox" y lista role="listbox" con
      aria-activedescendant.
    - "seleccionado" es el valor actual de la propiedad Livewire: la etiqueta
      visible sale renderizada desde el servidor y Alpine solo la actualiza
      cuando cambia el valor.
--}}
@php
    $modelo = $attributes->wire('model');
    $campo = $modelo->value();
    $esLive = $modelo->hasModifier('live');
    $invalido = $campo && $errors->has($campo);
    $textoSeleccionado = $opciones[(string) $seleccionado] ?? $placeholder;
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;

    $boton = $esAdmin
        ? 'bg-white text-stone-800 dark:bg-stone-900 dark:text-stone-100 '.($invalido ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-400' : 'border-arena-medio focus:border-menta-base focus:ring-menta-base dark:border-stone-600')
        : 'bg-white text-texto dark:bg-azul-superficie-oscura dark:text-slate-100 '.($invalido ? 'border-red-500 focus:border-red-500 focus:ring-red-500 dark:border-red-400' : 'border-celeste focus:border-azul-oscuro focus:ring-azul-oscuro dark:border-azul-borde-oscuro');
    $panel = $esAdmin ? 'border-arena-medio bg-white dark:border-stone-700 dark:bg-stone-800' : 'border-celeste bg-white dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura';
    $opcionActiva = $esAdmin ? 'bg-arena-claro dark:bg-stone-700' : 'bg-celeste dark:bg-azul-borde-oscuro';
    $opcionElegida = $esAdmin ? 'font-semibold text-menta-oscuro dark:text-menta-base' : 'font-semibold text-azul-oscuro dark:text-azul-fondo';
    $opcionNormal = $esAdmin ? 'text-stone-700 dark:text-stone-200' : 'text-slate-700 dark:text-slate-200';
    $colorIcono = $esAdmin ? 'text-stone-400 dark:text-stone-500' : 'text-slate-400 dark:text-slate-500';
@endphp

<div class="relative"
    x-data="{
        abierto: false,
        @if ($esLive) valor: @entangle($campo).live, @else valor: @entangle($campo), @endif
        activo: null,
        estilo: {},
        opciones() { return Array.from(this.$root.querySelectorAll('[role=option]')) },
        etiquetaActual() {
            const clave = String(this.valor ?? '');
            const opcion = this.opciones().find(o => o.dataset.valor === clave);
            return opcion ? opcion.dataset.etiqueta : @js($placeholder);
        },
        abrir() {
            if (this.abierto) return;
            const valores = this.opciones().map(o => o.dataset.valor);
            this.activo = valores.includes(String(this.valor ?? '')) ? String(this.valor) : (valores[0] ?? null);
            this.abierto = true;
            this.posicionar();
            this.$nextTick(() => { this.posicionar(); this.$refs.lista.focus(); this.mostrarActivo(); });
        },
        cerrar(enfocar = true) {
            this.abierto = false;
            if (enfocar) this.$refs.boton.focus();
        },
        elegir(valor) {
            if (valor === null) return;
            this.valor = valor;
            this.cerrar();
        },
        mover(paso) {
            const valores = this.opciones().map(o => o.dataset.valor);
            const indice = Math.min(Math.max(valores.indexOf(this.activo) + paso, 0), valores.length - 1);
            this.activo = valores[indice] ?? null;
            this.mostrarActivo();
        },
        extremo(alFinal) {
            const valores = this.opciones().map(o => o.dataset.valor);
            this.activo = (alFinal ? valores[valores.length - 1] : valores[0]) ?? null;
            this.mostrarActivo();
        },
        mostrarActivo() {
            this.opciones().find(o => o.dataset.valor === this.activo)?.scrollIntoView({ block: 'nearest' });
        },
        posicionar() {
            const caja = this.$refs.boton.getBoundingClientRect();
            const alto = Math.min(this.$refs.lista.scrollHeight || 240, 240);
            const abajo = window.innerHeight - caja.bottom;
            const haciaArriba = abajo < alto + 8 && caja.top > abajo;
            this.estilo = {
                left: `${caja.left}px`,
                width: `${caja.width}px`,
                top: haciaArriba ? 'auto' : `${caja.bottom + 4}px`,
                bottom: haciaArriba ? `${window.innerHeight - caja.top + 4}px` : 'auto',
            };
        },
    }"
    x-on:keydown.escape="if (abierto) { $event.stopPropagation(); cerrar(); }"
    x-on:click.outside="if (abierto) cerrar(false)"
    x-on:resize.window="abierto && posicionar()"
    x-on:scroll.window.capture="abierto && posicionar()">

    <button type="button" id="{{ $id }}" x-ref="boton"
        aria-haspopup="listbox" aria-controls="{{ $id }}-lista" x-bind:aria-expanded="abierto"
        @if ($invalido) aria-invalid="true" @endif
        x-on:click="abierto ? cerrar() : abrir()"
        x-on:keydown.arrow-down.prevent="abrir()"
        x-on:keydown.arrow-up.prevent="abrir()"
        {{ $attributes->whereDoesntStartWith('wire:model')->merge(['class' => "flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm shadow-sm focus:outline-none focus:ring-1 $boton"]) }}>
        {{-- Texto renderizado en el servidor; x-text lo actualiza al cambiar "valor". Las opciones se buscan
             desde $root (no con $refs) porque este x-text se evalúa antes de que la lista registre su x-ref. --}}
        <span id="{{ $id }}-texto" class="truncate" x-text="etiquetaActual()">{{ $textoSeleccionado }}</span>
        <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 transition-transform {{ $colorIcono }}" x-bind:class="abierto && 'rotate-180'" />
    </button>

    <ul x-ref="lista" id="{{ $id }}-lista" role="listbox" tabindex="-1"
        @if ($etiqueta) aria-label="{{ $etiqueta }}" @endif
        x-show="abierto" style="display: none"
        x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-bind:style="estilo"
        x-bind:aria-activedescendant="activo !== null ? '{{ $id }}-opcion-' + activo : null"
        x-on:keydown.arrow-down.prevent="mover(1)"
        x-on:keydown.arrow-up.prevent="mover(-1)"
        x-on:keydown.home.prevent="extremo(false)"
        x-on:keydown.end.prevent="extremo(true)"
        x-on:keydown.enter.prevent="elegir(activo)"
        x-on:keydown.space.prevent="elegir(activo)"
        x-on:keydown.tab="cerrar(false)"
        class="fixed z-[60] max-h-60 overflow-y-auto rounded-lg border py-1 shadow-lg focus:outline-none {{ $panel }}">
        @foreach ($opciones as $valor => $texto)
            <li id="{{ $id }}-opcion-{{ $valor }}" role="option" wire:key="{{ $id }}-opcion-{{ $valor }}"
                data-valor="{{ $valor }}" data-etiqueta="{{ $texto }}"
                x-bind:aria-selected="String(valor ?? '') === @js((string) $valor) ? 'true' : 'false'"
                x-on:click="elegir(@js((string) $valor))"
                x-on:mousemove="activo = @js((string) $valor)"
                x-bind:class="{
                    '{{ $opcionActiva }}': activo === @js((string) $valor),
                    '{{ $opcionElegida }}': String(valor ?? '') === @js((string) $valor),
                    '{{ $opcionNormal }}': String(valor ?? '') !== @js((string) $valor),
                }"
                class="flex cursor-pointer select-none items-center justify-between gap-3 px-3 py-2 text-sm">
                <span>{{ $texto }}</span>
                {{-- El x-show va en un span HTML: dentro de <x-icono> la directiva @js no se compila. --}}
                <span class="shrink-0" x-show="String(valor ?? '') === @js((string) $valor)" @if ((string) $valor !== (string) $seleccionado) style="display: none" @endif>
                    <x-icono nombre="check" class="h-4 w-4" />
                </span>
            </li>
        @endforeach
    </ul>
</div>
