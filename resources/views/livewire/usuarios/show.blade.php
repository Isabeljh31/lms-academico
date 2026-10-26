@php
    $badgeEstado = $estudiante->activo
        ? ['color' => 'bg-exito-base/15 text-exito-oscuro dark:text-green-300', 'label' => 'Activo']
        : ['color' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300', 'label' => 'Desactivado'];

    $condiciones = [
        'aprobado' => ['label' => 'Aprobado', 'clase' => 'text-exito-oscuro'],
        'desaprobado' => ['label' => 'Desaprobado', 'clase' => 'text-red-600 dark:text-red-400'],
        'en_curso' => ['label' => 'En curso', 'clase' => 'text-stone-500 dark:text-stone-400'],
        'sin_notas' => ['label' => 'Sin notas', 'clase' => 'text-stone-400 dark:text-stone-500'],
    ];
@endphp
<div class="py-8">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.alertas />

        <x-gestion.tarjeta>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex items-start gap-4">
                    <img src="{{ $estudiante->fotoUrl() }}" alt=""
                        class="h-16 w-16 shrink-0 rounded-full border-2 border-arena-medio object-cover">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-xl font-bold text-stone-900 dark:text-stone-100">{{ $estudiante->nombreFormal() }}</h1>
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeEstado['color'] }}">
                                <span class="h-1.5 w-1.5 rounded-full {{ $estudiante->activo ? 'bg-exito-base' : 'bg-red-500' }}"></span> {{ $badgeEstado['label'] }}
                            </span>
                            @if ($riesgoNivel === 'Alto')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                    <x-icono nombre="alerta" class="h-3.5 w-3.5" /> Riesgo alto
                                </span>
                            @elseif ($riesgoNivel === 'Medio')
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-arena-claro px-2.5 py-0.5 text-xs font-medium text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                    <x-icono nombre="alerta" class="h-3.5 w-3.5" /> Riesgo medio
                                </span>
                            @endif
                        </div>
                        @if ($estudiante->codigo)
                            <p class="font-mono text-sm text-stone-500 dark:text-stone-400">{{ $estudiante->codigo }}</p>
                        @endif
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-1">
                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditar({{ $estudiante->id }})">Editar</x-gestion.boton>
                    @if ($estudiante->isNot(auth()->user()))
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button type="button" aria-label="Más acciones para {{ $estudiante->name }}"
                                    class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-stone-500 hover:bg-arena-claro hover:text-stone-700 dark:text-stone-400 dark:hover:bg-stone-700">
                                    <x-icono nombre="puntos" class="h-4 w-4" />
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <button type="button" wire:click="confirmarCambiarEstado({{ $estudiante->id }})"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                    <x-icono :nombre="$estudiante->activo ? 'prohibido' : 'refrescar'" class="h-4 w-4" />
                                    {{ $estudiante->activo ? 'Desactivar' : 'Reactivar' }}
                                </button>
                                <hr class="my-1 border-arena-medio dark:border-stone-700">
                                <button type="button" wire:click="confirmarEliminarCuenta({{ $estudiante->id }})"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-stone-700">
                                    <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                </button>
                            </x-slot>
                        </x-dropdown>
                    @endif
                </div>
            </div>
        </x-gestion.tarjeta>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-gestion.tarjeta titulo="Datos personales">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">DNI</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->dni ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Correo institucional</dt><dd class="truncate font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->email }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Correo personal</dt><dd class="truncate font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->correo_personal ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Celular</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->celular ?? 'sin registrar' }}</dd></div>
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-stone-500 dark:text-stone-400">Edad</dt>
                        <dd class="font-medium text-stone-800 dark:text-stone-100">
                            @if ($estudiante->edad()) {{ $estudiante->edad() }} años ({{ $estudiante->anio_nacimiento }}) @else sin registrar @endif
                        </dd>
                    </div>
                    <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Cuenta creada</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->created_at->formatoFecha() }}</dd></div>
                </dl>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Apoderado">
                @if ($estudiante->apoderado_nombre || $estudiante->apoderado_celular)
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Nombre</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->apoderado_nombre ?? 'sin registrar' }}</dd></div>
                        <div class="flex items-center justify-between gap-3"><dt class="text-stone-500 dark:text-stone-400">Celular</dt><dd class="font-medium text-stone-800 dark:text-stone-100">{{ $estudiante->apoderado_celular ?? 'sin registrar' }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-stone-500">Sin datos de apoderado registrados.</p>
                @endif
            </x-gestion.tarjeta>
        </div>

        <x-gestion.tarjeta titulo="Cursos actuales">
            @if ($cursosConMetricas->isEmpty())
                <p class="text-sm text-stone-500">Sin cursos matriculados actualmente.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-arena-medio/60 dark:border-stone-700">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-arena-claro/40 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:bg-stone-700/40 dark:text-stone-400">
                            <tr>
                                <th class="px-3 py-2">Curso</th>
                                <th class="px-3 py-2 text-right">Progreso</th>
                                <th class="px-3 py-2 text-right">Nota final</th>
                                <th class="px-3 py-2 text-right">Condición</th>
                                <th class="px-3 py-2 text-right">Asistencia</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($cursosConMetricas as $m)
                                @php $condicion = $condiciones[$m['condicion']] ?? $condiciones['sin_notas']; @endphp
                                <tr wire:key="metrica-curso-{{ $m['curso']->id }}">
                                    <td class="px-3 py-2">
                                        <a href="{{ route('cursos.show', $m['curso']) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $m['curso']->nombre }}</a>
                                    </td>
                                    <td class="px-3 py-2 text-right text-stone-700 dark:text-stone-300">{{ $m['progreso'] }}%</td>
                                    <td class="px-3 py-2 text-right text-stone-700 dark:text-stone-300">
                                        {{ $m['nota_final'] !== null ? $m['nota_final'] : ($m['tiene_esquema'] ? '—' : 'sin esquema') }}
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold {{ $condicion['clase'] }}">{{ $condicion['label'] }}</td>
                                    <td class="px-3 py-2 text-right text-stone-700 dark:text-stone-300">{{ $m['asistencia'] !== null ? $m['asistencia'].'%' : '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-gestion.tarjeta>

        @if ($proximasEntregas->isNotEmpty())
            <x-gestion.tarjeta titulo="Tareas y quizzes pendientes" descripcion="{{ $totalPendientes }} actividades sin entregar en total.">
                <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($proximasEntregas as $p)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm" wire:key="pendiente-{{ $loop->index }}">
                            <div class="min-w-0">
                                <a href="{{ $p['url'] }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $p['titulo'] }}</a>
                                <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ $p['curso']->nombre }}</p>
                            </div>
                            @if ($p['fecha'])
                                <span class="shrink-0 text-xs {{ $p['atrasado'] ? 'font-medium text-terracota-oscuro' : 'text-stone-500' }}">
                                    {{ $p['atrasado'] ? 'Venció' : 'Vence' }} {{ $p['fecha']->formatoFecha() }}
                                </span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @endif

        @if ($historial->isNotEmpty())
            <x-gestion.tarjeta titulo="Historial de cursos">
                <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($historial as $h)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5 text-sm" wire:key="historial-{{ $loop->index }}">
                            <div class="min-w-0">
                                <a href="{{ route('cursos.show', $h['curso']) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $h['curso']->nombre }}</a>
                                @if ($h['motivo'] === 'baja' && $h['dado_de_baja_por'])
                                    <p class="text-xs text-stone-500 dark:text-stone-400">Dado de baja por {{ $h['dado_de_baja_por']->name }}</p>
                                @endif
                            </div>
                            <div class="flex items-center gap-2">
                                @if ($h['motivo'] === 'finalizado')
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-arena-claro px-2.5 py-0.5 text-xs font-medium text-stone-600 dark:bg-stone-700 dark:text-stone-300">
                                        Finalizado
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                        Dado de baja
                                    </span>
                                @endif
                                <span class="text-xs text-stone-500 dark:text-stone-400">{{ $h['fecha']?->formatoFecha() ?? 'sin fecha' }}</span>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @endif
    </div>

    @if ($mostrarModalEditar)
        @include('livewire.usuarios._modal-editar')
    @endif

    @if ($mostrarConfirmacion)
        @include('livewire.usuarios._modal-confirmar')
    @endif
</div>
