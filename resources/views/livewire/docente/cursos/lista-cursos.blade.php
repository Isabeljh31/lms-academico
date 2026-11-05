@php
    // Pantalla Cursos del Profesor (mockups "Cursos" y "CursosLista"), con los tokens del Panel.
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $boton = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition active:scale-[.97] motion-reduce:transition-none motion-reduce:active:scale-100 {$enfoque}";
    $botonPrimario = "{$boton} bg-azul-oscuro text-white shadow-sm hover:bg-azul-fondo";
    $botonSecundario = "{$boton} border border-slate-200 bg-white text-texto hover:border-azul-oscuro hover:text-azul-oscuro";
    $chip = "inline-flex min-h-11 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold transition motion-reduce:transition-none {$enfoque}";
    $chipActivo = 'border-azul-oscuro bg-azul-oscuro text-white';
    $chipInactivo = 'border-slate-200 bg-white text-slate-600 hover:border-azul-oscuro hover:text-azul-oscuro';
    $segmento = "inline-flex min-h-11 items-center gap-1.5 rounded-md px-3 text-[13px] font-semibold transition motion-reduce:transition-none {$enfoque} focus-visible:ring-offset-0";

    $estadosCurso = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO;
    $modalidades = \App\Services\Reportes\CursosProfesor::MODALIDADES;
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $cierreTexto = function ($curso, array $datos): string {
        if ($curso->estaFinalizado()) {
            return 'Curso cerrado';
        }

        return $datos['cierre']['texto'] ?? 'Sin fechas';
    };
    $fechas = function ($curso, array $datos) {
        $partes = $curso->fecha_inicio
            ? app(\App\Services\Reportes\CursosProfesor::class)->fecha($curso->fecha_inicio).($curso->fecha_fin ? ' – '.app(\App\Services\Reportes\CursosProfesor::class)->fecha($curso->fecha_fin) : '')
            : 'Sin fechas definidas';

        return $partes.' · '.($datos['semanas'] === 1 ? '1 semana' : $datos['semanas'].' semanas');
    };
    $restantes = $filtrados - $cursos->count();
@endphp

