@props([
    'estudiante',
    'editable' => false,
    'buscable' => false,
    'recien' => false,
])

{{--
    Estudiante en el tablero de Grupos. Editable: chip arrastrable dentro de
    una zona de tableroGrupos (casilla, clic o Espacio selecciona; Shift + clic
    selecciona un rango, Enter lo levanta para moverlo con las flechas) con
    un botón «Mover a…» que no inicia el arrastre (data-no-arrastrar).
--}}
@php
    $iniciales = collect(preg_split('/\s+/', trim($estudiante->name)))->filter()->take(2)
        ->map(fn ($parte) => mb_strtoupper(mb_substr($parte, 0, 1)))->implode('');
    $id = (string) $estudiante->id;
@endphp

@if ($editable)
    <li data-estudiante="{{ $id }}" data-nombre="{{ $estudiante->name }}" wire:key="chip-{{ $id }}"
        @if ($buscable) x-show="visible(@js($estudiante->name))" @endif
        x-bind:class="seleccionados.includes('{{ $id }}') ? 'border-azul-oscuro bg-azul-oscuro/5 ring-2 ring-azul-oscuro/25 dark:border-azul-fondo dark:bg-azul-fondo/10' : 'border-celeste bg-white hover:border-azul-oscuro/40 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura'"
        @class(['flex shrink-0 cursor-grab touch-manipulation select-none items-center gap-1 rounded-lg border border-celeste bg-white py-1 pl-1.5 pr-1 shadow-sm transition-colors active:cursor-grabbing motion-reduce:transition-none dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura',
            'grupos-entrada' => $recien])>
        <input type="checkbox" data-no-arrastrar value="{{ $id }}" x-model="seleccionados"
            aria-label="Seleccionar a {{ $estudiante->name }}"
            class="mx-1 h-4 w-4 shrink-0 rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
        <button type="button" class="flex min-w-0 flex-1 items-center gap-2 rounded-md py-0.5 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro"
            x-on:click="elegir($event, '{{ $id }}')" x-on:keydown="teclaEnChip($event, '{{ $id }}')"
            aria-pressed="false" x-bind:aria-pressed="seleccionados.includes('{{ $id }}').toString()"
            aria-label="{{ $estudiante->name }}. Espacio para seleccionar, Enter para mover con las flechas.">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-azul-oscuro/10 text-[11px] font-bold text-azul-oscuro dark:bg-azul-fondo/20 dark:text-azul-fondo" aria-hidden="true">{{ $iniciales }}</span>
            <span class="truncate text-sm font-medium text-texto dark:text-slate-100">{{ $estudiante->name }}</span>
        </button>
        <button type="button" data-no-arrastrar x-on:click="abrirMenu('{{ $id }}')" title="Mover a…"
            class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-slate-400 hover:bg-celeste hover:text-azul-oscuro focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:hover:bg-azul-borde-oscuro"
            aria-label="Mover a {{ $estudiante->name }} a otro grupo">
            <x-icono nombre="puntos" class="h-4 w-4" />
        </button>
    </li>
@else
    <li wire:key="chip-{{ $id }}" class="flex items-center gap-2 rounded-lg border border-celeste bg-white px-2 py-1.5 dark:border-stone-700 dark:bg-stone-800">
        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-celeste text-[11px] font-bold text-slate-600 dark:bg-stone-700 dark:text-stone-200" aria-hidden="true">{{ $iniciales }}</span>
        <span class="truncate text-sm text-stone-700 dark:text-stone-200">{{ $estudiante->name }}</span>
    </li>
@endif
