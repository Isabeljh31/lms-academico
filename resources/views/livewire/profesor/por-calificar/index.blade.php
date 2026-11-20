@php
    // Por calificar del Profesor (maqueta "PorCalificar-final"), con la paleta .tema-profesor del layout.
    $Index = \App\Livewire\Profesor\PorCalificar\Index::class;
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $colorUrgencia = ['alta' => 'text-[var(--err700)]', 'media' => 'text-[var(--warn800)]', 'baja' => 'text-[var(--p700)]'];
    $puntoUrgencia = ['alta' => 'bg-[#E0505B]', 'media' => 'bg-[#F0B73A]', 'baja' => 'bg-[#8CC21B]'];
    $sombra = 'shadow-[0_1px_2px_rgba(9,59,59,0.06),0_4px_14px_rgba(9,59,59,0.04)]';
    $interruptor = fn (bool $activo) => 'relative inline-flex h-5 w-[34px] shrink-0 rounded-full transition-colors motion-reduce:transition-none '.($activo ? 'bg-[var(--p700)]' : 'bg-[#E0D8C8]');
    $perilla = fn (bool $activo) => 'absolute top-[3px] h-3.5 w-3.5 rounded-full bg-white transition-all motion-reduce:transition-none '.($activo ? 'right-[3px]' : 'left-[3px]');
    $colorCurso = fn (int $cursoId) => $colores[$cursoId] ?? \App\Services\Calendario\ColoresCursos::NEUTRO;
    $alDia = $totalPorCalificar === 0;
@endphp

