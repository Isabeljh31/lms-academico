@props([
    'opciones' => [],
    'placeholder' => 'Elegir',
    'etiqueta' => '',
    'variante' => 'filtro',
])

@php
    /**
     * Desplegable propio del Profesor (sustituye al <select> nativo). Se enlaza con
     * wire:model[.live] como un select; las opciones son valor => etiqueta, o
     * valor => ['etiqueta' => ..., 'detalle' => ...] para un texto secundario en gris.
     * Usa la paleta .tema-profesor del layout.
     */
    $modelo = $attributes->wire('model');
    $propiedad = (string) $modelo->value();
    $id = 'desplegable-'.\Illuminate\Support\Str::slug($propiedad);

    $lista = collect($opciones)->map(fn ($opcion, $valor) => [
        'valor' => (string) $valor,
        'etiqueta' => (string) (is_array($opcion) ? $opcion['etiqueta'] : $opcion),
        'detalle' => is_array($opcion) ? ($opcion['detalle'] ?? null) : null,
    ])->values();

    $componente = app('livewire')->current();
    $valorActual = (string) ($componente ? data_get($componente, $propiedad) : '');
    $indiceActual = $lista->search(fn ($opcion) => $opcion['valor'] === $valorActual);
    $opcionActual = $indiceActual === false ? null : $lista[$indiceActual];
    $conBusqueda = $lista->count() > 8;
    $opcionesJs = $lista->map(fn ($opcion) => [
        ...$opcion,
        'texto' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($opcion['etiqueta'].' '.$opcion['detalle'])),
    ]);

    $estilosBoton = [
        'filtro' => 'h-[34px] w-full rounded-[10px] border border-transparent bg-[#F4F0E8] pl-2.5 pr-2 text-[12.5px]',
        'texto' => 'h-6 rounded-md border border-transparent bg-transparent pl-1 pr-0.5 text-xs',
        'borde' => 'h-[30px] rounded-lg border border-[#E0D8C8] bg-white pl-2.5 pr-1.5 text-[13px]',
        'pastilla' => 'h-10 rounded-[10px] border border-transparent bg-white pl-1 pr-2.5 text-[13px]',
    ][$variante] ?? '';
@endphp

