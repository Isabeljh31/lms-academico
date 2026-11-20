{{--
    "Contenido por semanas" del detalle del curso del Profesor (maqueta
    "CursoDetalle"): TODAS las semanas 1..N en orden, cada una como acordeón.
    El estado abierto sale de $semanasAbiertas (Livewire, la semana en curso
    abierta al entrar), que Alpine cambia sin pedir al servidor. Los filtros
    son del lado del navegador; sus contadores vienen del servidor
    ($detalleCurso, DetalleCursoProfesor::resumen).

    Etiquetas solo si aplican: "Esta semana", "Programada · se publica …",
    "Sin contenido" (sin lecciones, tareas ni quizzes) y "Borrador".
--}}
@use('App\Livewire\Profesor\PorCalificar\Index', 'PorCalificar')
@use('App\Services\Reportes\DetalleCursoProfesor')
@use('App\Services\Reportes\PanelProfesor')
@php
    $semanaActual = collect($detalleCurso['semanas'])->search(fn (array $datos) => $datos['momento'] === 'actual') ?: null;
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $etiqueta = 'inline-flex items-center gap-[5px] whitespace-nowrap rounded-full px-[9px] py-0.5 text-[11px] font-bold';
    $pastilla = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-[9px] py-0.5 text-[11.5px] font-bold';
    $contador = 'inline-flex items-center gap-[5px] rounded-[7px] bg-[#F4F0E8] px-2 py-0.5 text-xs text-[#3F3B34]';
    $botonContorno = "inline-flex h-8 items-center gap-1.5 whitespace-nowrap rounded-[9px] border-[1.5px] border-[#E0D8C8] bg-white px-3 text-[12.5px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque}";
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $rangoCorto = fn (array $rango) => $rango[0]->month === $rango[1]->month
        ? $rango[0]->format('d').' – '.$rango[1]->copy()->locale('es')->isoFormat('DD MMM')
        : $rango[0]->copy()->locale('es')->isoFormat('DD MMM').' – '.$rango[1]->copy()->locale('es')->isoFormat('DD MMM');
    $filtros = ['todas' => 'Todas', 'por_calificar' => 'Con entregas por calificar', 'sin_contenido' => 'Sin contenido', 'programadas' => 'Programadas'];
@endphp

