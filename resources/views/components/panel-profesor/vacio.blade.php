@props([
    'titulo',
    'descripcion' => null,
    'icono' => 'info',
    'tono' => 'bg-celeste text-azul-oscuro',
])

{{-- Estado vacío compacto del Panel del Profesor: una sola fila con icono (sin bloque punteado). --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3']) }}>
    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $tono }}">
        <x-icono :nombre="$icono" class="h-5 w-5" />
    </span>
    <p class="min-w-0 text-sm leading-snug text-slate-500">
        <span class="font-semibold text-texto">{{ $titulo }}</span>
        @if ($descripcion)
            <span class="block sm:inline">{{ $descripcion }}</span>
        @endif
    </p>
</div>
