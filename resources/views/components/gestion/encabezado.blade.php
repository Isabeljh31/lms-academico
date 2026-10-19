@props([
    'titulo',
    'descripcion' => null,
    'icono' => null,
])

{{-- Encabezado de página para las pantallas de Profesor y Supervisor. --}}
@php
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;
    $iconoFondo = $esAdmin ? 'bg-menta-fondo text-menta-oscuro dark:bg-stone-800 dark:text-menta-base' : 'bg-celeste text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo';
    $textoTitulo = $esAdmin ? 'text-stone-900 dark:text-stone-100' : 'text-texto dark:text-slate-100';
    $textoDesc = $esAdmin ? 'text-stone-500 dark:text-stone-400' : 'text-slate-500 dark:text-slate-400';
@endphp

<div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
    <div class="flex items-start gap-3 min-w-0">
        @if ($icono)
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $iconoFondo }}">
                <x-icono :nombre="$icono" class="h-5 w-5" />
            </span>
        @endif
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold {{ $textoTitulo }} break-words">{{ $titulo }}</h1>
            @if ($descripcion)
                <p class="mt-1 text-sm {{ $textoDesc }}">{{ $descripcion }}</p>
            @endif
        </div>
    </div>

    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            {{ $slot }}
        </div>
    @endif
</div>
