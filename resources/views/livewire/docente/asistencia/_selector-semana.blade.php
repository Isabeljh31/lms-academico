{{--
    Selector de semana: anterior/siguiente, desplegable propio (Alpine, listbox
    con teclado), «Ir a esta semana» y la cinta de semanas 1..N con su estado.
    Cambiar de semana solo cambia ?semana=N (Livewire), sin recargar la página.
--}}
@php
    $insignias = [
        'pendiente' => ['Pendiente', 'bg-[var(--warn200)] text-[var(--warn800)]'],
        'actual' => ['En curso', 'bg-[var(--inf100)] text-[var(--inf700)]'],
        'proxima' => ['Próxima', 'bg-[#eef3f1] text-[#3d5753]'],
        'programada' => ['Programada', 'bg-[#eef3f1] text-[#5b706c]'],
        'sin_clases' => ['Sin clases', 'bg-[#f4f7f6] text-[#6b7f7b]'],
    ];
    $insignia = function (array $datos) use ($insignias, $virtual): array {
        if ($virtual) {
            return match ($datos['momento']) {
                'pasada' => ['Cerrada', 'bg-[#eef3f1] text-[#3d5753]'],
                'actual' => ['En curso', 'bg-[var(--inf100)] text-[var(--inf700)]'],
                default => ['Programada', 'bg-[#eef3f1] text-[#5b706c]'],
            };
        }

        if ($datos['estado'] === 'completa') {
            return [$datos['porcentaje'] !== null ? \App\Services\Reportes\AsistenciaProfesor::porcentajeTexto($datos['porcentaje']) : 'Completa', 'bg-[var(--ok100)] text-[var(--ok900)]'];
        }

        return $insignias[$datos['estado']] ?? ['—', 'bg-[#eef3f1] text-[#5b706c]'];
    };
    $barras = [
        'completa' => 'bg-[var(--p700)]',
        'pendiente' => 'bg-[#F2B233]',
        'actual' => 'bg-[#8fcabd]',
        'proxima' => 'bg-[#dfe8e5]',
        'programada' => 'bg-[#dfe8e5]',
        'sin_clases' => 'bg-[#eef3f1]',
    ];
    $flecha = "grid h-12 w-12 shrink-0 place-items-center rounded-xl border-[1.5px] border-[#d6e6e2] bg-white text-[var(--p700)] transition-colors hover:bg-[var(--mint)] disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none {$enfoque}";
@endphp