<div class="mx-auto flex w-full max-w-[1200px] flex-col gap-5">
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-[1.75rem] font-bold leading-tight tracking-tight text-texto">Cursos</h1>
            <p class="mt-1 text-sm text-slate-600">Los cursos que tienes a cargo como profesor.</p>
        </div>
        <span class="inline-flex items-center whitespace-nowrap rounded-full bg-celeste px-3 py-1 text-[13px] font-bold leading-5 text-azul-oscuro">{{ $plural($total, 'curso', 'cursos') }}</span>
    </header>

    @if ($total === 0)
        <section class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-12 text-center">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-celeste text-azul-oscuro">
                <x-icono nombre="libro" class="h-6 w-6" />
            </span>
            <h2 class="text-lg font-semibold text-texto">Aún no tienes cursos a cargo</h2>
            <p class="max-w-md text-sm text-slate-600">Cuando el Supervisor te asigne un curso, como titular o como co-docente, aparecerá aquí.</p>
        </section>
    @else
        <section aria-labelledby="titulo-lista-cursos" class="flex flex-col gap-4">
            <h2 id="titulo-lista-cursos" class="sr-only">Lista de cursos</h2>

            {{-- Filtros: estado, búsqueda, orden y vista (solo con MIN_CONTROLES cursos o más) --}}
            @if ($conControles)
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por estado">
                    @foreach (\App\Livewire\Docente\Cursos\ListaCursos::ESTADOS as $clave => $etiqueta)
                        @continue($clave === 'despublicado' && $conteos[$clave] === 0 && $estado !== $clave)
                        <button type="button" wire:click="$set('estado', '{{ $clave }}')" wire:key="estado-{{ $clave }}"
                            aria-pressed="{{ $estado === $clave ? 'true' : 'false' }}"
                            class="{{ $chip }} {{ $estado === $clave ? $chipActivo : $chipInactivo }}">
                            {{ $etiqueta }} <span class="tabular-nums">{{ $conteos[$clave] }}</span>
                        </button>
                    @endforeach
                </div>

                <div class="flex w-full flex-wrap items-center gap-3 lg:w-auto lg:flex-1 lg:flex-nowrap lg:justify-end">
                    <label class="flex min-h-11 min-w-0 flex-1 basis-60 items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 text-slate-500 focus-within:border-azul-oscuro focus-within:ring-[3px] focus-within:ring-cielo-claro lg:max-w-xs">
                        <x-icono nombre="lupa" class="h-[18px] w-[18px] shrink-0" />
                        <span class="sr-only">Buscar curso por nombre o código</span>
                        <input type="search" wire:model.live.debounce.350ms="busqueda" placeholder="Buscar por nombre o código" autocomplete="off"
                            class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-texto placeholder:text-slate-500 focus:outline-none focus:ring-0">
                    </label>

                    <label class="flex min-h-11 items-center gap-1 rounded-lg border border-slate-200 bg-white pl-3 text-sm font-semibold text-texto focus-within:border-azul-oscuro focus-within:ring-[3px] focus-within:ring-cielo-claro">
                        <span class="whitespace-nowrap">Ordenar:</span>
                        <select wire:model.live="orden" class="min-h-11 rounded-lg border-0 bg-transparent py-0 pl-1 pr-8 text-sm font-semibold text-texto focus:outline-none focus:ring-0">
                            @foreach (\App\Livewire\Docente\Cursos\ListaCursos::ORDENES as $clave => $etiqueta)
                                <option value="{{ $clave }}">{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="inline-flex gap-0.5 rounded-lg border border-slate-200 bg-white p-0.5" role="group" aria-label="Vista">
                        <button type="button" wire:click="$set('vista', 'cuadricula')" aria-pressed="{{ $vistaActual === 'cuadricula' ? 'true' : 'false' }}"
                            class="{{ $segmento }} {{ $vistaActual === 'cuadricula' ? 'bg-celeste text-azul-oscuro' : 'text-slate-600 hover:bg-fondo' }}">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" /></svg>
                            Cuadrícula
                        </button>
                        <button type="button" wire:click="$set('vista', 'lista')" aria-pressed="{{ $vistaActual === 'lista' ? 'true' : 'false' }}"
                            class="{{ $segmento }} {{ $vistaActual === 'lista' ? 'bg-celeste text-azul-oscuro' : 'text-slate-600 hover:bg-fondo' }}">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5" /></svg>
                            Lista
                        </button>
                    </div>
                </div>
            </div>
            @endif

            @if ($filtrados === 0)
                <div class="flex flex-col items-center gap-3 rounded-xl border border-dashed border-slate-200 bg-white px-6 py-10 text-center">
                    <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-celeste text-azul-oscuro">
                        <x-icono nombre="lupa" class="h-5 w-5" />
                    </span>
                    <h3 class="text-base font-semibold text-texto">Ningún curso coincide con el filtro</h3>
                    <p class="text-sm text-slate-600">Prueba con otro nombre o código, o quita los filtros para ver todos tus cursos.</p>
                    <button type="button" wire:click="limpiarFiltros" class="{{ $botonSecundario }}">Limpiar filtros</button>
                </div>
            @elseif ($vistaActual === 'cuadricula')
                {{-- 1 o 2 cursos: tarjeta ancha (datos a la izquierda; "Para hacer" a la derecha). 3 o más: cuadrícula con un solo aviso. --}}
                <ul class="{{ $tarjetaAncha ? 'flex flex-col' : 'grid grid-cols-[repeat(auto-fit,minmax(min(100%,19rem),1fr))] items-stretch' }} gap-4" aria-label="Cursos en cuadrícula">
                    @foreach ($cursos as $curso)
                        @php
                            $datos = $resumen[$curso->id];
                            [$estadoTexto, $estadoClase] = $estadosCurso[$curso->estado] ?? [ucfirst($curso->estado), 'bg-celeste text-slate-600'];
                        @endphp
                        <li wire:key="tarjeta-{{ $curso->id }}" class="flex">
                            <article class="flex w-full flex-col overflow-hidden rounded-xl border border-t-4 border-celeste bg-white shadow-sm transition-shadow hover:shadow-lg hover:shadow-texto/10 motion-reduce:transition-none {{ $tarjetaAncha ? 'lg:flex-row' : '' }}"
                                style="border-top-color: {{ $datos['color'] }}" aria-labelledby="curso-{{ $curso->id }}-nombre">
                                <div class="flex min-w-0 flex-1 flex-col">
                                    <div class="flex items-start gap-3 px-4 pt-4">
                                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-sm font-bold text-white" style="background-color: {{ $datos['color'] }}" aria-hidden="true">{{ $datos['iniciales'] }}</span>
                                        <div class="min-w-0 flex-1">
                                            <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="rounded-md text-texto hover:text-azul-oscuro {{ $enfoque }}">
                                                <h3 id="curso-{{ $curso->id }}-nombre" class="break-words text-base font-semibold leading-snug">{{ $curso->nombre }}</h3>
                                            </a>
                                            @if ($datos['codigo'])
                                                <p class="break-words text-[13px] text-slate-500">{{ $datos['codigo'] }}</p>
                                            @endif
                                        </div>
                                        <span class="{{ $pastilla }} {{ $estadoClase }}">{{ $estadoTexto }}</span>
                                    </div>

                                    <div class="flex flex-col gap-1.5 px-4 pt-3 text-[13px] text-slate-600">
                                        <p class="flex items-start gap-2 leading-snug">
                                            <x-icono nombre="usuarios" class="mt-px h-4 w-4 shrink-0" />
                                            <span class="min-w-0 break-words"><span class="sr-only">Profesores: </span>Prof. {{ $datos['docentes'] }}</span>
                                        </p>
                                        <p class="flex items-start gap-2 leading-snug">
                                            <x-icono nombre="calendario" class="mt-px h-4 w-4 shrink-0" />
                                            <span class="min-w-0">{{ $fechas($curso, $datos) }}</span>
                                        </p>
                                        @if ($curso->modalidad)
                                            <p><span class="{{ $pastilla }} bg-celeste text-slate-600">{{ $modalidades[$curso->modalidad] ?? ucfirst($curso->modalidad) }}</span></p>
                                        @endif
                                    </div>

                                    {{-- Una sola barra de avance y, debajo, la semana actual con sus fechas. --}}
                                    <div class="px-4 pt-3">
                                        <div class="mb-1.5 flex justify-between gap-2 text-[13px]">
                                            <span class="font-semibold text-texto">{{ $datos['avance']['texto'] }}{{ $datos['avance']['estado'] === 'en_curso' && $datos['avance']['detalle'] ? ' · '.$datos['avance']['detalle'] : '' }}</span>
                                            @if ($datos['avance']['estado'] !== 'en_curso' && $datos['avance']['detalle'])
                                                <span class="whitespace-nowrap text-slate-500">{{ $datos['avance']['detalle'] }}</span>
                                            @endif
                                        </div>
                                        <div class="h-2 rounded-full bg-celeste" role="progressbar" aria-label="Avance del curso {{ $curso->nombre }}"
                                            aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
                                            <div class="h-2 rounded-full" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $datos['color'] }}"></div>
                                        </div>
                                        @if ($datos['semana_actual'])
                                            <p class="mt-1.5 text-[13px] text-slate-500">{{ $datos['semana_actual'] }}</p>
                                        @endif
                                    </div>

                                    <div class="px-4 pt-3">@include('livewire.docente.cursos._datos-tarjeta')</div>

                                    {{-- Tarjeta normal: un solo aviso, con la misma prioridad que "Para hacer". --}}
                                    @if (! $tarjetaAncha && $datos['aviso'])
                                        <p class="mx-4 mt-3 flex items-start gap-2 rounded-lg bg-amber-100 px-2.5 py-2 text-[13px] font-medium leading-snug text-amber-800">
                                            <x-icono nombre="alerta" class="mt-px h-4 w-4 shrink-0" />
                                            <span>{{ $datos['aviso'] }}</span>
                                        </p>
                                    @endif

                                    <div class="mt-auto flex flex-wrap gap-2 px-4 pb-4 pt-3">
                                        <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="{{ $botonPrimario }}" aria-label="Abrir curso {{ $curso->nombre }}">Abrir curso</a>
                                        <a href="{{ route('cursos.actividades', $curso) }}" wire:navigate class="{{ $botonSecundario }}" aria-label="Actividades de {{ $curso->nombre }}">Actividades</a>
                                        @if (! $tarjetaAncha && $datos['pendientes'] > 0)
                                            <a href="{{ route('docente.por-calificar') }}" wire:navigate class="{{ $botonSecundario }}"
                                                aria-label="Calificar {{ $plural($datos['pendientes'], 'entrega', 'entregas') }} de {{ $curso->nombre }}">Calificar {{ $datos['pendientes'] }}</a>
                                        @endif
                                    </div>
                                </div>

                                @if ($tarjetaAncha)
                                    {{-- "Para hacer": hasta 3 acciones, la más urgente primero, cada una con su enlace. --}}
                                    <section aria-labelledby="para-hacer-{{ $curso->id }}" class="flex flex-col border-t border-celeste bg-fondo/60 px-4 py-4 lg:w-[42%] lg:border-l lg:border-t-0">
                                        <h4 id="para-hacer-{{ $curso->id }}" class="text-xs font-semibold uppercase tracking-wide text-slate-500">Para hacer</h4>
                                        @if (empty($datos['para_hacer']))
                                            <p class="mt-3 flex items-center gap-2 text-sm font-semibold text-exito-oscuro">
                                                <x-icono nombre="check-circulo" class="h-5 w-5 shrink-0" /> Todo al día
                                            </p>
                                        @else
                                            <ul class="mt-2 divide-y divide-celeste">
                                                @foreach ($datos['para_hacer'] as $item)
                                                    <li class="flex flex-wrap items-center justify-between gap-x-3 gap-y-2 py-2.5" wire:key="hacer-{{ $curso->id }}-{{ $loop->index }}">
                                                        <span class="flex min-w-0 flex-1 basis-48 items-start gap-2 text-[13px] font-medium leading-snug text-texto">
                                                            <x-icono :nombre="$item['tipo'] === 'calificar' ? 'check-circulo' : ($item['tipo'] === 'evaluacion' ? 'tarea' : 'calendario')"
                                                                class="mt-px h-4 w-4 shrink-0 {{ $item['tipo'] === 'calificar' ? 'text-azul-oscuro' : 'text-amber-800' }}" />
                                                            <span class="min-w-0 break-words">{{ $item['texto'] }}</span>
                                                        </span>
                                                        <a href="{{ $item['url'] }}" wire:navigate class="{{ $botonSecundario }} px-3 text-[13px]"
                                                            aria-label="{{ $item['accion'] }}: {{ $item['texto'] }} ({{ $curso->nombre }})">{{ $item['accion'] }}</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </section>
                                @endif
                            </article>
                        </li>
                    @endforeach
                </ul>
            @else
                @php $columnas = 'lg:grid-cols-[minmax(0,2.6fr)_7rem_minmax(0,1.6fr)_6.5rem_8.5rem_minmax(0,1.4fr)_5.5rem]'; @endphp
                <div class="overflow-hidden rounded-xl border border-celeste bg-white shadow-sm">
                    <div class="hidden gap-4 border-b border-celeste bg-fondo px-5 py-3 text-xs font-semibold text-slate-500 lg:grid {{ $columnas }}" aria-hidden="true">
                        <span>Curso y sección</span>
                        <span>Estado</span>
                        <span>Avance</span>
                        <span>Matriculados</span>
                        <span>Pendiente</span>
                        <span>Siguiente fecha límite</span>
                        <span></span>
                    </div>
                    <ul aria-label="Cursos en lista">
                        @foreach ($cursos as $curso)
                            @php
                                $datos = $resumen[$curso->id];
                                [$estadoTexto, $estadoClase] = $estadosCurso[$curso->estado] ?? [ucfirst($curso->estado), 'bg-celeste text-slate-600'];
                            @endphp
                            <li wire:key="fila-{{ $curso->id }}"
                                class="grid grid-cols-2 items-center gap-x-4 gap-y-3 border-t border-celeste px-5 py-3 transition first:border-t-0 hover:bg-fondo motion-reduce:transition-none sm:grid-cols-3 {{ $columnas }}">
                                <a href="{{ route('cursos.show', $curso) }}" wire:navigate
                                    class="group col-span-2 flex min-w-0 items-center gap-3 rounded-md sm:col-span-3 lg:col-span-1 {{ $enfoque }}">
                                    <span class="h-9 w-1.5 shrink-0 rounded-full" style="background-color: {{ $datos['color'] }}" aria-hidden="true"></span>
                                    <span class="min-w-0">
                                        <span class="block break-words text-[15px] font-semibold text-texto group-hover:text-azul-oscuro">{{ $curso->nombre }}</span>
                                        @if ($datos['codigo'])
                                            <span class="block truncate text-[13px] text-slate-500">{{ $datos['codigo'] }}</span>
                                        @endif
                                    </span>
                                </a>
                                <div>
                                    <span class="block text-xs text-slate-500 lg:sr-only">Estado</span>
                                    <span class="{{ $pastilla }} {{ $estadoClase }}">{{ $estadoTexto }}</span>
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-xs text-slate-500 lg:sr-only">Avance</span>
                                    <span class="mb-1.5 block text-[13px] font-semibold text-texto">{{ $datos['avance']['texto'] }}</span>
                                    <span class="block h-1.5 rounded-full bg-celeste" role="progressbar" aria-label="Avance del curso {{ $curso->nombre }}"
                                        aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
                                        <span class="block h-1.5 rounded-full" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $datos['color'] }}"></span>
                                    </span>
                                </div>
                                <div>
                                    <span class="block text-xs text-slate-500 lg:sr-only">Matriculados</span>
                                    <span class="text-[15px] font-semibold tabular-nums text-imperial">{{ $curso->inscripciones_activas_count }}{{ $curso->cupo_maximo ? '/'.$curso->cupo_maximo : '' }}</span>
                                </div>
                                <div>
                                    <span class="block text-xs text-slate-500 lg:sr-only">Pendiente</span>
                                    @if ($datos['pendientes'] > 0)
                                        <a href="{{ route('docente.por-calificar') }}" wire:navigate class="{{ $pastilla }} bg-amber-100 text-amber-800 hover:underline {{ $enfoque }}"
                                            aria-label="Calificar {{ $plural($datos['pendientes'], 'entrega', 'entregas') }} de {{ $curso->nombre }}">{{ $datos['pendientes'] }} por calificar</a>
                                    @else
                                        <span class="{{ $pastilla }} bg-exito-base/15 text-exito-oscuro">Al día</span>
                                    @endif
                                </div>
                                <div class="min-w-0">
                                    <span class="block text-xs text-slate-500 lg:sr-only">Siguiente fecha límite</span>
                                    <span class="block break-words text-sm font-semibold text-imperial">{{ $cierreTexto($curso, $datos) }}</span>
                                </div>
                                <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="{{ $botonSecundario }} col-span-2 sm:col-span-1" aria-label="Abrir curso {{ $curso->nombre }}">Abrir</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($filtrados > 0 && $conControles)
                <div class="flex flex-col items-center gap-3">
                    <p class="text-[13px] text-slate-500" aria-live="polite">Mostrando {{ $cursos->count() }} de {{ $plural($filtrados, 'curso', 'cursos') }}</p>
                    @if ($restantes > 0)
                        <button type="button" wire:click="verMas" class="{{ $botonSecundario }}">
                            {{ match (true) {
                                $restantes > \App\Livewire\Docente\Cursos\ListaCursos::POR_TANDA => 'Ver '.\App\Livewire\Docente\Cursos\ListaCursos::POR_TANDA.' más (quedan '.$restantes.')',
                                $restantes === 1 => 'Ver el curso restante',
                                default => 'Ver los '.$restantes.' restantes',
                            } }}
                        </button>
                    @endif
                </div>
            @endif
        </section>
    @endif
</div>
