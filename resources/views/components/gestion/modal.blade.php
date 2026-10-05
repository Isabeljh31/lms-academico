@props([
    'titulo',
    'cerrar',
    'descripcion' => null,
    'ancho' => 'max-w-lg',
])

{{--
    Modal de las pantallas de Profesor y Supervisor. Se muestra u oculta desde
    el componente Livewire (envolver en @if). "cerrar" es la expresión Livewire
    que lo cierra, p. ej. "$set('mostrarModal', false)".
--}}
<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-0 sm:items-center sm:p-4"
    x-data x-on:keydown.escape.window="$wire.{{ $cerrar }}">
    <div role="dialog" aria-modal="true"
        class="max-h-[90vh] w-full {{ $ancho }} overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl dark:bg-stone-800">
        <div class="flex items-start justify-between gap-4 border-b border-arena-medio px-5 py-4 dark:border-stone-700">
            <div>
                <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100">{{ $titulo }}</h2>
                @if ($descripcion)
                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">{{ $descripcion }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $cerrar }}" aria-label="Cerrar"
                class="rounded-lg p-1 text-stone-400 hover:bg-arena-claro hover:text-stone-600 dark:hover:bg-stone-700">
                <x-icono nombre="cerrar" class="h-5 w-5" />
            </button>
        </div>
        <div class="space-y-4 px-5 py-4">
            {{ $slot }}
        </div>
        @isset($pie)
            <div class="flex flex-col-reverse gap-2 border-t border-arena-medio px-5 py-4 sm:flex-row sm:justify-end dark:border-stone-700">
                {{ $pie }}
            </div>
        @endisset
    </div>
</div>