<section aria-labelledby="titulo-contenido-semanas" class="rounded-[20px] bg-white pb-3.5 shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]"
    x-data="{
        filtro: 'todas',
        irActual() {
            const fila = document.getElementById('semana-{{ $semanaActual ?? 0 }}');
            if (! fila) return;
            const abiertas = (this.$wire.semanasAbiertas ?? []).map(Number);
            if (! abiertas.includes({{ $semanaActual ?? 0 }})) this.$wire.semanasAbiertas = [...abiertas, {{ $semanaActual ?? 0 }}];
            this.filtro = 'todas';
            this.$nextTick(() => fila.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' }));
        },
    }">
    <div class="flex items-start justify-between gap-4 px-6 pb-1.5 pt-[22px]">
        <div class="min-w-0">
            <h2 id="titulo-contenido-semanas" class="text-[19px] font-extrabold text-[var(--p900)]">Contenido por semanas</h2>
            <p class="mt-[3px] text-[12.5px] text-[#6B6559]">
                El curso dura {{ $plural(count($secciones['semanas']), 'semana', 'semanas') }}{{ $curso->semanas_duracion ? '' : ' (duración sugerida: el Supervisor aún no la definió)' }}.
                Las semanas sin etiqueta están publicadas; las programadas o en borrador no las ven los estudiantes.
            </p>
        </div>
        @if ($semanaActual)
            <button type="button" x-on:click="irActual()"
                class="inline-flex h-9 shrink-0 items-center gap-[7px] whitespace-nowrap rounded-[10px] border-[1.5px] border-[#E0D8C8] px-[13px] text-[12.5px] font-bold text-[var(--p700)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoque }}">
                <x-icono nombre="flecha-abajo" class="h-[15px] w-[15px]" /> Ir a la semana actual
            </button>
        @endif
    </div>

    <div class="mb-2 flex gap-[22px] border-b-[1.5px] border-[#ECE6DA] px-6 pt-1.5" role="group" aria-label="Filtrar semanas">
        @foreach ($filtros as $clave => $texto)
            <button type="button" x-on:click="filtro = '{{ $clave }}'" x-bind:aria-pressed="(filtro === '{{ $clave }}').toString()" aria-pressed="{{ $clave === 'todas' ? 'true' : 'false' }}"
                data-filtro="{{ $clave }}"
                x-bind:class="filtro === '{{ $clave }}' ? 'font-bold text-[var(--p900)] after:absolute after:inset-x-0 after:-bottom-[1.5px] after:h-[2.5px] after:rounded-sm after:bg-[var(--p900)]' : 'text-[#57524A] hover:text-[var(--p900)]'"
                class="relative flex min-h-10 items-center gap-1.5 pb-2.5 pt-2 text-[13px] {{ $enfoque }} {{ $clave === 'todas' ? 'font-bold text-[var(--p900)] after:absolute after:inset-x-0 after:-bottom-[1.5px] after:h-[2.5px] after:rounded-sm after:bg-[var(--p900)]' : 'text-[#57524A]' }}">
                {{ $texto }} <span class="rounded-full bg-[#ECE6DA] px-[7px] py-px text-[11px] font-bold">{{ $detalleCurso['filtros'][$clave] }}</span>
            </button>
        @endforeach
    </div>

    <ul aria-label="Semanas del curso">
        @foreach ($secciones['semanas'] as $seccion)
            @php
                $modulo = $seccion['modulo'];
                $numero = $seccion['semana'];
                $datos = $detalleCurso['semanas'][$numero];
                $abiertaInicial = in_array($numero, $semanasAbiertas, true);
                $esActual = $datos['momento'] === 'actual';
                $urgencia = $datos['dias'] !== null ? DetalleCursoProfesor::estiloUrgencia($datos['dias']) : null;
                $claseNumero = match ($datos['momento']) {
                    'actual' => 'bg-[var(--p900)] text-white',
                    'pasada' => 'bg-[#ECE6DA] text-[var(--p700)]',
                    'futura' => 'border-[1.5px] border-dashed border-[#E0D8C8] bg-white text-[#6B6559]',
                    default => 'bg-[var(--mint)] text-[var(--p700)]',
                };
                $primeraTarea = $seccion['tareas']->first();
                $condiciones = [
                    'por_calificar' => $datos['pendientes'] > 0,
                    'sin_contenido' => $datos['sin_contenido'],
                    'programadas' => $datos['programada'] !== null,
                ];
            @endphp
            <li id="semana-{{ $numero }}" wire:key="semana-profesor-{{ $numero }}" data-semana="{{ $numero }}" class="scroll-mt-4"
                x-show="filtro === 'todas' {{ collect($condiciones)->filter()->keys()->map(fn ($clave) => "|| filtro === '{$clave}'")->join(' ') }}"
                x-data="{
                    get abierta() { return (this.$wire.semanasAbiertas ?? []).map(Number).includes({{ $numero }}) },
                    alternar() {
                        const otras = (this.$wire.semanasAbiertas ?? []).map(Number).filter(s => s !== {{ $numero }});
                        this.$wire.semanasAbiertas = this.abierta ? otras : [...otras, {{ $numero }}];
                    },
                    abrir() { if (! this.abierta) this.alternar() },
                }">
                <div @class(['mx-3.5 rounded-[14px] border-[1.5px]', 'my-1.5 border-[#E0D8C8] bg-[#F4F0E8]' => $abiertaInicial, 'border-transparent' => ! $abiertaInicial])
                    x-bind:class="abierta ? 'my-1.5 border-[#E0D8C8] bg-[#F4F0E8]' : 'border-transparent'">
                    {{-- Encabezado clicable: el botón de abrir/cerrar cubre solo esta fila (absolute inset-0); el menú y "Agregar contenido" quedan encima (z-10). --}}
                    <div class="relative flex cursor-pointer items-center gap-3.5 rounded-[12px] px-2.5 py-[13px] hover:bg-[#F4F0E8]">
                        <span class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl text-[15px] font-extrabold {{ $claseNumero }}" aria-hidden="true">{{ $numero }}</span>
                        <div class="min-w-0 flex-1">
                            <h3 class="flex flex-wrap items-center gap-2 text-[14.5px] font-bold text-[var(--p900)]">
                                <span id="semana-{{ $numero }}-titulo">{{ $seccion['etiqueta'] }}@if ($modulo?->tema()):<span class="font-medium"> {{ $modulo->tema() }}</span>@endif</span>
                                @if ($esActual)
                                    <span class="{{ $etiqueta }} bg-[var(--p900)] text-white">Esta semana</span>
                                @endif
                                @if ($datos['programada'])
                                    <span class="{{ $etiqueta }} bg-[var(--warn200)] text-[var(--warn800)]"><x-icono nombre="reloj" class="h-3 w-3" /> Programada · se publica {{ mb_strtolower(DetalleCursoProfesor::fechaCorta($datos['programada'])) }}</span>
                                @elseif ($datos['borrador'] && ! $datos['sin_contenido'])
                                    <span class="{{ $etiqueta }} bg-[#F4F0E8] text-[#57524A]">Borrador</span>
                                @endif
                                @if ($datos['sin_contenido'])
                                    <span class="{{ $etiqueta }} bg-[var(--warn200)] text-[var(--warn800)]">Sin contenido</span>
                                @endif
                            </h3>
                            <div class="mt-1.5 flex flex-wrap items-center gap-2">
                                @if ($seccion['rango'])
                                    <span class="whitespace-nowrap text-[12.5px] text-[#6B6559]">{{ $rangoCorto($seccion['rango']) }}</span>
                                @endif
                                @if ($seccion['clases'] > 0)
                                    <span class="{{ $contador }}"><x-icono nombre="presentacion" class="h-3.5 w-3.5 text-[var(--p700)]" />{{ $plural($seccion['clases'], 'clase', 'clases') }}</span>
                                @endif
                                @if ($datos['sin_contenido'])
                                    <span class="text-[12.5px] text-[var(--warn800)]">Aún no hay material para los estudiantes</span>
                                @else
                                    @if ($modulo->lecciones_count > 0)
                                        <span class="{{ $contador }}"><x-icono nombre="material" class="h-3.5 w-3.5 text-[var(--p700)]" />{{ $plural($modulo->lecciones_count, 'elemento', 'elementos') }}</span>
                                    @endif
                                    @if ($modulo->tareas_count > 0)
                                        <span class="{{ $contador }}"><x-icono nombre="tarea" class="h-3.5 w-3.5 text-[var(--p700)]" />{{ $plural($modulo->tareas_count, 'tarea', 'tareas') }}</span>
                                    @endif
                                    @if ($modulo->quizzes_count > 0)
                                        <span class="{{ $contador }}"><x-icono nombre="quiz" class="h-3.5 w-3.5 text-[var(--p700)]" />{{ $plural($modulo->quizzes_count, 'quiz', 'quizzes') }}</span>
                                    @endif
                                @endif
                            </div>
                        </div>

                        {{-- Derecha: lo que pide atención en la semana. --}}
                        @if ($datos['pendientes'] > 0)
                            <span class="{{ $pastilla }} {{ $urgencia['pastilla'] }}" data-pendientes="{{ $datos['pendientes'] }}">
                                <span class="h-[9px] w-[9px] rounded-full {{ $urgencia['punto'] }}" aria-hidden="true"></span>{{ $datos['pendientes'] }} por calificar · {{ PorCalificar::textoEspera($datos['dias']) }}
                            </span>
                        @elseif ($datos['momento'] === 'futura' && $primeraTarea)
                            <span class="whitespace-nowrap text-[12.5px] text-[#6B6559]">Entrega {{ $primeraTarea->fecha_entrega->format('d/m') }}</span>
                        @elseif ($datos['sin_contenido'] && $editable)
                            <button type="button" x-on:click="abrir()" class="{{ $botonContorno }} relative z-10" aria-label="Agregar contenido a la {{ $seccion['etiqueta'] }}">
                                <x-icono nombre="mas" class="h-3.5 w-3.5" /> Agregar contenido
                            </button>
                        @elseif ($seccion['tareas']->isNotEmpty() && $datos['entregadas'] > 0)
                            <span class="{{ $pastilla }} bg-[var(--ok100)] text-[var(--ok900)]"><x-icono nombre="check" class="h-[13px] w-[13px]" /> Todo calificado</span>
                        @endif

                        <button type="button" x-on:click="alternar()" aria-controls="semana-{{ $numero }}-contenido"
                            x-bind:aria-expanded="abierta.toString()" aria-expanded="{{ $abiertaInicial ? 'true' : 'false' }}"
                            aria-label="Abrir o cerrar {{ $seccion['etiqueta'] }}"
                            class="absolute inset-0 cursor-pointer rounded-[12px] focus-visible:outline focus-visible:outline-[3px] focus-visible:-outline-offset-[3px] focus-visible:outline-[color:var(--p700)]"></button>
                        @if ($editable)
                            <div class="relative z-10 shrink-0">
                                @include('livewire.profesor.cursos._menu-semana', ['modulo' => $modulo, 'numero' => $numero, 'etiqueta' => $seccion['etiqueta']])
                            </div>
                        @endif
                    </div>

                    <div id="semana-{{ $numero }}-contenido" role="region" aria-labelledby="semana-{{ $numero }}-titulo"
                        x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless class="px-3.5 pb-3">
                        @include('livewire.profesor.cursos._semana-abierta')
                    </div>
                </div>
                <div class="mx-6 border-t border-[#F4F0E8]" x-show="! abierta" @if ($abiertaInicial) style="display: none" @endif aria-hidden="true"></div>
            </li>
        @endforeach
    </ul>
</section>
