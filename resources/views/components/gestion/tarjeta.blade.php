@props([
    'titulo' => null,
    'descripcion' => null,
    'relleno' => true,
])

{{-- Contenedor de sección para las pantallas de Profesor y Supervisor. --}}
<section {{ $attributes->merge(['class' => 'rounded-xl border border-arena-medio bg-white shadow-sm dark:border-stone-700 dark:bg-stone-800']) }}>
    @if ($titulo || isset($acciones))
        <header class="flex flex-col gap-2 border-b border-arena-medio px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-stone-700">
            <div>
                @if ($titulo)
                    <h2 class="text-base font-semibold text-stone-800 dark:text-stone-100">{{ $titulo }}</h2>
                @endif
                @if ($descripcion)
                    <p class="text-xs text-stone-500 dark:text-stone-400">{{ $descripcion }}</p>
                @endif
            </div>
            @isset($acciones)
                <div class="flex flex-wrap items-center gap-2">{{ $acciones }}</div>
            @endisset
        </header>
    @endif
    <div class="{{ $relleno ? 'p-4' : '' }}">
        {{ $slot }}
    </div>
</section>
