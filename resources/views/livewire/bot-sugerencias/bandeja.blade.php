{{-- Ruta exclusiva del rol estudiante (middleware role:estudiante en routes/bot.php),
     por eso usa la paleta fría directo, sin ternario por rol.
     Jerarquía: las pendientes (requieren acción) van arriba y destacadas; las
     resueltas, abajo y discretas. Cada tarjeta lleva el color de su curso, el
     mismo del Calendario (App\Services\Calendario\ColoresCursos). --}}
@php
    $tarjeta = 'bg-white dark:bg-azul-superficie-oscura rounded-xl border border-celeste dark:border-azul-borde-oscuro';
    $enlace = 'inline-flex items-center gap-1.5 text-sm font-semibold text-azul-oscuro hover:underline dark:text-azul-fondo';
@endphp

<div class="py-8">
    {{-- Ancho de contenedor estandarizado (max-w-6xl) para todas las vistas del estudiante. --}}
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado + aclaración de confianza --}}
        <div class="space-y-4">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold text-texto dark:text-slate-100">Propuestas del asistente</h1>
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        @if ($pendientes->isNotEmpty())
                            Tienes {{ $pendientes->count() }} {{ $pendientes->count() === 1 ? 'propuesta' : 'propuestas' }} por revisar.
                        @else
                            No tienes propuestas por revisar.
                        @endif
                    </p>
                </div>
                <a href="{{ route('evento-calendario.index') }}" wire:navigate class="{{ $enlace }}">
                    <x-icono nombre="calendario" class="h-4 w-4" /> Ir a mi calendario
                </a>
            </div>

            <div class="flex items-start gap-3 rounded-xl border border-azul-oscuro/15 bg-celeste/70 px-4 py-3 dark:border-azul-borde-oscuro dark:bg-azul-oscuro/20">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-azul-oscuro text-white">
                    <x-icono nombre="escudo" class="h-5 w-5" />
                </span>
                <div class="text-sm">
                    <p class="font-semibold text-azul-oscuro dark:text-celeste">Tú decides qué entra en tu calendario</p>
                    <p class="mt-0.5 text-texto dark:text-slate-200">
                        El bot detecta atrasos y sugiere recordatorios, pero <strong class="font-semibold">nunca cambia tu calendario sin tu aprobación.</strong>
                    </p>
                </div>
            </div>
        </div>

        @if (session('estado'))
            <div class="flex items-center gap-2 rounded-lg bg-exito-base/15 px-4 py-3 text-sm text-exito-oscuro dark:text-exito-base" role="status">
                <x-icono nombre="check-circulo" class="h-5 w-5 shrink-0" />
                {{ session('estado') }}
            </div>
        @endif

        {{-- Por revisar --}}
        <section class="space-y-3" aria-labelledby="titulo-pendientes">
            <h2 id="titulo-pendientes" class="flex items-center gap-2 text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                Por revisar
                @if ($pendientes->isNotEmpty())
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-900/30 dark:text-amber-300">{{ $pendientes->count() }}</span>
                @endif
            </h2>

            @forelse ($pendientes as $t)
                @php $propuesta = $t['propuesta']; @endphp
                <article wire:key="propuesta-{{ $propuesta->id }}"
                    class="{{ $tarjeta }} relative overflow-hidden shadow-md dark:shadow-none">
                    {{-- Barra con el color del curso --}}
                    <span class="absolute inset-y-0 left-0 w-1.5" style="background-color: {{ $t['color'] }}" aria-hidden="true"></span>

                    <div class="p-5 pl-6">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300"
                                title="{{ $t['presentacion']['etiqueta'] }}">
                                <x-icono :nombre="$t['presentacion']['icono']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <span class="inline-flex items-center gap-1.5 text-xs font-semibold" style="color: {{ $t['color'] }}">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $t['color'] }}" aria-hidden="true"></span>
                                        {{ $t['curso'] }}
                                    </span>
                                    <span class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $t['presentacion']['etiqueta'] }}</span>
                                </div>
                                <h3 class="mt-1 font-semibold text-texto dark:text-slate-100">{{ $t['tareaTitulo'] }}</h3>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $propuesta->datos_propuesta['motivo'] ?? '' }}</p>
                                @if ($t['tarea'])
                                    @can('ver', $t['tarea'])
                                        <a href="{{ route('cursos.tareas.show', [$t['tarea']->curso_id, $t['tarea']->id]) }}" wire:navigate
                                            class="mt-1 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-azul-oscuro hover:underline dark:text-slate-400">
                                            Abrir la tarea <x-icono nombre="flecha-derecha" class="h-3.5 w-3.5" />
                                        </a>
                                    @endcan
                                @endif
                            </div>
                        </div>

                        {{-- Decisión --}}
                        <div class="mt-4 rounded-lg bg-celeste/50 p-4 dark:bg-azul-borde-oscuro/40">
                            <p class="text-sm font-medium text-texto dark:text-slate-100">{{ $t['presentacion']['accion'] }}</p>
                            <div class="mt-3 flex flex-col gap-3 lg:flex-row lg:items-end">
                                <div class="w-full lg:max-w-xs">
                                    <label for="fecha-propuesta-{{ $propuesta->id }}" class="block text-xs font-medium text-slate-600 dark:text-slate-300">Fecha y hora del recordatorio</label>
                                    <div class="mt-1">
                                        <x-selector-fecha-hora id="fecha-propuesta-{{ $propuesta->id }}" wire:model="fechasAjustadas.{{ $propuesta->id }}" />
                                    </div>
                                    <x-input-error :messages="$errors->get('fechasAjustadas.'.$propuesta->id)" class="mt-1" />
                                </div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-primary-button type="button" wire:click="aceptar({{ $propuesta->id }})" wire:loading.attr="disabled" wire:target="aceptar({{ $propuesta->id }})" class="gap-1.5">
                                        <x-icono nombre="check" class="h-4 w-4" /> Aceptar
                                    </x-primary-button>
                                    <x-secondary-button wire:click="rechazar({{ $propuesta->id }})" wire:loading.attr="disabled" wire:target="rechazar({{ $propuesta->id }})" class="gap-1.5">
                                        <x-icono nombre="cerrar" class="h-4 w-4" /> Rechazar
                                    </x-secondary-button>
                                    <a href="{{ $t['enlaceCalendario'] }}" wire:navigate class="{{ $enlace }} px-2">
                                        <x-icono nombre="calendario" class="h-4 w-4" /> Ver en calendario
                                    </a>
                                </div>
                            </div>
                            @if ($t['recordatorio']->isPast())
                                <p class="mt-2 flex items-center gap-1.5 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    <x-icono nombre="reloj" class="h-3.5 w-3.5 shrink-0" /> La fecha propuesta ya pasó; elige una nueva antes de aceptar.
                                </p>
                            @endif
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Puedes ajustar la fecha antes de aceptar. Si rechazas, tu calendario no cambia.</p>
                        </div>
                    </div>
                </article>
            @empty
                <div class="{{ $tarjeta }} flex flex-col items-center px-6 py-10 text-center shadow-sm dark:shadow-none">
                    <span class="flex h-14 w-14 items-center justify-center rounded-full bg-exito-base/15 text-exito-oscuro dark:text-exito-base">
                        <x-icono nombre="check-circulo" class="h-7 w-7" />
                    </span>
                    <h3 class="mt-4 text-lg font-semibold text-texto dark:text-slate-100">Estás al día</h3>
                    <p class="mt-1 max-w-md text-sm text-slate-500 dark:text-slate-400">
                        No tienes propuestas por revisar. Si el asistente detecta una tarea vencida sin entregar, te sugerirá aquí un recordatorio para ponerte al día.
                    </p>
                    <a href="{{ route('evento-calendario.index') }}" wire:navigate
                        class="mt-5 inline-flex items-center gap-2 rounded-lg bg-azul-oscuro px-4 py-2 text-sm font-semibold text-white transition hover:bg-azul-oscuro/90">
                        <x-icono nombre="calendario" class="h-4 w-4" /> Ver mi calendario
                    </a>
                </div>
            @endforelse
        </section>

        {{-- Resueltas: discretas, con el resultado como ícono --}}
        @if ($resueltas->isNotEmpty())
            <section class="space-y-3" aria-labelledby="titulo-resueltas">
                <h2 id="titulo-resueltas" class="text-sm font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">Resueltas</h2>
                <ul class="{{ $tarjeta }} divide-y divide-celeste overflow-hidden dark:divide-azul-borde-oscuro">
                    @foreach ($resueltas as $t)
                        @php
                            $propuesta = $t['propuesta'];
                            $aceptada = $propuesta->estado === 'aprobado';
                        @endphp
                        <li wire:key="propuesta-{{ $propuesta->id }}" class="relative flex flex-col gap-2 py-3 pl-5 pr-4 sm:flex-row sm:items-center sm:gap-4">
                            <span class="absolute inset-y-2 left-0 w-1 rounded-r opacity-60" style="background-color: {{ $t['color'] }}" aria-hidden="true"></span>
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <span class="mt-0.5 shrink-0 {{ $aceptada ? 'text-exito-oscuro dark:text-exito-base' : 'text-slate-400 dark:text-slate-500' }}"
                                    title="{{ $aceptada ? 'Aceptada' : 'Rechazada' }}">
                                    <x-icono :nombre="$aceptada ? 'check-circulo' : 'x-circulo'" class="h-5 w-5" />
                                    <span class="sr-only">{{ $aceptada ? 'Aceptada' : 'Rechazada' }}</span>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate text-sm text-slate-700 dark:text-slate-200">
                                        <span class="font-medium">{{ $t['tareaTitulo'] }}</span>
                                        <span class="text-slate-400">·</span>
                                        <span class="text-xs font-semibold" style="color: {{ $t['color'] }}">{{ $t['curso'] }}</span>
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        @if ($aceptada)
                                            Aceptada el {{ $propuesta->fecha_respuesta?->formatoFechaHora() }} · recordatorio para el {{ $t['recordatorio']->formatoFechaHora() }}
                                        @else
                                            Rechazada el {{ $propuesta->fecha_respuesta?->formatoFechaHora() }} · tu calendario no cambió
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <a href="{{ $t['enlaceCalendario'] }}" wire:navigate class="shrink-0 pl-8 text-xs font-semibold text-azul-oscuro hover:underline sm:pl-0 dark:text-azul-fondo">
                                Ver en calendario
                            </a>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</div>
