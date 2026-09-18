<div class="py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm {{ $this->puedeGestionar ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo' }} hover:underline">&larr; {{ $curso->nombre }}</a>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Tareas</h1>
            </div>

            @if ($this->puedeGestionar)
                <a href="{{ route('cursos.tareas.crear', $curso) }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nueva tarea
                </a>
            @endif
        </div>

        @if ($tareas->isEmpty())
            <p class="text-stone-500 dark:text-stone-400">Todavía no hay tareas en este curso.</p>
        @elseif ($this->puedeGestionar)
            {{-- Vista de profesor/administrador: sin cambios respecto a la versión anterior. --}}
            <div class="space-y-3">
                @foreach ($tareas as $tarea)
                    <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate wire:key="tarea-{{ $tarea->id }}"
                        class="flex items-center justify-between bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-4 hover:shadow-md transition">
                        <div>
                            <h3 class="font-medium text-stone-800 dark:text-stone-100">{{ $tarea->titulo }}</h3>
                            <p class="text-xs text-stone-500 dark:text-stone-400 mt-1">
                                {{ $tarea->modulo?->titulo }} · Vence {{ $tarea->fecha_entrega->format('d/m/Y H:i') }} · Peso {{ $tarea->peso_nota }}
                            </p>
                        </div>

                        <div class="flex items-center gap-3">
                            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $tarea->entregas->count() }} entregas</span>
                        </div>
                    </a>
                @endforeach
            </div>
        @else
            {{-- Rediseño visual del rol estudiante: tarjetas con jerarquía por urgencia (ver ARQUITECTURA.md). --}}
            @php
                $tarjetas = $tareas->map(function ($tarea) {
                    $entrega = $tarea->entregas->first();
                    $estado = $entrega ? $entrega->estado : ($tarea->estaVencida() ? 'atrasado' : 'pendiente');
                    $completada = in_array($estado, ['entregado', 'calificado'], true);
                    $urgente = ! $completada && ($estado === 'atrasado' || ($tarea->fecha_entrega->isFuture() && $tarea->fecha_entrega->diffInDays(now()) <= 3));

                    return compact('tarea', 'estado', 'completada', 'urgente');
                })->sortBy(fn ($t) => [$t['completada'] ? 1 : 0, $t['tarea']->fecha_entrega]);
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                @foreach ($tarjetas as $t)
                    <a href="{{ route('cursos.tareas.show', [$curso, $t['tarea']]) }}" wire:navigate wire:key="tarea-card-{{ $t['tarea']->id }}"
                        class="rounded-xl border p-4 shadow-sm dark:shadow-none transition hover:shadow-md
                            {{ $t['urgente']
                                ? 'border-coral-oscuro/40 bg-coral-fondo/40 dark:bg-coral-oscuro/15'
                                : ($t['completada'] ? 'border-celeste dark:border-azul-borde-oscuro bg-celeste/20 dark:bg-azul-superficie-oscura/60 opacity-80' : 'border-celeste dark:border-azul-borde-oscuro bg-white dark:bg-azul-superficie-oscura') }}">
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg
                                    {{ $t['urgente'] ? 'bg-coral-oscuro text-white' : 'bg-celeste/50 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste' }}">
                                    <x-icono nombre="tarea" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="font-medium text-texto dark:text-slate-100 truncate">{{ $t['tarea']->titulo }}</h3>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                        {{ $t['tarea']->modulo?->titulo }} · Vence {{ $t['tarea']->fecha_entrega->format('d/m/Y H:i') }}
                                    </p>
                                </div>
                            </div>
                            <x-badge-estado :estado="$t['estado']" />
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
