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
            </div>
        </li>
    @endforeach
</ul>
