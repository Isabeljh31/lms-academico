@php
    $badgeEstado = $profesor->activo
        ? ['color' => 'bg-exito-base/15 text-exito-oscuro dark:text-green-300', 'label' => 'Activo']
        : ['color' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300', 'label' => 'Desactivado'];
@endphp
<div class="py-8">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.alertas />

        <x-gestion.tarjeta>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <img src="{{ $profesor->fotoUrl('3C7A75') }}" alt=""
                        class="h-16 w-16 shrink-0 rounded-full border-2 border-arena-medio object-cover">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-stone-900 dark:text-stone-100">{{ $profesor->name }}</h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeEstado['color'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $profesor->activo ? 'bg-exito-base' : 'bg-red-500' }}"></span> {{ $badgeEstado['label'] }}
                            </span>
                        </div>
                        @if ($profesor->codigo)
                            <p class="font-mono text-sm text-stone-500 dark:text-stone-400">{{ $profesor->codigo }}</p>
                        @endif
                        @if ($profesor->especialidad)
                            <p class="mt-1 flex items-center gap-1 text-sm text-stone-500 dark:text-stone-400">
                                <x-icono nombre="birrete" class="h-4 w-4 shrink-0" /> {{ $profesor->especialidad }}
                            </p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditar({{ $profesor->id }})">Editar</x-gestion.boton>
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button type="button" aria-label="Más acciones para {{ $profesor->name }}"
                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-stone-500 hover:bg-arena-claro hover:text-stone-700 dark:text-stone-400 dark:hover:bg-stone-700">
                                <x-icono nombre="puntos" class="h-4 w-4" />
                            </button>
                        </x-slot>
                        <x-slot name="content">
                            <button type="button" wire:click="confirmarCambiarEstado({{ $profesor->id }})"
                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                <x-icono :nombre="$profesor->activo ? 'prohibido' : 'refrescar'" class="h-4 w-4" />
                                {{ $profesor->activo ? 'Desactivar' : 'Reactivar' }}
                            </button>
                            <hr class="my-1 border-arena-medio dark:border-stone-700">
                            <button type="button" wire:click="confirmarEliminarProfesor({{ $profesor->id }})"
                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-stone-700">
                                <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                            </button>
                        </x-slot>
                    </x-dropdown>
                </div>
            </div>
        </x-gestion.tarjeta>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-gestion.tarjeta titulo="Datos personales">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">DNI</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->dni ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Correo institucional</dt><dd class="truncate font-medium text-stone-800 dark:text-stone-100">{{ $profesor->email }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Correo personal</dt><dd class="truncate font-medium text-stone-800 dark:text-stone-100">{{ $profesor->correo_personal ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Celular</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->celular ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500 dark:text-stone-400">Edad</dt>
                        <dd class="font-medium text-stone-800 dark:text-stone-100">
                            @if ($profesor->edad()) {{ $profesor->edad() }} años ({{ $profesor->anio_nacimiento }}) @else sin registrar @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Cuenta creada</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->created_at->formatoFecha() }}</dd></div>
                </dl>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Datos profesionales">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Especialidad</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->especialidad ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Grado académico</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->grado_academico ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">N.° de colegiatura</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->colegiatura ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Inicio de labores</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $profesor->fecha_inicio_labores?->formatoFecha() ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500 dark:text-stone-400">CV</dt>
                        <dd class="font-medium">
                            @if ($profesor->cv_path)
                                <a href="{{ $profesor->cvUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-menta-oscuro hover:underline dark:text-menta-base">
                                    <x-icono nombre="documento" class="h-4 w-4" /> Ver CV
                                </a>
                            @else
                                <span class="text-stone-800 dark:text-stone-100">sin subir</span>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-gestion.tarjeta>
        </div>

        <x-gestion.tarjeta titulo="Calificación de entregas">
            <x-slot name="acciones">
                <span class="text-sm text-stone-500 dark:text-stone-400">{{ $estudiantesCount }} estudiantes activos - {{ $tareasCount }} tareas</span>
            </x-slot>
            <div class="flex items-center justify-between text-sm">
                <span class="text-stone-600 dark:text-stone-400">Revisadas en total</span>
                <span class="font-bold {{ $tasaCalificacion >= 80 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }}">{{ $tasaCalificacion }} %</span>
            </div>
            <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-arena-medio/50 dark:bg-stone-700" role="progressbar" aria-valuenow="{{ $tasaCalificacion }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-2 rounded-full {{ $tasaCalificacion >= 80 ? 'bg-exito-base' : 'bg-terracota-base' }}" style="width: {{ $tasaCalificacion }}%"></div>
            </div>
            <p class="mt-2 text-xs text-stone-500">{{ $calificadas }} calificadas - {{ $pendientes }} pendientes - {{ $totalEntregas }} en total</p>

            @if ($cursosConMetricas->isNotEmpty())
                <div class="mt-4 overflow-x-auto rounded-lg border border-arena-medio/60 dark:border-stone-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-arena-claro/40 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-700/40 dark:text-stone-400">
                            <tr>
                                <th class="px-3 py-2">Curso</th>
                                <th class="px-3 py-2 text-right">Estudiantes</th>
                                <th class="px-3 py-2 text-right">Tareas</th>
                                <th class="px-3 py-2 text-right">% calificado</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($cursosConMetricas as $m)
                                <tr wire:key="metrica-curso-{{ $m['curso']->id }}" class="{{ $m['curso']->estaFinalizado() ? 'opacity-60' : '' }}">
                                    <td class="px-3 py-2">
                                        <a href="{{ route('cursos.show', $m['curso']) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $m['curso']->nombre }}</a>
                                        @if ($m['curso']->estaFinalizado())
                                            <span class="ml-1 text-xs text-stone-500">(archivado)</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right text-stone-700 dark:text-stone-300">{{ $m['estudiantes'] }}</td>
                                    <td class="px-3 py-2 text-right text-stone-700 dark:text-stone-300">{{ $m['tareas'] }}</td>
                                    <td class="px-3 py-2 text-right font-semibold {{ $m['tasa'] >= 80 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }}">{{ $m['tasa'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>

        <x-gestion.tarjeta titulo="Cursos a cargo">
            <x-slot name="acciones">
                <x-gestion.boton variante="fantasma" tamano="sm" icono="mas" wire:click="abrirModalAsignar({{ $profesor->id }})">Asignar curso</x-gestion.boton>
            </x-slot>
            @if ($cursosActivos->isEmpty())
                <p class="text-sm text-stone-500">Sin cursos activos asignados.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($cursosActivos as $c)
                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-arena-medio bg-arena-claro/40 px-2.5 py-1 text-sm dark:border-stone-600 dark:bg-stone-700/50" wire:key="curso-tag-{{ $c->id }}">
                            <a href="{{ route('cursos.show', $c) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $c->nombre }}</a>
                            <button type="button" wire:click="confirmarDesasignarCurso({{ $c->id }})"
                                class="text-stone-400 hover:text-terracota-oscuro" title="Desvincular" aria-label="Desvincular del curso {{ $c->nombre }}">
                                <x-icono nombre="cerrar" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endforeach
                </div>
            @endif

            @if ($cursosArchivados->isNotEmpty())
                <details class="mt-4 group">
                    <summary class="cursor-pointer text-xs font-semibold uppercase tracking-wide text-stone-500 hover:text-stone-700 dark:hover:text-stone-300">
                        Historial - {{ $cursosArchivados->count() }} {{ $cursosArchivados->count() === 1 ? 'curso archivado' : 'cursos archivados' }}
                    </summary>
                    <div class="mt-2 flex flex-wrap gap-2">
                        @foreach ($cursosArchivados as $c)
                            <span class="inline-flex items-center gap-1.5 rounded-lg border border-arena-medio/60 bg-arena-claro/20 px-2.5 py-1 text-sm text-stone-500 dark:border-stone-700 dark:bg-stone-700/30" wire:key="curso-archivado-{{ $c->id }}">
                                <a href="{{ route('cursos.show', $c) }}" wire:navigate class="hover:underline">{{ $c->nombre }}</a>
                            </span>
                        @endforeach
                    </div>
                </details>
            @endif
        </x-gestion.tarjeta>
    </div>

    @if ($mostrarModalEditar)
        @include('livewire.supervisor.profesores._modal-editar')
    @endif

    @if ($mostrarModalAsignar)
        @include('livewire.supervisor.profesores._modal-asignar')
    @endif

    @if ($mostrarConfirmacion)
        @include('livewire.supervisor.profesores._modal-confirmar')
    @endif
</div>
