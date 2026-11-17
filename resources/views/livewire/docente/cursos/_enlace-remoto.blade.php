{{--
    Tarjeta «Enlace de clases remotas» (detalle del curso del Profesor). Se
    incluye solo si el curso es remoto o tiene clases remotas. Muestra solo el
    dominio del enlace guardado; el campo empieza vacío (nunca se precarga la
    URL). Curso archivado o sin permiso de edición = solo lectura.
    Variables: $enlaceRemoto (Cursos\Show::tarjetaEnlaceRemoto), $tarjeta, $enfoque.
--}}
@php
    $controlEnlace = 'h-10 w-full rounded-[10px] border-[1.5px] border-[#d3dedb] bg-white px-3 text-[13.5px] text-[var(--ink)] focus:border-[var(--p700)] focus:outline-none focus:ring-[3px] focus:ring-[rgba(19,110,97,0.14)]';
@endphp

<section aria-labelledby="titulo-enlace-remoto" class="{{ $tarjeta }} px-[22px] py-5" data-tarjeta-enlace-remoto>
    @if ($avisoEnlaceRemoto)
        <div wire:key="aviso-enlace-remoto-{{ md5($avisoEnlaceRemoto) }}" data-aviso-flotante role="status" aria-live="polite"
            x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 6000)" x-show="visible" x-transition.opacity
            class="fixed right-4 top-4 z-[60] flex max-w-[380px] items-start gap-2.5 rounded-xl border border-[#cfe2dd] bg-white px-4 py-3 text-[13.5px] font-semibold text-[var(--p900)] shadow-[0_12px_28px_rgba(9,59,59,0.18)]">
            <x-icono nombre="check-circulo" class="h-5 w-5 shrink-0 text-[var(--ok900)]" />
            <p class="flex-1">{{ $avisoEnlaceRemoto }}</p>
            <button type="button" x-on:click="visible = false" aria-label="Cerrar aviso" class="grid h-6 w-6 shrink-0 place-items-center rounded text-[#4b625e] hover:bg-[var(--mint)] {{ $enfoque }}">
                <x-icono nombre="cerrar" class="h-4 w-4" />
            </button>
        </div>
    @endif

    <div class="flex items-start gap-3">
        <span class="grid h-10 w-10 shrink-0 place-items-center rounded-[10px] bg-[var(--mint)] text-[var(--p700)]"><x-icono nombre="enlace" class="h-5 w-5" /></span>
        <div class="min-w-0 flex-1">
            <h2 id="titulo-enlace-remoto" class="text-[17px] font-extrabold text-[var(--p900)]">Enlace de clases remotas</h2>
            <p class="mt-[3px] text-[12.5px] text-[#5b706c]">Tus estudiantes lo usan para unirse a las clases remotas. Una clase puede tener su propio enlace desde su menú ⋮ en Asistencia.</p>
        </div>
    </div>

    <p class="mt-3 flex items-center gap-2 text-[13px]" data-estado-enlace-remoto>
        @if ($enlaceRemoto['dominio'])
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-[var(--ok100)] px-[9px] py-0.5 text-[11px] font-bold text-[var(--ok900)]">Enlace guardado</span>
            <span class="min-w-0 truncate font-semibold text-[var(--p900)]">{{ $enlaceRemoto['dominio'] }}</span>
        @else
            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-[var(--warn200)] px-[9px] py-0.5 text-[11px] font-bold text-[var(--warn800)]">Sin enlace</span>
            <span class="text-[#5b706c]">Las clases remotas aún no tienen enlace.</span>
        @endif
    </p>

    @if ($enlaceRemoto['editable'])
        <form wire:submit="guardarEnlaceRemoto" class="mt-3 flex flex-col gap-2">
            <label for="enlace-remoto-nuevo" class="text-[13px] font-semibold text-[var(--ink)]">{{ $enlaceRemoto['dominio'] ? 'Cambiar enlace' : 'Pegar enlace' }}</label>
            <input id="enlace-remoto-nuevo" type="url" inputmode="url" autocomplete="off" maxlength="{{ \App\Services\Asistencia\EnlacesClaseRemota::LARGO_MAXIMO }}"
                wire:model="enlaceRemotoNuevo" placeholder="https://meet.google.com/…" aria-describedby="ayuda-enlace-remoto" class="{{ $controlEnlace }}">
            <p id="ayuda-enlace-remoto" class="text-xs text-[#5b706c]">Solo enlaces https de: {{ $enlaceRemoto['dominios'] }}.</p>
            @error('enlace_remoto') <p role="alert" class="flex items-center gap-1 text-xs font-semibold text-[var(--err700)]">{{ $message }}</p> @enderror
            <div class="mt-1 flex flex-wrap gap-2">
                <button type="submit" wire:loading.attr="disabled" wire:target="guardarEnlaceRemoto"
                    class="inline-flex h-[38px] items-center justify-center rounded-[10px] bg-[var(--p800)] px-4 text-[13px] font-bold text-white transition-colors hover:bg-[var(--p900)] disabled:opacity-60 motion-reduce:transition-none {{ $enfoque }}">Guardar</button>
                @if ($enlaceRemoto['dominio'])
                    <button type="button" wire:click="quitarEnlaceRemoto" wire:confirm="¿Quitar el enlace de clases remotas del curso?" wire:loading.attr="disabled" wire:target="quitarEnlaceRemoto"
                        class="inline-flex h-[38px] items-center justify-center rounded-[10px] border-[1.5px] border-[#f3c9c2] bg-white px-4 text-[13px] font-bold text-[var(--err700)] transition-colors hover:bg-[var(--err100)] disabled:opacity-60 motion-reduce:transition-none {{ $enfoque }}">Quitar enlace</button>
                @endif
            </div>
        </form>
    @else
        <p class="mt-3 text-xs text-[#5b706c]">{{ $curso->estaFinalizado() ? 'Curso archivado: el enlace no se puede cambiar.' : 'Solo los docentes del curso pueden cambiar el enlace.' }}</p>
    @endif
</section>
