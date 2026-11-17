@props([
    'variante' => 'fila',
    'valor' => 'borrador',
    'leccion' => null,
    'titulo' => '',
    'editar' => false,
])

@php
    /**
     * Visibilidad para el estudiante (pantalla del Profesor): mismo menú en la
     * fila de cada elemento y en el formulario "Agregar". Las opciones y sus
     * textos salen de GestionaContenidoSemanal::VISIBILIDAD_LECCION.
     *
     * - variante "fila": botón "Visible"/"No visible" sin flecha; elegir llama a
     *   cambiarVisibilidadLeccion($leccion, valor) y, con :editar, el menú
     *   incluye "Editar contenido" (editarLeccion) tras una línea.
     * - variante "campo": desplegable enlazado con wire:model (p. ej. "estado"),
     *   con la descripción de la opción elegida debajo.
     */
    $opciones = \App\Livewire\Cursos\Show::VISIBILIDAD_LECCION;
    $esCampo = $variante === 'campo';
    $modelo = $attributes->wire('model');
    $id = $esCampo ? 'visibilidad-'.\Illuminate\Support\Str::slug((string) $modelo->value()) : 'visibilidad-leccion-'.$leccion;
    $valor = array_key_exists((string) $valor, $opciones) ? (string) $valor : 'borrador';
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $estilos = [
        'publicado' => ['pastilla' => 'bg-[#F5FCD2] text-[#3B6506]', 'icono' => 'ojo'],
        'borrador' => ['pastilla' => 'bg-[#EEF2F1] text-[#475569]', 'icono' => 'ojo-tachado'],
    ];
@endphp

