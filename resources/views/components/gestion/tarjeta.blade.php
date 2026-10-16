@props([
    'titulo' => null,
    'descripcion' => null,
    'relleno' => true,
])

{{-- Contenedor de sección para las pantallas de Profesor y Supervisor. --}}
@php
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;
    $borde = $esAdmin ? 'border-arena-medio dark:border-stone-700' : 'border-celeste dark:border-azul-borde-oscuro';
    $superficie = $esAdmin ? 'dark:bg-stone-800' : 'dark:bg-azul-superficie-oscura';
    $textoTitulo = $esAdmin ? 'text-stone-800 dark:text-stone-100' : 'text-texto dark:text-slate-100';
    $textoDesc = $esAdmin ? 'text-stone-500 dark:text-stone-400' : 'text-slate-500 dark:text-slate-400';
@endphp

<section {{ $attributes->merge(['class' => "rounded-xl border $borde bg-white shadow-sm $superficie dark:shadow-none"]) }}>
    @if ($titulo || isset($acciones))
        <header class="flex flex-col gap-2 border-b {{ $borde }} px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if ($titulo)
                    <h2 class="text-base font-semibold {{ $textoTitulo }}">{{ $titulo }}</h2>
                @endif
                @if ($descripcion)
                    <p class="text-xs {{ $textoDesc }}">{{ $descripcion }}</p>
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
