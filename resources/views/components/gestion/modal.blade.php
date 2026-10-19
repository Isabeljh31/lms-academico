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
@php
    $esAdmin = auth()->user()?->hasRole('supervisor') ?? false;
    $superficie = $esAdmin ? 'dark:bg-stone-800' : 'dark:bg-azul-superficie-oscura';
    $borde = $esAdmin ? 'border-arena-medio dark:border-stone-700' : 'border-celeste dark:border-azul-borde-oscuro';
    $textoTitulo = $esAdmin ? 'text-stone-800 dark:text-stone-100' : 'text-texto dark:text-slate-100';
    $textoDesc = $esAdmin ? 'text-stone-500 dark:text-stone-400' : 'text-slate-500 dark:text-slate-400';
    $botonCerrar = $esAdmin ? 'text-stone-400 hover:bg-arena-claro hover:text-stone-600 dark:hover:bg-stone-700' : 'text-slate-400 hover:bg-celeste hover:text-slate-600 dark:hover:bg-azul-borde-oscuro';
@endphp

<div class="fixed inset-0 z-50 flex items-end justify-center bg-stone-900/50 p-0 sm:items-center sm:p-4"
    x-data x-on:keydown.escape.window="$wire.{{ $cerrar }}">
    <div role="dialog" aria-modal="true"
        class="max-h-[90vh] w-full {{ $ancho }} overflow-y-auto rounded-t-2xl bg-white shadow-2xl sm:rounded-2xl {{ $superficie }}">
        <div class="flex items-start justify-between gap-4 border-b {{ $borde }} px-5 py-4">
            <div>
                <h2 class="text-lg font-bold {{ $textoTitulo }}">{{ $titulo }}</h2>
                @if ($descripcion)
                    <p class="mt-1 text-xs {{ $textoDesc }}">{{ $descripcion }}</p>
                @endif
            </div>
            <button type="button" wire:click="{{ $cerrar }}" aria-label="Cerrar"
                class="rounded-lg p-1 {{ $botonCerrar }}">
                <x-icono nombre="cerrar" class="h-5 w-5" />
            </button>
        </div>
        <div class="space-y-4 px-5 py-4">
            {{ $slot }}
        </div>
        @isset($pie)
            <div class="flex flex-col-reverse gap-2 border-t {{ $borde }} px-5 py-4 sm:flex-row sm:justify-end">
                {{ $pie }}
            </div>
        @endisset
    </div>
</div>