<div
    x-data="{
        valor: @if ($esCampo) @entangle($modelo) @else @js($valor) @endif,
        opciones: @js($opciones),
        abierto: false,
        get actual() { return this.opciones[this.valor] ? this.valor : 'borrador' },
        elementos() { return [...this.$refs.panel.querySelectorAll('[data-opcion]')] },
        abrir() {
            if (this.abierto) return;
            this.abierto = true;
            this.$nextTick(() => {
                this.posicionar();
                (this.elementos().find(el => el.dataset.opcion === this.actual) ?? this.elementos()[0])?.focus({ preventScroll: true });
            });
        },
        cerrar(devolverFoco = true) {
            if (! this.abierto) return;
            this.abierto = false;
            if (devolverFoco) this.$refs.boton.focus({ preventScroll: true });
        },
        mover(paso) {
            const elementos = this.elementos();
            const posicion = elementos.indexOf(document.activeElement);
            elementos[(posicion + paso + elementos.length) % elementos.length]?.focus();
        },
        extremo(final) { const elementos = this.elementos(); elementos[final ? elementos.length - 1 : 0]?.focus(); },
        elegir(opcion) {
            this.cerrar();
            if (opcion === this.actual) return;
            this.valor = opcion;
            @unless ($esCampo)
                $wire.cambiarVisibilidadLeccion({{ (int) $leccion }}, opcion);
            @endunless
        },
        {{-- Posición fija bajo el botón (o encima si no cabe), para no quedar recortado por la tarjeta con overflow oculto. --}}
        posicionar() {
            if (! this.abierto) return;
            const boton = this.$refs.boton.getBoundingClientRect();
            const panel = this.$refs.panel;
            @if ($esCampo) panel.style.width = boton.width + 'px'; @endif
            const alto = panel.offsetHeight;
            const ancho = panel.offsetWidth;
            const haciaArriba = alto > window.innerHeight - boton.bottom - 12 && boton.top - 12 > window.innerHeight - boton.bottom - 12;
            panel.style.top = (haciaArriba ? boton.top - alto - 6 : boton.bottom + 6) + 'px';
            panel.style.left = Math.max(8, Math.min({{ $esCampo ? 'boton.left' : 'boton.right - ancho' }}, window.innerWidth - ancho - 8)) + 'px';
        },
    }"
    x-on:click.outside="cerrar(false)"
    x-on:keydown.escape="if (abierto) { $event.stopPropagation(); cerrar(); }"
    x-on:resize.window="posicionar()"
    x-on:scroll.window="posicionar()"
    {{ $attributes->whereDoesntStartWith('wire:model')->class([$esCampo ? 'relative min-w-0' : 'relative shrink-0']) }}>

    @if ($esCampo)
        <span id="{{ $id }}-etiqueta" class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-300">Visibilidad para el estudiante</span>
    @endif

    <button type="button" x-ref="boton" id="{{ $id }}-boton"
        aria-haspopup="menu" aria-controls="{{ $id }}-menu" aria-expanded="false"
        x-bind:aria-expanded="abierto ? 'true' : 'false'"
        @if ($esCampo) aria-labelledby="{{ $id }}-etiqueta {{ $id }}-boton" @else aria-label="Visibilidad de {{ $titulo }}: {{ $opciones[$valor]['etiqueta'] }}" x-bind:aria-label="'Visibilidad de ' + @js($titulo) + ': ' + opciones[actual].etiqueta" @endif
        x-on:click="abierto ? cerrar() : abrir()"
        x-on:keydown.arrow-down.prevent="abrir()"
        x-on:keydown.arrow-up.prevent="abrir()"
        @if ($esCampo)
            x-bind:class="abierto ? 'border-[var(--p700)] ring-[3px] ring-[rgba(19,110,97,0.14)]' : 'border-[#d3dedb]'"
            class="flex h-11 w-full items-center gap-2.5 rounded-[10px] border-[1.5px] border-[#d3dedb] bg-white px-2.5 text-left text-[13px] font-semibold text-[var(--ink)] transition-[border-color,box-shadow] duration-150 motion-reduce:transition-none {{ $enfoque }}"
        @else
            x-bind:class="actual === 'publicado' ? @js($estilos['publicado']['pastilla']) : @js($estilos['borrador']['pastilla'])"
            class="inline-flex min-h-11 items-center gap-1.5 whitespace-nowrap rounded-lg px-2.5 text-xs font-bold transition-colors motion-reduce:transition-none {{ $estilos[$valor]['pastilla'] }} {{ $enfoque }}"
        @endif>
        @if ($esCampo)
            <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-lg"
                x-bind:class="actual === 'publicado' ? @js($estilos['publicado']['pastilla']) : @js($estilos['borrador']['pastilla'])">
                @foreach ($estilos as $clave => $estilo)
                    <x-icono :nombre="$estilo['icono']" class="h-4 w-4" x-show="actual === '{{ $clave }}'" :style="$clave === $valor ? null : 'display: none'" />
                @endforeach
            </span>
            <span class="min-w-0 flex-1 truncate" x-text="opciones[actual].etiqueta">{{ $opciones[$valor]['etiqueta'] }}</span>
            <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-[#475569] transition-transform duration-150 motion-reduce:transition-none" x-bind:class="abierto ? 'rotate-180' : ''" />
        @else
            @foreach ($estilos as $clave => $estilo)
                <x-icono :nombre="$estilo['icono']" class="h-4 w-4" x-show="actual === '{{ $clave }}'" :style="$clave === $valor ? null : 'display: none'" />
            @endforeach
            <span x-text="opciones[actual].corta">{{ $opciones[$valor]['corta'] }}</span>
        @endif
    </button>

    @if ($esCampo)
        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400" x-text="opciones[actual].detalle">{{ $opciones[$valor]['detalle'] }}</p>
    @endif

    <div x-ref="panel" id="{{ $id }}-menu" role="menu" aria-labelledby="{{ $id }}-boton" x-show="abierto" x-cloak style="display: none"
        x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
        x-on:keydown.home.prevent="extremo(false)" x-on:keydown.end.prevent="extremo(true)"
        x-on:keydown.tab="cerrar(false)"
        class="fixed z-50 flex w-max min-w-[280px] max-w-[360px] flex-col rounded-xl border border-[#d3dedb] bg-white p-[5px] shadow-[0_12px_28px_rgba(9,59,59,0.16)]">
        @foreach ($opciones as $clave => $opcion)
            <button type="button" role="menuitemradio" data-opcion="{{ $clave }}" tabindex="-1"
                aria-checked="{{ $clave === $valor ? 'true' : 'false' }}" x-bind:aria-checked="actual === '{{ $clave }}' ? 'true' : 'false'"
                x-on:click="elegir('{{ $clave }}')"
                x-bind:class="actual === '{{ $clave }}' ? 'bg-[var(--mint)]' : 'hover:bg-[#F1F7F5]'"
                class="flex w-full items-center gap-2.5 rounded-[9px] px-[9px] py-2 text-left focus:outline-none focus-visible:bg-[#F1F7F5] focus-visible:ring-2 focus-visible:ring-[var(--p700)]">
                <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-lg {{ $estilos[$clave]['pastilla'] }}" aria-hidden="true">
                    <x-icono :nombre="$estilos[$clave]['icono']" class="h-4 w-4" />
                </span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[13px] font-bold text-[var(--p900)]">{{ $opcion['etiqueta'] }}</span>
                    <span class="block text-[11.5px] text-[#5b706c]">{{ $opcion['detalle'] }}</span>
                </span>
                <x-icono nombre="check" class="h-4 w-4 shrink-0 text-[var(--p700)]" x-show="actual === '{{ $clave }}'" :style="$clave === $valor ? null : 'display: none'" aria-hidden="true" />
            </button>
        @endforeach
        @if (! $esCampo && $editar)
            <div role="separator" class="my-[5px] border-t border-[#e2ece9]"></div>
            <button type="button" role="menuitem" data-opcion="editar" tabindex="-1"
                x-on:click="cerrar(false); $wire.editarLeccion({{ (int) $leccion }})"
                class="flex w-full items-center gap-2.5 rounded-[9px] px-[9px] py-2 text-left text-[13px] font-bold text-[var(--p900)] hover:bg-[#F1F7F5] focus:outline-none focus-visible:bg-[#F1F7F5] focus-visible:ring-2 focus-visible:ring-[var(--p700)]">
                <span class="grid h-[26px] w-[26px] shrink-0 place-items-center rounded-lg bg-[#EEF2F1] text-[#475569]" aria-hidden="true">
                    <x-icono nombre="lapiz" class="h-4 w-4" />
                </span>
                Editar contenido
            </button>
        @endif
    </div>
</div>
