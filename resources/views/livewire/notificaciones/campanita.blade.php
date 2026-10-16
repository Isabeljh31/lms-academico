@php
    // Montada globalmente en layouts/app.blade.php para los 3 roles, así que
    // el color se condiciona al rol en vez de duplicar el componente — ver
    // ARQUITECTURA.md § nueva paleta de color.
    $paletaFria = auth()->check() && ! auth()->user()->hasRole('supervisor');
@endphp

<div class="relative" wire:poll.30s x-data @click.outside="$wire.abierta = false">
    <button wire:click="alternar" class="relative {{ $paletaFria ? 'text-slate-500 dark:text-slate-400 hover:text-azul-oscuro dark:hover:text-azul-fondo' : 'text-stone-500 dark:text-stone-400 hover:text-menta-oscuro dark:hover:text-menta-base' }}" title="Notificaciones">
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.4-1.4A2 2 0 0118 14.2V11a6 6 0 10-12 0v3.2c0 .5-.2 1-.6 1.4L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
        </svg>

        @if ($noLeidas > 0)
            <span class="absolute -top-1 -right-1 flex items-center justify-center h-4 min-w-[1rem] px-1 rounded-full {{ $paletaFria ? 'bg-coral-oscuro' : 'bg-terracota-oscuro' }} text-white text-[10px] font-bold">
                {{ $noLeidas > 9 ? '9+' : $noLeidas }}
            </span>
        @endif
    </button>

    <div x-show="$wire.abierta" x-cloak x-transition
        class="absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto rounded-md shadow-lg {{ $paletaFria ? 'bg-white dark:bg-azul-superficie-oscura' : 'bg-white dark:bg-stone-800' }} ring-1 ring-black ring-opacity-5 z-50">
        <div class="flex items-center justify-between px-4 py-2 border-b {{ $paletaFria ? 'border-celeste dark:border-azul-borde-oscuro' : 'border-arena-medio dark:border-stone-700' }}">
            <span class="text-sm font-semibold {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-700 dark:text-stone-200' }}">Notificaciones</span>
            @if ($noLeidas > 0)
                <button wire:click="marcarTodasLeidas" class="text-xs {{ $paletaFria ? 'text-azul-oscuro dark:text-azul-fondo' : 'text-menta-oscuro dark:text-menta-base' }} hover:underline">Marcar todas leídas</button>
            @endif
        </div>

        @forelse ($notificaciones as $notificacion)
            <a href="{{ $notificacion->data['url'] ?? '#' }}" wire:navigate wire:click="marcarLeida('{{ $notificacion->id }}')"
                class="block px-4 py-3 text-sm border-b last:border-0 {{ $paletaFria ? 'border-celeste dark:border-azul-borde-oscuro hover:bg-celeste/30 dark:hover:bg-azul-borde-oscuro/40' : 'border-arena-medio dark:border-stone-700 hover:bg-arena-claro dark:hover:bg-stone-700' }} {{ $notificacion->read_at ? 'opacity-60' : '' }}">
                <p class="font-medium {{ $paletaFria ? 'text-texto dark:text-slate-100' : 'text-stone-800 dark:text-stone-100' }}">{{ $notificacion->data['titulo'] ?? 'Notificación' }}</p>
                <p class="{{ $paletaFria ? 'text-slate-500 dark:text-slate-400' : 'text-stone-500 dark:text-stone-400' }} text-xs mt-0.5">{{ $notificacion->data['mensaje'] ?? '' }}</p>
                <p class="{{ $paletaFria ? 'text-slate-400' : 'text-stone-400' }} text-[10px] mt-1">{{ $notificacion->created_at->diffForHumans() }}</p>
            </a>
        @empty
            <p class="px-4 py-6 text-sm {{ $paletaFria ? 'text-slate-400' : 'text-stone-400' }} text-center">Sin notificaciones todavía.</p>
        @endforelse
    </div>
</div>