<div class="flex flex-col gap-3.5 px-7 pb-5 pt-5">
    <div class="flex flex-wrap items-center gap-2.5">
        <button type="button" wire:click="semanaAnterior" @disabled($semana <= 1) class="{{ $flecha }}" aria-label="Semana anterior">
            <x-icono nombre="flecha-izquierda" class="h-4 w-4" />
        </button>

        <div class="relative w-full max-w-[380px] sm:w-[380px]"
            x-data="{
                abierto: false,
                activo: {{ (int) $semana }},
                total: {{ (int) $totalSemanas }},
                abrir() {
                    this.activo = {{ (int) $semana }};
                    this.abierto = true;
                    this.$nextTick(() => { this.$refs.lista.focus({ preventScroll: true }); this.mostrar(); });
                },
                cerrar(foco = true) {
                    if (! this.abierto) return;
                    this.abierto = false;
                    if (foco) this.$refs.boton.focus({ preventScroll: true });
                },
                mover(paso) { this.activo = Math.min(this.total, Math.max(1, this.activo + paso)); this.mostrar(); },
                mostrar() { this.$nextTick(() => document.getElementById('opcion-semana-' + this.activo)?.scrollIntoView({ block: 'nearest' })); },
                elegir(numero) { this.cerrar(); if (numero !== {{ (int) $semana }}) $wire.irASemana(numero); },
            }"
            x-on:click.outside="cerrar(false)"
            x-on:keydown.escape.prevent.stop="cerrar()">
            <span id="etiqueta-semana" class="sr-only">Semana del curso</span>
            <button type="button" x-ref="boton" aria-haspopup="listbox" aria-controls="lista-semanas" aria-labelledby="etiqueta-semana boton-semana" id="boton-semana"
                aria-expanded="false" x-bind:aria-expanded="abierto ? 'true' : 'false'"
                x-on:click="abierto ? cerrar() : abrir()" x-on:keydown.arrow-down.prevent="abrir()" x-on:keydown.arrow-up.prevent="abrir()"
                x-bind:class="abierto ? 'ring-[3px] ring-[rgba(19,110,97,0.16)]' : ''"
                class="flex h-14 w-full items-center gap-3 rounded-xl border-[1.5px] border-[var(--p700)] bg-white px-4 text-left transition-shadow focus:outline-none focus-visible:ring-[3px] focus-visible:ring-[rgba(19,110,97,0.16)] motion-reduce:transition-none">
                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="text-[17px] font-extrabold text-[var(--p900)]">Semana {{ $semana }}</span>
                        @if ($semana === $semanaActual)
                            <span class="rounded-full bg-[var(--p900)] px-2 py-0.5 text-[11px] font-bold text-white">Esta semana</span>
                        @endif
                    </span>
                    <span class="block text-[13px] text-[#4b625e]">{{ $rangoTexto($actual['rango'] ?? null) }}</span>
                </span>
                <svg class="h-4 w-4 shrink-0 text-[var(--p700)] transition-transform motion-reduce:transition-none" x-bind:class="abierto ? 'rotate-180' : ''" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6" /></svg>
            </button>

            <div x-show="abierto" x-cloak style="display: none"
                x-transition:enter="transition-opacity duration-100 motion-reduce:transition-none" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                class="absolute left-0 top-full z-40 mt-1.5 flex w-full min-w-[300px] flex-col overflow-hidden rounded-xl border border-[#e1ece9] bg-white shadow-[0_10px_30px_rgba(9,59,59,0.14),0_2px_6px_rgba(9,59,59,0.06)] sm:w-[440px]">
                <ul id="lista-semanas" x-ref="lista" role="listbox" tabindex="-1" aria-labelledby="etiqueta-semana"
                    x-bind:aria-activedescendant="'opcion-semana-' + activo"
                    x-on:keydown.arrow-down.prevent="mover(1)" x-on:keydown.arrow-up.prevent="mover(-1)"
                    x-on:keydown.home.prevent="activo = 1; mostrar()" x-on:keydown.end.prevent="activo = total; mostrar()"
                    x-on:keydown.enter.prevent="elegir(activo)" x-on:keydown.space.prevent="elegir(activo)" x-on:keydown.tab="cerrar(false)"
                    class="max-h-80 overflow-y-auto overscroll-contain p-1.5 focus:outline-none [scrollbar-color:#c9ddd8_transparent] [scrollbar-width:thin]">
                    @foreach ($semanas as $numero => $datos)
                        @php [$textoInsignia, $claseInsignia] = $insignia($datos); @endphp
                        <li id="opcion-semana-{{ $numero }}" role="option" aria-selected="{{ $numero === $semana ? 'true' : 'false' }}"
                            x-on:mouseenter="activo = {{ $numero }}" x-on:click="elegir({{ $numero }})"
                            x-bind:class="activo === {{ $numero }} ? 'bg-[var(--mint)]' : ''"
                            class="flex min-h-11 cursor-pointer items-center gap-4 rounded-lg px-3 py-2 text-[13.5px] {{ $numero === $semana ? 'font-extrabold' : 'font-bold' }} text-[var(--p900)]">
                            <span class="w-[92px] shrink-0">Semana {{ $numero }}</span>
                            <span class="min-w-0 flex-1 font-medium text-[#3d5753]">{{ $rangoTexto($datos['rango']) }}</span>
                            <span class="whitespace-nowrap rounded-full px-2 py-0.5 text-[11.5px] font-bold {{ $claseInsignia }}">{{ $textoInsignia }}</span>
                        </li>
                    @endforeach
                </ul>
                <p class="border-t border-[#eef4f2] px-4 py-2.5 text-[12.5px] text-[#5b706c]">Elige una semana para ver {{ $virtual ? 'sus lecciones' : 'sus clases' }}</p>
            </div>
        </div>

        <button type="button" wire:click="semanaSiguiente" @disabled($semana >= $totalSemanas) class="{{ $flecha }}" aria-label="Semana siguiente">
            <x-icono nombre="flecha-derecha" class="h-4 w-4" />
        </button>

        @if ($semanaActual !== null && $semana !== $semanaActual)
            <button type="button" wire:click="irAEstaSemana" class="{{ $enlace }} ml-auto text-[14px]">Ir a esta semana</button>
        @endif
    </div>

    <ol class="flex gap-2 overflow-x-auto pb-1 [scrollbar-width:thin]" aria-label="Semanas del curso">
        @foreach ($semanas as $numero => $datos)
            @php
                $elegida = $numero === $semana;
                $punteada = ! $virtual && in_array($datos['estado'], ['proxima', 'programada'], true) || ($virtual && $datos['momento'] === 'futura');
                [$textoInsignia] = $insignia($datos);
            @endphp
            <li class="min-w-[52px] flex-1">
                <button type="button" wire:click="irASemana({{ $numero }})" @if ($elegida) aria-current="true" @endif
                    aria-label="Semana {{ $numero }}{{ $numero === $semanaActual ? ', esta semana' : '' }}, {{ $textoInsignia }}"
                    class="flex h-[54px] w-full flex-col items-center justify-center gap-1.5 rounded-xl text-[14px] font-extrabold transition-colors motion-reduce:transition-none {{ $enfoque }}
                        {{ $elegida ? 'bg-[var(--p900)] text-white' : ($punteada ? 'border-[1.5px] border-dashed border-[#cfdcd8] bg-white text-[#6b7f7b] hover:bg-[var(--mint)]' : 'bg-[#f3f8f6] text-[var(--p900)] hover:bg-[var(--mint)]') }}
                        {{ ! $elegida && $numero === $semanaActual ? 'ring-[1.5px] ring-[var(--p900)]' : '' }}">
                    {{ $numero }}
                    @unless ($virtual)
                        <span class="h-1 w-3/4 rounded-full {{ $barras[$datos['estado']] ?? 'bg-[#eef3f1]' }}" aria-hidden="true"></span>
                    @endunless
                </button>
            </li>
        @endforeach
    </ol>

    @unless ($virtual)
        <ul class="flex flex-wrap gap-x-5 gap-y-1 text-[12.5px] text-[#4b625e]" aria-label="Leyenda de la cinta de semanas">
            <li class="flex items-center gap-2"><span class="h-1 w-4 rounded-full bg-[var(--p700)]" aria-hidden="true"></span>Completa</li>
            <li class="flex items-center gap-2"><span class="h-1 w-4 rounded-full bg-[#F2B233]" aria-hidden="true"></span>Con pendientes</li>
            <li class="flex items-center gap-2"><span class="h-1 w-4 rounded-full bg-[#dfe8e5]" aria-hidden="true"></span>Programada</li>
            <li class="flex items-center gap-2"><span class="h-1 w-4 rounded-full bg-[#eef3f1] ring-1 ring-[#dfe8e5]" aria-hidden="true"></span>Sin clases</li>
        </ul>
    @endunless
</div>
