@props([
    'titulo',
    'descripcion' => null,
    'volver' => null,
    'volverTexto' => 'Volver',
    'icono' => null,
])

{{-- Encabezado de página para las pantallas de Profesor y Supervisor. --}}
<div class="space-y-3">
    @if ($volver)
        <a href="{{ $volver }}" wire:navigate
            class="inline-flex items-center gap-1.5 text-sm font-medium text-stone-500 hover:text-menta-oscuro dark:text-stone-400 dark:hover:text-menta-base transition">
            <x-icono nombre="flecha-izquierda" class="h-4 w-4" />
            {{ $volverTexto }}
        </a>
    @endif

    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-3 min-w-0">
            @if ($icono)
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-menta-fondo text-menta-oscuro dark:bg-stone-800 dark:text-menta-base">
                    <x-icono :nombre="$icono" class="h-5 w-5" />
                </span>
            @endif
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100 break-words">{{ $titulo }}</h1>
                @if ($descripcion)
                    <p class="mt-1 text-sm text-stone-500 dark:text-stone-400">{{ $descripcion }}</p>
                @endif
            </div>
        </div>

        @if ($slot->isNotEmpty())
            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                {{ $slot }}
            </div>
        @endif
    </div>
</div>
