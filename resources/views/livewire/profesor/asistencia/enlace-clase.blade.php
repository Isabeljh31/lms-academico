{{--
    «Enlace de esta clase» (menú ⋮ de una clase remota futura o en curso):
    modal pequeño con el mismo campo que el del curso. Muestra si la clase
    tiene enlace propio o hereda el del curso, siempre solo con el dominio.
--}}
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $botonContorno = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-full border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p900)] transition-colors hover:bg-[var(--mint)] disabled:opacity-60 motion-reduce:transition-none {$enfoque}";
    $botonOscuro = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-full bg-[#1D1C19] px-5 text-[13px] font-bold text-white transition-colors hover:bg-[var(--p800)] disabled:opacity-60 motion-reduce:transition-none {$enfoque}";
    $control = 'w-full rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-3 py-2 text-[13.5px] text-[var(--ink)] focus:border-[var(--p700)] focus:outline-none focus:ring-[3px] focus:ring-[rgba(19,110,97,0.14)]';
@endphp

<div>
    @if ($aviso)
        <div wire:key="aviso-enlace-{{ md5($aviso) }}" data-aviso-flotante role="status" aria-live="polite"
            x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 6000)" x-show="visible" x-transition.opacity
            class="fixed right-4 top-4 z-[60] flex max-w-[380px] items-start gap-2.5 rounded-xl border border-[#E0D8C8] bg-white px-4 py-3 text-[13.5px] font-semibold text-[var(--p900)] shadow-[0_12px_28px_rgba(9,59,59,0.18)]">
            <x-icono nombre="check-circulo" class="h-5 w-5 shrink-0 text-[var(--ok900)]" />
            <p class="flex-1">{{ $aviso }}</p>
            <button type="button" x-on:click="visible = false" aria-label="Cerrar aviso" class="grid h-6 w-6 shrink-0 place-items-center rounded text-[#57524A] hover:bg-[var(--mint)] {{ $enfoque }}">
                <x-icono nombre="cerrar" class="h-4 w-4" />
            </button>
        </div>
    @endif

    @if ($abierto && $clase)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[rgba(9,59,59,0.45)] p-0 sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.cerrar()">
            <div role="dialog" aria-modal="true" aria-labelledby="titulo-enlace-clase" x-trap.noscroll="true"
                class="w-full max-w-[460px] rounded-t-2xl border border-[#E0D8C8] bg-white shadow-[0_20px_50px_rgba(9,59,59,0.18)] sm:rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-[#ECE6DA] px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="titulo-enlace-clase" class="text-[15px] font-extrabold text-[var(--p900)]">Enlace de esta clase</h2>
                        <p class="mt-0.5 text-xs text-[#6B6559]">{{ $clase->titulo }} · {{ DetalleCursoProfesor::fechaCorta($clase->fecha) }} · {{ $clase->fecha->format('H:i') }}</p>
                    </div>
                    <button type="button" wire:click="cerrar" aria-label="Cerrar" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[#57524A] hover:bg-[var(--mint)] {{ $enfoque }}">
                        <x-icono nombre="cerrar" class="h-5 w-5" />
                    </button>
                </div>

                <form wire:submit="guardar" class="flex flex-col gap-3 px-5 py-4">
                    <p class="flex items-start gap-2 rounded-[10px] bg-[var(--mint)] px-3 py-2 text-xs text-[var(--p900)]" data-estado-enlace-clase>
                        <x-icono nombre="enlace" class="h-4 w-4 shrink-0" />
                        @if ($dominioPropio)
                            <span>Esta clase tiene un enlace propio: <strong>{{ $dominioPropio }}</strong>.</span>
                        @elseif ($dominioCurso)
                            <span>Esta clase hereda el enlace del curso: <strong>{{ $dominioCurso }}</strong>.</span>
                        @else
                            <span>Esta clase hereda el enlace del curso, pero el curso aún no tiene enlace.</span>
                        @endif
                    </p>

                    <div>
                        <label for="enlace-clase-nuevo" class="mb-1 block text-[13px] font-semibold text-[var(--ink)]">{{ $dominioPropio ? 'Cambiar el enlace de esta clase' : 'Enlace distinto para esta clase' }}</label>
                        <input id="enlace-clase-nuevo" type="url" inputmode="url" autocomplete="off" maxlength="{{ \App\Services\Asistencia\EnlacesClaseRemota::LARGO_MAXIMO }}"
                            wire:model="enlaceNuevo" placeholder="https://meet.google.com/…" aria-describedby="ayuda-enlace-clase" class="{{ $control }}">
                        <p id="ayuda-enlace-clase" class="mt-1 text-xs text-[#6B6559]">Solo enlaces https de: {{ $dominiosPermitidos }}.</p>
                        @error('enlace_remoto') <p role="alert" class="mt-1 flex items-center gap-1 text-xs font-semibold text-[var(--err700)]">{{ $message }}</p> @enderror
                    </div>

                    <div class="flex flex-wrap justify-end gap-2 pt-1">
                        @if ($dominioPropio)
                            <button type="button" wire:click="heredar" wire:loading.attr="disabled" class="{{ $botonContorno }}">Usar el enlace del curso</button>
                        @endif
                        <button type="submit" wire:loading.attr="disabled" class="{{ $botonOscuro }}">Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
