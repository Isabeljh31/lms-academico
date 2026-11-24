@php
    // Mismo criterio de paleta que la campanita: fría para Estudiante/Profesor, cálida para Supervisor.
    $paletaFria = ! auth()->user()->hasRole('supervisor');
    $claseBorde = $paletaFria ? 'border-celeste dark:border-azul-borde-oscuro' : 'border-arena-medio dark:border-stone-700';
    $claseSuperficie = $paletaFria ? 'bg-white dark:bg-azul-superficie-oscura' : 'bg-white dark:bg-stone-800';
    $claseAcento = $paletaFria ? 'text-azul-oscuro dark:text-cielo' : 'text-menta-oscuro dark:text-menta-base';
@endphp

<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-3xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-texto dark:text-slate-100 flex items-center gap-2">
                <x-icono nombre="campana" class="h-6 w-6" /> Notificaciones
            </h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ $noLeidas > 0 ? $noLeidas.' sin leer' : 'Estás al día.' }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <button type="button" wire:click="alternarFiltro"
                class="text-xs font-semibold rounded-full border px-3 py-1.5 {{ $claseBorde }} {{ $soloNoLeidas ? $claseAcento : 'text-slate-500 dark:text-slate-400' }}">
                {{ $soloNoLeidas ? 'Mostrando: no leídas' : 'Mostrando: todas' }}
            </button>

            @if($noLeidas > 0)
            <button type="button" wire:click="marcarTodasLeidas" class="text-xs font-semibold {{ $claseAcento }} hover:underline">
                Marcar todas leídas
            </button>
            @endif
        </div>
    </div>

    {{-- Listado --}}
    <div class="{{ $claseSuperficie }} rounded-2xl border {{ $claseBorde }} shadow-sm overflow-hidden">
        @forelse($filas as $fila)
        <a href="{{ $fila['url'] }}" wire:navigate wire:click="marcarLeida('{{ $fila['id'] }}')"
            class="flex items-start gap-3 px-5 py-4 border-b last:border-0 {{ $claseBorde }} hover:bg-celeste/10 dark:hover:bg-azul-borde-oscuro/20 {{ $fila['leida'] ? 'opacity-60' : '' }}">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $paletaFria ? 'bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-cielo' : 'bg-menta-oscuro/10 text-menta-oscuro dark:bg-menta-base/20 dark:text-menta-base' }}">
                <x-icono :nombre="$fila['icono']" class="h-4 w-4" />
            </span>
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <p class="font-medium text-texto dark:text-slate-100 truncate">{{ $fila['titulo'] }}</p>
                    @unless($fila['leida'])
                    <span class="h-1.5 w-1.5 rounded-full {{ $paletaFria ? 'bg-azul-oscuro dark:bg-cielo' : 'bg-menta-oscuro dark:bg-menta-base' }} shrink-0"></span>
                    @endunless
                </div>
                <p class="text-sm text-slate-500 dark:text-slate-400 mt-0.5">{{ $fila['mensaje'] }}</p>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">{{ $fila['etiqueta'] }} · {{ $fila['fecha']->diffForHumans() }}</p>
            </div>
        </a>
        @empty
        <p class="px-5 py-10 text-center text-sm text-slate-400 dark:text-slate-500">
            {{ $soloNoLeidas ? 'No tienes notificaciones sin leer.' : 'Todavía no tienes notificaciones.' }}
        </p>
        @endforelse
    </div>

    @if($paginador->hasPages())
    <div>{{ $paginador->links() }}</div>
    @endif
</div>
