@props([
    'etiqueta',
    'nombre' => null,
    'requerido' => false,
    'ayuda' => null,
])

{{-- Campo de formulario con etiqueta, ayuda y error visible (ERS RNF-07). --}}
<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    <label @if ($nombre) for="{{ $nombre }}" @endif class="block text-sm font-medium text-stone-700 dark:text-stone-300">
        {{ $etiqueta }}
        @if ($requerido) <span class="text-terracota-oscuro" aria-hidden="true">*</span> @endif
    </label>
    {{ $slot }}
    @if ($ayuda)
        <p class="text-xs text-stone-500 dark:text-stone-400">{{ $ayuda }}</p>
    @endif
    @if ($nombre)
        @error($nombre)
            <p class="flex items-center gap-1 text-xs font-medium text-red-600 dark:text-red-400">
                <x-icono nombre="alerta" class="h-3.5 w-3.5" /> {{ $message }}
            </p>
        @enderror
    @endif
</div>
