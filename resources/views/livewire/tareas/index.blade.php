<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if ($this->esVistaDocente)
        <x-gestion.encabezado titulo="Tareas" icono="tarea" :descripcion="$curso->nombre.', las notas de tareas son de práctica y no entran en la nota final ponderada.'">
            @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                <x-gestion.boton icono="mas" :href="route('cursos.tareas.crear', $curso)">Nueva tarea</x-gestion.boton>
            @endif
        </x-gestion.encabezado>
        <x-gestion.alertas />
        @else
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="text-sm {{ $this->puedeGestionar ? 'text-menta-oscuro dark:text-menta-base' : 'text-azul-oscuro dark:text-azul-fondo' }} hover:underline">&larr; {{ $curso->nombre }}</a>
                <h1 class="text-2xl font-bold {{ $this->puedeGestionar ? 'text-stone-900 dark:text-stone-100' : 'text-texto dark:text-slate-100' }}">Tareas</h1>
            </div>

            @if ($this->puedeGestionar)
                <a href="{{ route('cursos.tareas.crear', $curso) }}" wire:navigate
                    class="inline-flex items-center px-4 py-2 bg-menta-oscuro text-white text-sm font-semibold rounded-md hover:bg-menta-oscuro/90">
                    + Nueva tarea
                </a>
            @endif
        </div>

        @endif

        @if ($tareas->isEmpty() && ! $this->esVistaDocente)
            <p class="text-stone-500 dark:text-stone-400">Todavía no hay tareas en este curso.</p>
        @elseif ($tareas->isEmpty())
            <x-gestion.vacio icono="tarea" titulo="Todavía no hay tareas en este curso" descripcion="Crea una tarea y elige si publicarla ahora, dejarla en borrador o programarla." />
        @elseif ($this->esVistaDocente)
            {{-- Vista de Profesor / Supervisor (este último en solo lectura). --}}
            <x-gestion.tarjeta :relleno="false">
                <ul class="divide-y {{ auth()->user()->hasRole('supervisor') ? 'divide-arena-medio/60 dark:divide-stone-700' : 'divide-celeste/60 dark:divide-azul-borde-oscuro' }}">
                    @foreach ($tareas as $tarea)
                        @php
                            $visibleEstudiante = $tarea->esVisibleParaEstudiante();
                            $esAdminVista = auth()->user()->hasRole('supervisor');
                            $claseBadgeNeutra = $esAdminVista ? 'bg-arena-medio text-stone-700 dark:bg-stone-700 dark:text-stone-200' : 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200';
                        @endphp
                        <li wire:key="tarea-{{ $tarea->id }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                            <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="truncate font-medium {{ $esAdminVista ? 'text-stone-800 dark:text-stone-100' : 'text-texto dark:text-slate-100' }}">{{ $tarea->titulo }}</h3>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $visibleEstudiante ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                                    @if ($tarea->esGrupal())
                                        <span class="rounded-full px-2 py-0.5 text-xs {{ $claseBadgeNeutra }}">Grupal{{ $tarea->grupoAsignado ? ': '.$tarea->grupoAsignado->nombre : '' }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">
                                    {{ $tarea->modulo?->nombreCompleto() }} - Vence {{ $tarea->fecha_entrega->formatoFechaHora() }} - {{ $tarea->entregas->count() }} entregas - {{ $tarea->entregas->where('estado', '!=', 'calificado')->count() }} por calificar
                                </p>
                            </a>
                            @if ($this->puedeGestionar && ! $curso->estaFinalizado())
                                <div class="flex shrink-0 flex-wrap gap-1">
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" :href="route('cursos.tareas.editar', [$curso, $tarea])">Editar</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma" tamano="sm" :icono="$tarea->visible ? 'ojo-tachado' : 'ojo'" wire:click="alternarVisibilidad({{ $tarea->id }})">
                                        {{ $tarea->visible ? 'Ocultar' : 'Publicar' }}
                                    </x-gestion.boton>
                                    @if ($tarea->entregas->isEmpty())
                                        <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $tarea->id }})"
                                            wire:confirm="¿Eliminar la tarea {{ $tarea->titulo }}? Esta acción no se puede deshacer.">Eliminar</x-gestion.boton>
                                    @endif
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @else
            {{-- Rediseño visual del rol estudiante: tarjetas con jerarquía por urgencia (ver ARQUITECTURA.md). --}}
            @php
                $tarjetas = $tareas->map(function ($tarea) {
                    $entrega = $tarea->entregas->first();
                    $estado = $tarea->estadoPara($entrega);
                    $completada = in_array($estado, ['entregado', 'fuera_de_plazo', 'calificado'], true);
                    $urgente = ! $completada && (in_array($estado, ['no_entregado', 'devuelto'], true) || ($tarea->fecha_entrega->isFuture() && $tarea->fecha_entrega->diffInDays(now()) <= 3));

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
                                        {{ $t['tarea']->modulo?->nombreCompleto() }} - Vence {{ $t['tarea']->fecha_entrega->formatoFechaHora() }}
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
