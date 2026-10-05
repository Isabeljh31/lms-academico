@props([
    'titulo',
    'descripcion' => null,
    'icono' => 'info',
])

{{-- Estado vacío informativo (ERS RNF-08) para Profesor y Supervisor. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-xl border border-dashed border-arena-medio px-6 py-12 text-center dark:border-stone-700']) }}>
    <span class="flex h-12 w-12 items-center justify-center rounded-full bg-arena-claro text-stone-500 dark:bg-stone-800 dark:text-stone-400">
        <x-icono :nombre="$icono" class="h-6 w-6" />
    </span>
    <p class="mt-3 text-sm font-semibold text-stone-700 dark:text-stone-200">{{ $titulo }}</p>
    @if ($descripcion)
        <p class="mt-1 max-w-md text-sm text-stone-500 dark:text-stone-400">{{ $descripcion }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mt-4 flex flex-wrap justify-center gap-2">{{ $slot }}</div>
    @endif
</div>
