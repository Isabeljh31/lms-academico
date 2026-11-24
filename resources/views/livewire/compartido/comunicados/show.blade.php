<div class="py-8 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto space-y-6">

    <a href="{{ $comunicado->curso_id ? route('cursos.show', $comunicado->curso_id) : route('dashboard') }}" wire:navigate
        class="inline-flex items-center gap-1 text-sm font-medium text-azul-oscuro hover:underline dark:text-azul-fondo">
        <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> {{ $comunicado->curso_id ? 'Volver al curso' : 'Volver al panel' }}
    </a>

    <div class="bg-white dark:bg-azul-superficie-oscura rounded-2xl border border-celeste dark:border-azul-borde-oscuro shadow-sm overflow-hidden">
        <div class="p-6 space-y-4">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                @if ($comunicado->prioridad === 'urgente')
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-semibold text-red-700 dark:bg-red-950/40 dark:text-red-300">
                        <x-icono nombre="alerta" class="h-3 w-3" /> Urgente
                    </span>
                @endif
                <span class="rounded-full bg-celeste/60 dark:bg-azul-borde-oscuro/60 px-2 py-0.5 text-slate-600 dark:text-slate-300">
                    {{ $comunicado->curso_id ? 'Anuncio de '.$comunicado->curso?->nombre : 'Comunicado institucional' }}
                </span>
            </div>

            <h1 class="text-xl font-bold text-texto dark:text-slate-100">{{ $comunicado->titulo }}</h1>

            <p class="text-xs text-slate-400 dark:text-slate-500">
                {{ $comunicado->autor?->name ?? 'LearnUp' }} &middot; {{ $comunicado->created_at->formatoFechaHora() }}
            </p>

            @if ($comunicado->imagenUrl())
                <img src="{{ $comunicado->imagenUrl() }}" alt="" class="w-full rounded-xl object-cover max-h-96">
            @endif

            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">{{ $comunicado->contenido }}</p>
        </div>
    </div>
</div>
