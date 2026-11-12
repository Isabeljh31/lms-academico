{{--
    Contenido por semanas del detalle del curso del Profesor (mockup
    "CursoDetalle"). Cada semana es un acordeón con sus secciones fijas; el
    estado abierto sale de $semanasAbiertas (Livewire), que Alpine cambia sin
    pedir al servidor. Al entrar se abre la semana en curso (la misma regla de
    GestionaContenidoSemanal); las pasadas quedan plegadas.

    - Semana actual: número relleno y la pastilla "Semana actual".
    - Futuras sin contenido: número con borde punteado y "Programada".
    - "Sin contenido": la semana aún no tiene ningún elemento (lecciones, tareas ni quizzes).
--}}
@php
    $semanaActual = \App\Services\Reportes\CursosProfesor::semanaActual($curso, count($secciones['semanas']));
    $ahora = now();
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
@endphp
<ul aria-label="Semanas del curso">
    @foreach ($secciones['semanas'] as $seccion)
        @php
            $modulo = $seccion['modulo'];
            $numero = $seccion['semana'];
            $abiertaInicial = in_array($numero, $semanasAbiertas, true);
            $esActual = $numero === $semanaActual;
            $esFutura = ! $esActual && $seccion['rango'] && $seccion['rango'][0]->gt($ahora);
            $elementos = $modulo ? $modulo->lecciones_count + $modulo->tareas_count + $modulo->quizzes_count : 0;
            $sinContenido = $elementos === 0;
            $claseNumero = match (true) {
                $esActual => 'bg-azul-oscuro text-white',
                $esFutura && ! $modulo => 'border border-dashed border-slate-400 bg-white text-slate-500',
                default => 'bg-celeste text-azul-oscuro',
            };
        @endphp
        <li wire:key="semana-profesor-{{ $numero }}"
            x-data="{
                get abierta() { return (this.$wire.semanasAbiertas ?? []).map(Number).includes({{ $numero }}) },
                alternar() {
                    const otras = (this.$wire.semanasAbiertas ?? []).map(Number).filter(s => s !== {{ $numero }});
                    this.$wire.semanasAbiertas = this.abierta ? otras : [...otras, {{ $numero }}];
                },
            }"
            @class(['border-t border-celeste transition-colors motion-reduce:transition-none', 'bg-fondo/60' => $abiertaInicial])
            x-bind:class="{ 'bg-fondo/60': abierta }">
            <div class="flex items-center gap-1 px-4 py-2">
                <button type="button" id="semana-{{ $numero }}-encabezado" x-on:click="alternar()"
                    x-bind:aria-expanded="abierta.toString()" aria-expanded="{{ $abiertaInicial ? 'true' : 'false' }}"
                    aria-controls="semana-{{ $numero }}-contenido"
                    class="group flex min-h-11 min-w-0 flex-1 items-center gap-4 rounded-lg py-1 text-left {{ $enfoque }}">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] text-[15px] font-bold {{ $claseNumero }}">{{ $numero }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-[15px] font-semibold text-texto group-hover:text-azul-oscuro">
                                {{ $seccion['etiqueta'] }}@if ($modulo?->tema())<span class="font-medium text-slate-600">: {{ $modulo->tema() }}</span>@endif
                            </span>
                            @if ($esActual)
                                <span class="{{ $pastilla }} bg-azul-oscuro text-white">Semana actual</span>
                            @endif
                            @if ($modulo)
                                <span class="{{ $pastilla }} {{ $modulo->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-celeste text-slate-600' }}">{{ $modulo->etiquetaEstadoPublicacion() }}</span>
                            @elseif ($esFutura)
                                <span class="{{ $pastilla }} bg-celeste text-slate-600">Programada</span>
                            @endif
                            @if ($sinContenido)
                                <span class="{{ $pastilla }} bg-amber-100 text-amber-800">Sin contenido</span>
                            @endif
                        </span>
                        <span class="mt-0.5 block text-[13px] text-slate-500">
                            @if ($seccion['rango'])
                                {{ $seccion['rango'][0]->translatedFormat('d M') }} – {{ $seccion['rango'][1]->translatedFormat('d M') }}
                                @if ($seccion['clases'] > 0) · {{ $plural($seccion['clases'], 'clase', 'clases') }} @endif
                                ·
                            @endif
                            @if ($sinContenido)
                                Aún no hay material para los estudiantes
                            @else
                                {{ $plural($modulo->lecciones_count, 'elemento', 'elementos') }} · {{ $plural($modulo->tareas_count, 'tarea', 'tareas') }} · {{ $plural($modulo->quizzes_count, 'quiz', 'quizzes') }}
                            @endif
                        </span>
                    </span>
                    <x-icono nombre="flecha-abajo" class="h-5 w-5 shrink-0 text-slate-500 transition-transform duration-200 motion-reduce:transition-none {{ $abiertaInicial ? 'rotate-180' : '' }}" x-bind:class="{ 'rotate-180': abierta }" />
                </button>

                @if ($editable)
                    @include('livewire.docente.cursos._menu-semana', ['modulo' => $modulo, 'numero' => $numero, 'etiqueta' => $seccion['etiqueta']])
                @endif
            </div>

            <div id="semana-{{ $numero }}-contenido" role="region" aria-labelledby="semana-{{ $numero }}-encabezado"
                x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless
                class="px-4 pb-2 sm:pl-[4.5rem] sm:pr-4">
                @include('livewire.docente.cursos._secciones', ['modulo' => $modulo, 'semanaClave' => $numero])

                {{-- Tareas y quizzes de la semana: los mismos que el estudiante ve dentro de ella. --}}
                @if ($seccion['tareas']->isNotEmpty() || $seccion['quizzes']->isNotEmpty())
                    <section aria-labelledby="semana-{{ $numero }}-actividades-titulo" class="border-t border-celeste py-2.5">
                        <h3 id="semana-{{ $numero }}-actividades-titulo" class="flex items-center gap-2 pb-2 text-sm font-semibold text-texto">
                            Tareas y quizzes de la semana
                            <span class="rounded-full border border-celeste bg-fondo px-2 text-xs font-bold text-slate-500">{{ $seccion['tareas']->count() + $seccion['quizzes']->count() }}</span>
                        </h3>
                        <ul class="overflow-hidden rounded-lg border border-celeste bg-white">
                            @foreach ($seccion['tareas'] as $tarea)
                                <li wire:key="semana-{{ $numero }}-tarea-{{ $tarea->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-celeste px-3 py-2.5 first:border-t-0">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-celeste text-azul-oscuro" title="Tarea"><x-icono nombre="tarea" class="h-4 w-4" /></span>
                                    <div class="min-w-0 flex-1 basis-48">
                                        <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-texto">
                                            {{ $tarea->titulo }}
                                            <span class="{{ $pastilla }} {{ $tarea->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-celeste text-slate-600' }} text-[11px]">{{ $tarea->etiquetaEstadoPublicacion() }}</span>
                                        </p>
                                        <p class="text-xs text-slate-600">Tarea · {{ $tarea->estaVencida() ? 'Venció' : 'Vence' }} {{ $tarea->fecha_entrega->formatoFechaHora() }} · {{ $plural($tarea->entregas_count, 'entrega', 'entregas') }}</p>
                                    </div>
                                    <a href="{{ route('cursos.tareas.show', [$curso, $tarea]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-azul-oscuro hover:bg-celeste {{ $enfoque }}"
                                        aria-label="Ver entregas de {{ $tarea->titulo }}"><x-icono nombre="ojo" class="h-4 w-4" /> Ver entregas</a>
                                    @if ($editable)
                                        <a href="{{ route('cursos.tareas.editar', [$curso, $tarea]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-celeste hover:text-azul-oscuro {{ $enfoque }}"
                                            aria-label="Editar {{ $tarea->titulo }}"><x-icono nombre="lapiz" class="h-4 w-4" /> Editar</a>
                                    @endif
                                </li>
                            @endforeach
                            @foreach ($seccion['quizzes'] as $quiz)
                                <li wire:key="semana-{{ $numero }}-quiz-{{ $quiz->id }}" class="flex flex-wrap items-center gap-x-3 gap-y-1 border-t border-celeste px-3 py-2.5 first:border-t-0">
                                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/50 text-lavanda-oscuro" title="Quiz"><x-icono nombre="quiz" class="h-4 w-4" /></span>
                                    <div class="min-w-0 flex-1 basis-48">
                                        <p class="flex flex-wrap items-center gap-2 text-sm font-medium text-texto">
                                            {{ $quiz->tema }}
                                            <span class="{{ $pastilla }} {{ $quiz->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : 'bg-celeste text-slate-600' }} text-[11px]">{{ $quiz->etiquetaEstadoPublicacion() }}</span>
                                        </p>
                                        <p class="text-xs text-slate-600">Quiz · {{ $quiz->fecha_limite ? 'Hasta '.$quiz->fecha_limite->formatoFechaHora() : 'Sin fecha límite' }}</p>
                                    </div>
                                    @if ($editable)
                                        <a href="{{ route('cursos.quizzes.editar', [$curso, $quiz]) }}" wire:navigate class="inline-flex min-h-11 items-center gap-1 rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-celeste hover:text-azul-oscuro {{ $enfoque }}"
                                            aria-label="Editar {{ $quiz->tema }}"><x-icono nombre="lapiz" class="h-4 w-4" /> Editar</a>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif
            </div>
        </li>
    @endforeach
</ul>