<div
    x-data="{
        valor: @entangle($modelo),
        opciones: @js($opcionesJs),
        placeholder: @js($placeholder),
        abierto: false,
        activo: -1,
        busqueda: '',
        get indiceActual() { return this.opciones.findIndex(opcion => opcion.valor === String(this.valor ?? '')); },
        get actual() { return this.opciones[this.indiceActual] ?? null; },
        get visibles() {
            const texto = this.busqueda.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();
            return this.opciones.map((opcion, indice) => indice).filter(indice => texto === '' || this.opciones[indice].texto.includes(texto));
        },
        abrir() {
            if (this.abierto) return;
            this.busqueda = '';
            this.activo = this.indiceActual >= 0 ? this.indiceActual : 0;
            this.abierto = true;
            this.$nextTick(() => {
                this.posicionar();
                (this.$refs.busqueda ?? this.$refs.lista).focus({ preventScroll: true });
                this.mostrarActivo();
            });
        },
        cerrar(devolverFoco = true) {
            if (! this.abierto) return;
            this.abierto = false;
            if (devolverFoco) this.$refs.boton.focus({ preventScroll: true });
        },
        elegir(indice) {
            if (indice < 0 || ! this.opciones[indice]) return;
            if (this.opciones[indice].valor !== String(this.valor ?? '')) this.valor = this.opciones[indice].valor;
            this.cerrar();
        },
        mover(paso) {
            const visibles = this.visibles;
            if (visibles.length === 0) return;
            const posicion = visibles.indexOf(this.activo);
            const siguiente = posicion < 0 ? (paso > 0 ? 0 : visibles.length - 1) : Math.min(visibles.length - 1, Math.max(0, posicion + paso));
            this.activo = visibles[siguiente];
            this.mostrarActivo();
        },
        extremo(final) {
            const visibles = this.visibles;
            if (visibles.length === 0) return;
            this.activo = final ? visibles[visibles.length - 1] : visibles[0];
            this.mostrarActivo();
        },
        filtrar() {
            if (! this.visibles.includes(this.activo)) this.activo = this.visibles[0] ?? -1;
            this.$nextTick(() => this.posicionar());
        },
        mostrarActivo() {
            this.$nextTick(() => document.getElementById('{{ $id }}-opcion-' + this.activo)?.scrollIntoView({ block: 'nearest' }));
        },
        {{-- Posición fija bajo el botón (o encima si no cabe), para no quedar recortado por tarjetas con overflow oculto. --}}
        posicionar() {
            if (! this.abierto) return;
            const boton = this.$refs.boton.getBoundingClientRect();
            const panel = this.$refs.panel;
            panel.style.minWidth = Math.max(boton.width, 72) + 'px';
            const alto = panel.offsetHeight;
            const ancho = panel.offsetWidth;
            const espacioAbajo = window.innerHeight - boton.bottom - 12;
            const espacioArriba = boton.top - 12;
            const haciaArriba = alto > espacioAbajo && espacioArriba > espacioAbajo;
            panel.style.top = (haciaArriba ? boton.top - alto - 6 : boton.bottom + 6) + 'px';
            panel.style.left = Math.max(8, Math.min(boton.left, window.innerWidth - ancho - 8)) + 'px';
        },
    }"
    x-on:click.outside="cerrar(false)"
    x-on:keydown.escape="if (abierto) { $event.stopPropagation(); cerrar(); }"
    x-on:resize.window="posicionar()"
    x-on:scroll.window="posicionar()"
    {{ $attributes->whereDoesntStartWith('wire:model')->class('relative min-w-0') }}>

    <span id="{{ $id }}-etiqueta" class="sr-only">{{ $etiqueta }}</span>

    <button type="button" id="{{ $id }}-boton" x-ref="boton"
        aria-haspopup="listbox" aria-controls="{{ $id }}-lista" aria-expanded="false"
        aria-labelledby="{{ $id }}-etiqueta {{ $id }}-boton"
        x-bind:aria-expanded="abierto ? 'true' : 'false'"
        x-on:click="abierto ? cerrar() : abrir()"
        x-on:keydown.arrow-down.prevent="abrir()"
        x-on:keydown.arrow-up.prevent="abrir()"
        x-bind:class="abierto ? 'border-[var(--p700)] ring-[3px] ring-[rgba(19,110,97,0.16)]' : ''"
        class="flex min-w-0 items-center gap-1.5 text-left font-semibold text-[var(--p900)] transition-[border-color,box-shadow] duration-150 focus:outline-none focus-visible:border-[var(--p700)] focus-visible:ring-[3px] focus-visible:ring-[rgba(19,110,97,0.16)] motion-reduce:transition-none {{ $estilosBoton }}">
        <span class="min-w-0 flex-1 truncate">
            <span x-text="actual ? actual.etiqueta : placeholder">{{ $opcionActual['etiqueta'] ?? $placeholder }}</span><span x-show="actual?.detalle" class="font-medium text-[#6B6559]" @if (! ($opcionActual['detalle'] ?? null)) style="display: none" @endif x-text="actual?.detalle ? ' · ' + actual.detalle : ''">{{ ($opcionActual['detalle'] ?? null) ? ' · '.$opcionActual['detalle'] : '' }}</span>
        </span>
        <svg class="h-3.5 w-3.5 shrink-0 text-[var(--p700)] transition-transform duration-150 motion-reduce:transition-none" x-bind:class="abierto ? 'rotate-180' : ''"
            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
    </button>

    <div x-ref="panel" x-show="abierto" x-cloak
        x-transition:enter="transition-opacity duration-100 motion-reduce:transition-none" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        class="fixed z-50 flex max-h-72 w-max max-w-[360px] flex-col overflow-hidden rounded-xl border border-[#ECE6DA] bg-white shadow-[0_10px_30px_rgba(9,59,59,0.14),0_2px_6px_rgba(9,59,59,0.06)]"
        x-on:keydown.tab="cerrar(false)">
        @if ($conBusqueda)
            <div class="border-b border-[#F4F0E8] p-2">
                <label class="flex h-8 items-center gap-2 rounded-lg bg-[#F4F0E8] px-2.5 text-[#6B6559] focus-within:ring-[3px] focus-within:ring-[rgba(19,110,97,0.16)]">
                    <x-icono nombre="lupa" class="h-3.5 w-3.5 shrink-0" />
                    <span class="sr-only">Filtrar opciones</span>
                    <input type="search" x-ref="busqueda" x-model="busqueda" x-on:input="filtrar()" autocomplete="off" placeholder="Buscar…"
                        role="combobox" aria-autocomplete="list" aria-expanded="true" aria-controls="{{ $id }}-lista"
                        x-bind:aria-activedescendant="activo >= 0 ? '{{ $id }}-opcion-' + activo : null"
                        x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
                        x-on:keydown.enter.prevent="elegir(activo)"
                        class="w-full min-w-0 border-0 bg-transparent p-0 text-[13px] text-[var(--ink)] placeholder:text-[#6B6559] focus:outline-none focus:ring-0">
                </label>
            </div>
        @endif

        <ul id="{{ $id }}-lista" x-ref="lista" role="listbox" tabindex="-1" aria-labelledby="{{ $id }}-etiqueta"
            x-bind:aria-activedescendant="activo >= 0 ? '{{ $id }}-opcion-' + activo : null"
            x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
            x-on:keydown.home.prevent="extremo(false)" x-on:keydown.end.prevent="extremo(true)"
            x-on:keydown.enter.prevent="elegir(activo)" x-on:keydown.space.prevent="elegir(activo)"
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain p-1.5 focus:outline-none [scrollbar-color:#c9ddd8_transparent] [scrollbar-width:thin]">
            @foreach ($lista as $indice => $opcion)
                <li id="{{ $id }}-opcion-{{ $indice }}" role="option" data-valor="{{ $opcion['valor'] }}"
                    aria-selected="{{ $indice === $indiceActual ? 'true' : 'false' }}"
                    x-bind:aria-selected="indiceActual === {{ $indice }} ? 'true' : 'false'"
                    x-show="visibles.includes({{ $indice }})"
                    x-on:mouseenter="activo = {{ $indice }}"
                    x-on:click="elegir({{ $indice }})"
                    x-bind:class="{ 'bg-[var(--mint)]': activo === {{ $indice }}, 'font-bold text-[var(--p900)]': indiceActual === {{ $indice }} }"
                    class="flex min-h-9 cursor-pointer items-center gap-3 rounded-lg px-2.5 py-1.5 text-[13px] text-[var(--ink)]">
                    <span class="min-w-0 flex-1">
                        {{ $opcion['etiqueta'] }}@if ($opcion['detalle'])<span class="ml-1 text-[11.5px] font-medium text-[#6B6559]">· {{ $opcion['detalle'] }}</span>@endif
                    </span>
                    <svg x-show="indiceActual === {{ $indice }}" @if ($indice !== $indiceActual) style="display: none" @endif
                        class="h-4 w-4 shrink-0 text-[var(--p700)]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5" /></svg>
                </li>
            @endforeach
            <li x-show="visibles.length === 0" style="display: none" class="px-2.5 py-2 text-[13px] text-[#6B6559]" role="presentation">Sin resultados</li>
        </ul>
    </div>
</div>