{{-- Contenedor como /cursos: hasta 1480 px de contenido + 40 px por lado, centrado en la ventana. --}}
<div class="mx-[calc(50%-min(780px,50vw))] flex flex-col px-4 sm:px-6 lg:px-10"
    x-data="{
        densidad: 'compacta',
        enfocarBuscador(evento) {
            if (evento.key !== '/' || evento.ctrlKey || evento.metaKey || evento.altKey) return;
            if (evento.target.closest('input, textarea, select, [contenteditable=true]')) return;
            if (! this.$refs.buscador) return;
            evento.preventDefault();
            this.$refs.buscador.focus();
        },
    }"
    x-on:keydown.window="enfocarBuscador($event)">

    {{-- Cabecera: la cifra es la misma que la insignia "Por calificar" de la barra inferior. --}}
    <section aria-label="Resumen de entregas por calificar"
        class="flex flex-wrap items-center gap-7 rounded-[22px] bg-[var(--p900)] px-8 py-[22px] text-white">
        <div class="min-w-0 flex-1">
            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-[var(--sec2)]">Por calificar</p>
            <h1 class="mb-1 mt-1.5 text-[30px] font-extrabold leading-tight tracking-[-0.02em] xl:whitespace-nowrap">
                @if ($alDia)
                    Estás al día
                @else
                    <span class="text-[var(--lime)]">{{ $totalPorCalificar }}</span> {{ $totalPorCalificar === 1 ? 'entrega por calificar' : 'entregas por calificar' }}
                @endif
            </h1>
            <p class="text-sm text-[#E0D8C8]">
                @if ($alDia)
                    No tienes entregas por calificar.
                @else
                    @if ($urgentes > 0)<b class="font-bold text-[var(--lime)]">{{ $urgentes }} {{ $urgentes === 1 ? 'lleva' : 'llevan' }} más de 7 días</b> esperando tu nota. @endif Empieza por las más antiguas.
                @endif
            </p>
        </div>
        <dl class="flex">
            <div class="flex min-w-24 flex-col px-[22px] first:pl-0">
                <dt class="order-2 text-xs text-[#E0D8C8]">Por calificar</dt>
                <dd class="order-1 text-[26px] font-extrabold leading-tight tabular-nums">{{ $totalPorCalificar }}</dd>
            </div>
            <div class="flex min-w-24 flex-col border-l border-white/20 px-[22px]">
                <dt class="order-2 text-xs text-[#E0D8C8]">Urgentes (+7 días)</dt>
                <dd class="order-1 text-[26px] font-extrabold leading-tight tabular-nums text-[#FFD96B]">{{ $urgentes }}</dd>
            </div>
            <div class="flex min-w-24 flex-col border-l border-white/20 px-[22px] pr-0">
                <dt class="order-2 text-xs text-[#E0D8C8]">Cursos con pendientes</dt>
                <dd class="order-1 whitespace-nowrap text-[26px] font-extrabold leading-tight tabular-nums">{{ $cursosConPendientes }} de {{ $cursosActivos->count() }}</dd>
            </div>
        </dl>
    </section>

    <div class="mt-4 empty:hidden"><x-gestion.alertas /></div>

    @if ($alDia && ! $incluirCalificadas)
        <section class="mt-5 flex flex-col items-center gap-3 rounded-[18px] bg-white px-6 py-12 text-center {{ $sombra }}">
            <span class="grid h-12 w-12 place-items-center rounded-xl bg-[var(--ok100)] text-[var(--ok900)]">
                <x-icono nombre="check-circulo" class="h-6 w-6" />
            </span>
            <h2 class="text-lg font-bold text-[var(--p900)]">Estás al día, no tienes entregas por calificar</h2>
            <p class="max-w-md text-sm text-[#57524A]">Cuando tus estudiantes entreguen tareas aparecerán aquí, empezando por las que más esperan. Crear o editar tareas se hace en Actividades, dentro de cada curso.</p>
            @if ($hayCalificadas)
                <button type="button" wire:click="$set('incluirCalificadas', true)"
                    class="inline-flex h-9 items-center rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p700)] hover:bg-[var(--mint)] {{ $enfoque }}">Ver notas puestas</button>
            @endif
        </section>
    @else
        <div class="mt-5 grid grid-cols-1 items-start gap-5 lg:grid-cols-[392px_minmax(0,1fr)]">
            {{-- Izquierda: tareas con pendientes --}}
            <aside aria-label="Tareas con entregas por calificar" class="overflow-hidden rounded-[18px] bg-white {{ $sombra }}">
                <div class="border-b border-[#ECE6DA] px-3.5 pb-3 pt-3.5">
                    <label class="mb-2.5 flex h-10 items-center gap-2.5 rounded-[10px] bg-[var(--mint)] px-3.5 text-[#6B6559] focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-[color:var(--p700)]">
                        <x-icono nombre="lupa" class="h-[17px] w-[17px] shrink-0" />
                        <span class="sr-only">Buscar estudiante o tarea</span>
                        <input type="search" x-ref="buscador" wire:model.live.debounce.300ms="busqueda" placeholder="Buscar estudiante o tarea" autocomplete="off" aria-keyshortcuts="/"
                            class="w-full min-w-0 border-0 bg-transparent p-0 text-sm text-[var(--ink)] placeholder:text-[#6B6559] focus:outline-none focus:ring-0">
                        <kbd class="shrink-0 rounded-md border border-[#E0D8C8] bg-white px-[7px] py-0.5 font-sans text-[11px] font-semibold text-[#57524A]" aria-hidden="true">/</kbd>
                    </label>

                    @php
                        $opcionesCurso = ['' => 'Todos los cursos'] + $cursosActivos->mapWithKeys(fn ($cursoActivo) => [
                            $cursoActivo->id => ['etiqueta' => $cursoActivo->nombre, 'detalle' => $cursoActivo->codigo],
                        ])->all();
                    @endphp
                    <x-docente.desplegable wire:model.live="curso" :opciones="$opcionesCurso" etiqueta="Curso" placeholder="Todos los cursos" class="mb-2" />

                    <div class="mb-3 grid gap-2 {{ $mostrarTipo ? 'grid-cols-3' : 'grid-cols-2' }}">
                        <x-docente.desplegable wire:model.live="semana" :opciones="['' => 'Semana: todas'] + $opcionesSemana" etiqueta="Semana" placeholder="Semana: todas" />
                        <x-docente.desplegable wire:model.live="modalidad" :opciones="array_replace($Index::MODALIDADES, ['' => 'Modalidad: todas'])" etiqueta="Modalidad" placeholder="Modalidad: todas" />
                        @if ($mostrarTipo)
                            <x-docente.desplegable wire:model.live="tipo" :opciones="array_replace($Index::TIPOS, ['' => 'Tipo: todos'])" etiqueta="Tipo" placeholder="Tipo: todos" />
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        <button type="button" role="switch" aria-checked="{{ $agruparPorCurso ? 'true' : 'false' }}" wire:click="$toggle('agruparPorCurso')"
                            class="flex items-center gap-2 whitespace-nowrap rounded-lg text-[12.5px] font-semibold text-[var(--p900)] {{ $enfoque }}">
                            <span class="{{ $interruptor($agruparPorCurso) }}" aria-hidden="true"><span class="{{ $perilla($agruparPorCurso) }}"></span></span>
                            Agrupar por curso
                        </button>
                        <button type="button" role="switch" aria-checked="{{ $incluirCalificadas ? 'true' : 'false' }}" wire:click="$toggle('incluirCalificadas')"
                            class="flex items-center gap-2 whitespace-nowrap rounded-lg text-[12.5px] font-semibold text-[var(--p900)] {{ $enfoque }}">
                            <span class="{{ $interruptor($incluirCalificadas) }}" aria-hidden="true"><span class="{{ $perilla($incluirCalificadas) }}"></span></span>
                            Incluir ya calificadas
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between gap-3 px-4 pb-1.5 pt-2.5 text-xs text-[#57524A]">
                    <x-docente.desplegable wire:model.live="orden" :opciones="$Index::ORDENES" etiqueta="Ordenar" placeholder="Ordenar" variante="texto" />
                    <span class="whitespace-nowrap">{{ $agruparPorCurso ? $plural($cursosLista->count(), 'curso', 'cursos') : $plural($tareas->count(), 'tarea', 'tareas') }}</span>
                </div>

                <div class="max-h-[640px] overflow-y-auto px-2 pb-2">
                    @if ($tareas->isEmpty())
                        <p class="px-3 py-6 text-center text-[13px] text-[#57524A]">No hay entregas con esos filtros.</p>
                    @elseif ($agruparPorCurso)
                        @foreach ($cursosLista as $resumenCurso)
                            @php
                                $cursoLista = $resumenCurso['curso'];
                                $contieneSeleccion = $seleccionada && $seleccionada['tarea']->curso_id === $cursoLista->id;
                                $abiertoAlInicio = $contieneSeleccion || $resumenCurso['primera']['urgencia'] === 'alta';
                            @endphp
                            <div wire:key="curso-lista-{{ $cursoLista->id }}" x-data="{ abierto: @js($abiertoAlInicio) }" class="mt-1.5">
                                <button type="button" x-on:click="abierto = ! abierto" x-bind:aria-expanded="abierto" aria-controls="tareas-curso-{{ $cursoLista->id }}"
                                    x-bind:class="abierto && 'bg-[#F4F0E8]'"
                                    class="flex w-full items-center gap-[11px] rounded-xl px-2.5 py-2.5 text-left hover:bg-[#F4F0E8] {{ $enfoque }}">
                                    <span class="relative grid h-[34px] w-[34px] shrink-0 place-items-center rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-[#F4F0E8] text-xs font-extrabold text-[var(--p900)]" aria-hidden="true">
                                        {{ $resumenCurso['iniciales'] }}
                                        <span class="absolute -right-1 -top-1 h-[11px] w-[11px] rounded-full border-2 border-white" style="background-color: {{ $colorCurso($cursoLista->id) }}"></span>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block break-words text-sm font-bold leading-tight text-[var(--p900)]">{{ $cursoLista->nombre }}</span>
                                        <span x-show="abierto" class="text-xs text-[#57524A]">{{ $cursoLista->codigo }}</span>
                                        @if ($resumenCurso['total'] > 0)
                                            <span x-show="! abierto" x-cloak class="inline-flex items-center gap-1.5 text-xs font-semibold {{ $colorUrgencia[$resumenCurso['primera']['urgencia']] }}">
                                                <span class="h-[9px] w-[9px] rounded-full {{ $puntoUrgencia[$resumenCurso['primera']['urgencia']] }}" aria-hidden="true"></span>espera {{ $Index::textoEspera($resumenCurso['primera']['dias']) }}
                                            </span>
                                        @else
                                            <span x-show="! abierto" x-cloak class="text-xs font-semibold text-[var(--p700)]">Todo calificado</span>
                                        @endif
                                    </span>
                                    <span class="whitespace-nowrap text-[12.5px] text-[#3F3B34]"><b class="text-[15px] text-[var(--p900)]">{{ $resumenCurso['total'] }}</b> por calificar</span>
                                    <x-icono nombre="flecha-abajo" class="h-[14px] w-[14px] shrink-0 text-[#6B6559] transition-transform motion-reduce:transition-none" x-bind:class="abierto && 'rotate-180'" />
                                </button>
                                <div id="tareas-curso-{{ $cursoLista->id }}" x-show="abierto" @unless ($abiertoAlInicio) x-cloak @endunless>
                                    @foreach ($resumenCurso['semanas'] as $semanaLista)
                                        @php $porcentaje = $semanaLista['total'] > 0 ? round($semanaLista['calificadas'] / $semanaLista['total'] * 100) : 0; @endphp
                                        <div wire:key="semana-{{ $cursoLista->id }}-{{ $semanaLista['clave'] }}" class="ml-11 mt-2 pr-2">
                                            <p class="flex items-center gap-1.5 text-[11.5px] font-bold text-[#57524A]">
                                                @if ($semanaLista['completa'])
                                                    <x-icono nombre="check-circulo" class="h-[13px] w-[13px] shrink-0 text-[var(--p700)]" />
                                                @endif
                                                <span>{{ $semanaLista['etiqueta'] }}</span>
                                                <span class="font-semibold">· {{ $semanaLista['calificadas'] }} de {{ $semanaLista['total'] }} calificadas</span>
                                            </p>
                                            <div class="mt-1 h-1 overflow-hidden rounded-full bg-[#ECE6DA]" role="progressbar" aria-label="Avance de {{ $semanaLista['etiqueta'] }}"
                                                aria-valuemin="0" aria-valuemax="{{ $semanaLista['total'] }}" aria-valuenow="{{ $semanaLista['calificadas'] }}">
                                                <div class="h-full rounded-full bg-[#8CC21B]" style="width: {{ $porcentaje }}%"></div>
                                            </div>
                                        </div>
                                        @foreach ($semanaLista['tareas'] as $grupoTarea)
                                            @include('livewire.profesor.por-calificar._tarea-lista', ['conCurso' => false])
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        @foreach ($tareas as $grupoTarea)
                            @include('livewire.profesor.por-calificar._tarea-lista', ['conCurso' => true])
                        @endforeach
                    @endif
                </div>

                <div x-data="{ verSinPendientes: false }" class="border-t border-[#ECE6DA] bg-[#F7F4EE] px-4 py-3 text-[12.5px] text-[#57524A]">
                    <div class="flex items-center justify-between gap-3">
                        <span>Mostrando {{ $cursosLista->count() }} de {{ $plural($cursosActivos->count(), 'curso', 'cursos') }}</span>
                        @if ($cursosSinPendientes->isNotEmpty())
                            <button type="button" x-on:click="verSinPendientes = ! verSinPendientes" x-bind:aria-expanded="verSinPendientes" aria-controls="cursos-sin-pendientes"
                                class="rounded font-bold text-[var(--p700)] hover:underline {{ $enfoque }}">
                                <span x-text="verSinPendientes ? 'Ocultar cursos sin pendientes' : 'Ver cursos sin pendientes'">Ver cursos sin pendientes</span>
                            </button>
                        @endif
                    </div>
                    @if ($cursosSinPendientes->isNotEmpty())
                        <ul id="cursos-sin-pendientes" x-show="verSinPendientes" x-cloak class="mt-2 flex flex-col gap-1">
                            @foreach ($cursosSinPendientes as $cursoSinPendientes)
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $colorCurso($cursoSinPendientes->id) }}" aria-hidden="true"></span>
                                    <a href="{{ route('cursos.show', $cursoSinPendientes) }}" wire:navigate class="rounded break-words text-[var(--p900)] hover:underline {{ $enfoque }}">{{ $cursoSinPendientes->nombre }}</a>
                                    <span class="ml-auto whitespace-nowrap text-[var(--p700)]">Al día</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <p class="border-t border-[#ECE6DA] bg-[#F7F4EE] px-4 py-3 text-[12.5px] text-[#57524A]"><b class="text-[var(--p900)]">¿Quieres crear o editar tareas?</b> Eso se hace en Actividades, dentro de cada curso.</p>
            </aside>

            {{-- Derecha: entregas de la tarea elegida --}}
            <section aria-label="Entregas de la tarea" class="min-w-0">
                @if (! $seleccionada)
                    <div class="flex flex-col items-center gap-3 rounded-[18px] bg-white px-6 py-12 text-center {{ $sombra }}">
                        <span class="grid h-11 w-11 place-items-center rounded-xl bg-[var(--mint)] text-[var(--p700)]">
                            <x-icono nombre="lupa" class="h-5 w-5" />
                        </span>
                        <h2 class="text-base font-bold text-[var(--p900)]">No hay entregas con esos filtros</h2>
                        <p class="text-sm text-[#57524A]">Prueba con otra búsqueda, otro curso, otra semana u otra modalidad.</p>
                        <button type="button" wire:click="limpiarFiltros"
                            class="inline-flex h-9 items-center rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p700)] hover:bg-[var(--mint)] {{ $enfoque }}">Limpiar filtros</button>
                    </div>
                @else
                    @php
                        $tareaSel = $seleccionada['tarea'];
                        $grupal = $seleccionada['grupal'];
                        $primera = $seleccionada['primera'];
                        $totalTarea = $seleccionada['total'];
                        $desde = ($pagina - 1) * $porPagina + 1;
                        $hasta = $desde + $filasPagina->count() - 1;
                        $unidad = $grupal ? ['grupo', 'grupos'] : ['entrega', 'entregas'];
                        $avanceSel = $seleccionada['avance'];
                        $etiquetaNeutra = 'inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#F4F0E8] px-[9px] py-0.5 text-[11px] font-bold text-[#57524A]';
                        $todoCalificado = $seleccionada['completa'] && ! $incluirCalificadas;
                    @endphp
                    <header class="mb-3 flex flex-wrap items-center gap-3.5 rounded-[18px] bg-white px-5 py-4 {{ $sombra }}">
                        <span class="relative grid h-[46px] w-[46px] shrink-0 place-items-center rounded-xl border-[1.5px] border-[#E0D8C8] bg-white font-extrabold text-[var(--p900)]" aria-hidden="true">
                            {{ \App\Services\Reportes\PanelProfesor::iniciales($tareaSel->curso->nombre) }}
                            <span class="absolute -right-1 -top-1 h-3 w-3 rounded-full border-2 border-white" style="background-color: {{ $colorCurso($tareaSel->curso_id) }}"></span>
                        </span>
                        <div class="min-w-0 flex-[1_1_18rem]">
                            <p class="break-words text-[11px] font-bold uppercase tracking-[0.09em] text-[#57524A]">{{ collect([$tareaSel->curso->nombre, $tareaSel->curso->codigo, $Index::etiquetaSemana($seleccionada['semana'])])->filter()->join(' · ') }}</p>
                            <h2 class="mt-0.5 flex flex-wrap items-center gap-x-2.5 gap-y-1 text-xl font-extrabold tracking-[-0.01em] text-[var(--p900)]">
                                <span class="break-words">{{ $tareaSel->titulo }}</span>
                                <span class="{{ $etiquetaNeutra }}">
                                    <x-icono nombre="{{ $grupal ? 'usuarios' : 'persona' }}" class="h-3 w-3" />
                                    {{ $grupal ? 'Grupal' : 'Individual' }}
                                </span>
                                @if ($mostrarTipo)
                                    <span class="{{ $etiquetaNeutra }}">{{ $Index::TIPOS[$seleccionada['tipo']] }}</span>
                                @endif
                            </h2>
                            <p class="mt-1.5 text-[13.5px] text-[#3F3B34]">
                                @if ($totalTarea > 0)
                                    <b class="text-[var(--p900)]">{{ $plural($totalTarea, $unidad[0], $unidad[1]) }} por calificar</b>
                                    @if ($seleccionada['urgentes'] > 0)
                                        · <span class="inline-flex items-center gap-1.5 whitespace-nowrap font-semibold text-[var(--err700)]"><span class="h-[9px] w-[9px] rounded-full bg-[#E0505B]" aria-hidden="true"></span>{{ $seleccionada['urgentes'] === $totalTarea && $totalTarea > 1 ? "los {$totalTarea} llevan" : ($seleccionada['urgentes'] === 1 ? '1 lleva' : $seleccionada['urgentes'].' llevan') }} más de 7 días</span>
                                    @endif
                                    · la más antigua espera {{ $Index::textoEspera($primera['dias']) }} ·
                                @endif
                                {{ $avanceSel['calificadas'] }} de {{ $avanceSel['total'] }} {{ $grupal ? ($avanceSel['total'] === 1 ? 'grupo calificado' : 'grupos calificados') : ($avanceSel['total'] === 1 ? 'calificada' : 'calificadas') }}
                            </p>
                        </div>
                        <a href="{{ route('cursos.tareas.show', [$tareaSel->curso_id, $tareaSel->id]) }}" wire:navigate
                            class="inline-flex items-center gap-[5px] whitespace-nowrap rounded text-[12.5px] font-bold text-[var(--p700)] hover:underline {{ $enfoque }}">
                            Abrir tarea
                            <svg class="h-[13px] w-[13px]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M7 17L17 7M8 7h9v9" /></svg>
                        </a>
                        @if ($totalTarea > 0)
                            <button type="button" wire:click="abrirCalificar({{ $primera['entrega']->id }})"
                                aria-label="Calificar la más antigua de {{ $tareaSel->titulo }}"
                                class="inline-flex h-11 items-center gap-2 whitespace-nowrap rounded-xl bg-[var(--p900)] px-[18px] text-sm font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                                Calificar la más antigua
                                <x-icono nombre="flecha-derecha" class="h-[15px] w-[15px]" />
                            </button>
                        @endif
                    </header>

                    @if ($todoCalificado)
                        <div class="flex flex-col items-center gap-3 rounded-[18px] bg-white px-6 py-12 text-center {{ $sombra }}">
                            <span class="grid h-12 w-12 place-items-center rounded-xl bg-[var(--ok100)] text-[var(--ok900)]">
                                <x-icono nombre="check-circulo" class="h-6 w-6" />
                            </span>
                            <h3 class="text-base font-bold text-[var(--p900)]">Todo calificado en esta tarea ✓</h3>
                            <p class="text-sm text-[#57524A]">No quedan entregas por calificar. Puedes revisar las notas puestas.</p>
                            <button type="button" wire:click="$set('incluirCalificadas', true)"
                                class="inline-flex h-9 items-center rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p700)] hover:bg-[var(--mint)] {{ $enfoque }}">Ver notas</button>
                        </div>
                    @else

                    <div class="mb-3 flex flex-wrap items-center justify-between gap-3.5 rounded-[14px] bg-white px-3.5 py-2.5 shadow-[0_1px_2px_rgba(9,59,59,0.06)]">
                        <span class="text-[13px] text-[#57524A]">Ordenadas: la que más espera, primero{{ $incluirCalificadas ? '; las calificadas, al final' : '' }}</span>
                        <div class="flex flex-wrap items-center gap-4">
                            <button type="button" role="switch" aria-checked="{{ $siguienteAlGuardar ? 'true' : 'false' }}" wire:click="$toggle('siguienteAlGuardar')"
                                class="flex items-center gap-2 whitespace-nowrap rounded-lg text-[12.5px] text-[#57524A] {{ $enfoque }}">
                                <span class="{{ $interruptor($siguienteAlGuardar) }}" aria-hidden="true"><span class="{{ $perilla($siguienteAlGuardar) }}"></span></span>
                                Al guardar la nota, ir a la siguiente
                            </button>
                            <div class="flex rounded-[10px] bg-[var(--mint)] p-[3px]" role="group" aria-label="Densidad de la tabla">
                                <button type="button" x-on:click="densidad = 'comoda'" x-bind:aria-pressed="densidad === 'comoda'"
                                    x-bind:class="densidad === 'comoda' ? 'bg-[var(--p900)] text-white' : 'text-[#57524A] hover:text-[var(--p900)]'"
                                    class="rounded-lg px-[11px] py-[5px] text-[13px] font-semibold {{ $enfoque }}">Cómoda</button>
                                <button type="button" x-on:click="densidad = 'compacta'" x-bind:aria-pressed="densidad === 'compacta'"
                                    x-bind:class="densidad === 'compacta' ? 'bg-[var(--p900)] text-white' : 'text-[#57524A] hover:text-[var(--p900)]'"
                                    class="rounded-lg px-[11px] py-[5px] text-[13px] font-semibold {{ $enfoque }}">Compacta</button>
                            </div>
                        </div>
                    </div>

                    <div class="overflow-hidden rounded-2xl bg-white {{ $sombra }}" wire:key="tabla-{{ $tareaSel->id }}">
                        <table class="w-full table-fixed text-left">
                            <caption class="sr-only">Entregas {{ $incluirCalificadas ? 'de' : 'por calificar de' }} {{ $tareaSel->titulo }}, la que más espera primero{{ $incluirCalificadas ? '; las ya calificadas al final' : '' }}</caption>
                            <colgroup>
                                <col>
                                <col class="w-[210px]">
                                <col class="w-[140px]">
                                <col class="w-[128px]">
                            </colgroup>
                            <thead class="h-[38px] border-b border-[#ECE6DA] bg-[#F4F0E8] text-[11.5px] font-bold uppercase tracking-[0.08em] text-[#57524A]">
                                <tr>
                                    <th scope="col" class="whitespace-nowrap pl-[18px] pr-3">{{ $grupal ? 'Grupo' : 'Estudiante' }}</th>
                                    <th scope="col" class="whitespace-nowrap px-3">Entregó el</th>
                                    <th scope="col" class="whitespace-nowrap px-3">Lleva esperando</th>
                                    <th scope="col" class="pl-3 pr-[18px]"><span class="sr-only">Acción</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($filasPagina as $fila)
                                    @include('livewire.profesor.por-calificar._fila')
                                @endforeach
                            </tbody>
                        </table>
                        <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#ECE6DA] bg-[#F7F4EE] px-[18px] py-3 text-[13px] text-[#57524A]">
                            <p aria-live="polite">
                                Mostrando {{ $desde }}–{{ $hasta }} de {{ $plural($filasTarea, $unidad[0], $unidad[1]) }}.@if ($grupal) La nota de un grupo se aplica a todos sus integrantes.@endif
                            </p>
                            <div class="flex items-center gap-3.5">
                                <div class="flex items-center gap-2">
                                    <span aria-hidden="true">Por página</span>
                                    <x-docente.desplegable wire:model.live="porPagina" :opciones="array_combine($Index::POR_PAGINA, $Index::POR_PAGINA)" etiqueta="Por página" placeholder="20" variante="borde" />
                                </div>
                                @if ($paginas > 1)
                                    <nav class="flex gap-1.5" aria-label="Páginas">
                                        <button type="button" wire:click="irAPagina({{ $pagina - 1 }})" @disabled($pagina <= 1) aria-label="Página anterior"
                                            class="grid h-8 min-w-8 place-items-center rounded-lg border border-[#E0D8C8] bg-white font-semibold text-[var(--p900)] disabled:opacity-40 {{ $enfoque }}">‹</button>
                                        @foreach (range(1, $paginas) as $numero)
                                            <button type="button" wire:click="irAPagina({{ $numero }})" @if ($numero === $pagina) aria-current="page" @endif
                                                class="grid h-8 min-w-8 place-items-center rounded-lg border px-2 font-semibold {{ $numero === $pagina ? 'border-[var(--p900)] bg-[var(--p900)] text-white' : 'border-[#E0D8C8] bg-white text-[var(--p900)]' }} {{ $enfoque }}">{{ $numero }}</button>
                                        @endforeach
                                        <button type="button" wire:click="irAPagina({{ $pagina + 1 }})" @disabled($pagina >= $paginas) aria-label="Página siguiente"
                                            class="grid h-8 min-w-8 place-items-center rounded-lg border border-[#E0D8C8] bg-white font-semibold text-[var(--p900)] disabled:opacity-40 {{ $enfoque }}">›</button>
                                    </nav>
                                @endif
                            </div>
                        </div>
                    </div>
                    @endif
                @endif
            </section>
        </div>
    @endif

    @if ($mostrarModalCalificar && $entregaSeleccionada)
        <x-gestion.modal titulo="Calificar entrega" cerrar="$set('mostrarModalCalificar', false)"
            descripcion="{{ $entregaSeleccionada->tarea->titulo }} - {{ $entregaSeleccionada->grupo ? $entregaSeleccionada->grupo->nombre.' (se aplica a todo el grupo)' : $entregaSeleccionada->estudiante?->name }}">
            <div class="space-y-2 rounded-lg bg-celeste/50 p-3 text-sm dark:bg-azul-pagina-oscura">
                @if ($entregaSeleccionada->archivo_url)
                    <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($entregaSeleccionada->archivo_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-azul-oscuro hover:underline"><x-icono nombre="material" class="h-4 w-4" /> Ver archivo</a>
                @endif
                @if ($entregaSeleccionada->enlace)
                    <a href="{{ $entregaSeleccionada->enlace }}" target="_blank" rel="noopener noreferrer" class="ml-3 inline-flex items-center gap-1 text-azul-oscuro hover:underline"><x-icono nombre="enlace" class="h-4 w-4" /> Ver enlace</a>
                @endif
                @if ($entregaSeleccionada->respuesta_texto)
                    <p class="whitespace-pre-line text-stone-700 dark:text-stone-300">{{ $entregaSeleccionada->respuesta_texto }}</p>
                @endif
                @if ($entregaSeleccionada->comentario)
                    <p class="text-stone-500">Comentario: "{{ $entregaSeleccionada->comentario }}"</p>
                @endif
            </div>
            <x-gestion.campo etiqueta="Nota (0 a 20)" nombre="nota" requerido>
                <x-gestion.control id="nota" type="number" step="0.01" min="0" max="20" wire:model="nota" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Comentario para el estudiante" nombre="feedback">
                <x-gestion.control tipo="textarea" id="feedback" wire:model="feedback" />
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCalificar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardarCalificacion">Guardar calificación</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
