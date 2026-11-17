{{-- Pie de tabla paginada (página «pagina» de WithPagination): «Mostrando a–b de N» y páginas. --}}
@php
    $botonPagina = "grid h-9 min-w-9 place-items-center rounded-lg border-[1.5px] px-2.5 text-[13px] font-bold transition-colors disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none {$enfoque}";
    $ultimaPagina = $pagina->lastPage();
    $actualPagina = $pagina->currentPage();
    $numeros = collect(range(1, $ultimaPagina))->filter(fn (int $numero) => $numero === 1 || $numero === $ultimaPagina || abs($numero - $actualPagina) <= 1)->values();
@endphp

<nav class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e8f0ee] px-7 py-4" aria-label="Paginación">
    <p class="text-[13.5px] text-[#4b625e]">Mostrando {{ $pagina->firstItem() }}–{{ $pagina->lastItem() }} de {{ $pagina->total() }} estudiantes</p>
    @if ($ultimaPagina > 1)
        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="previousPage('pagina')" @disabled($pagina->onFirstPage()) class="{{ $botonPagina }} border-[#d6e6e2] bg-white text-[var(--p700)] hover:bg-[var(--mint)]" aria-label="Página anterior">
                <x-icono nombre="flecha-izquierda" class="h-4 w-4" />
            </button>
            @foreach ($numeros as $indice => $numero)
                @if ($indice > 0 && $numero - $numeros[$indice - 1] > 1)
                    <span class="px-1 text-[#6b7f7b]" aria-hidden="true">…</span>
                @endif
                <button type="button" wire:click="gotoPage({{ $numero }}, 'pagina')" @if ($numero === $actualPagina) aria-current="page" @endif aria-label="Página {{ $numero }}"
                    class="{{ $botonPagina }} {{ $numero === $actualPagina ? 'border-[var(--p900)] bg-[var(--p900)] text-white' : 'border-[#d6e6e2] bg-white text-[var(--p900)] hover:bg-[var(--mint)]' }}">{{ $numero }}</button>
            @endforeach
            <button type="button" wire:click="nextPage('pagina')" @disabled(! $pagina->hasMorePages()) class="{{ $botonPagina }} border-[#d6e6e2] bg-white text-[var(--p700)] hover:bg-[var(--mint)]" aria-label="Página siguiente">
                <x-icono nombre="flecha-derecha" class="h-4 w-4" />
            </button>
        </div>
    @endif
</nav>
