{{--
    Contenido por semanas del Profesor del curso: cada semana es un acordeón que
    se despliega en la misma página con sus secciones fijas. El estado abierto
    sale de $semanasAbiertas (Livewire), que Alpine cambia sin pedir al servidor;
    así guardar, eliminar o cualquier re-render no cierra la semana.

    Jerarquía visual (nivel 1): número en recuadro grande, título en negrita,
    fechas y resumen en texto secundario y un divisor entre semanas. La semana
    abierta lleva fondo celeste, número relleno y chevron girado.
--}}
@php
    $claseNumeroAbierto = 'bg-azul-oscuro text-white dark:bg-azul-fondo';
@endphp
<ul class="divide-y divide-azul-oscuro/15 dark:divide-azul-borde-oscuro">
    @foreach ($secciones['semanas'] as $seccion)
        @php
            $modulo = $seccion['modulo'];
            $numero = $seccion['semana'];
            $abiertaInicial = in_array($numero, $semanasAbiertas, true);
            $claseNumeroCerrado = $modulo
                ? 'bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo'
                : 'border border-dashed border-azul-oscuro/30 text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300';
        @endphp
        <li wire:key="semana-profesor-{{ $numero }}"
            x-data="{
                get abierta() { return (this.$wire.semanasAbiertas ?? []).map(Number).includes({{ $numero }}) },
                alternar() {
                    const otras = (this.$wire.semanasAbiertas ?? []).map(Number).filter(s => s !== {{ $numero }});
                    this.$wire.semanasAbiertas = this.abierta ? otras : [...otras, {{ $numero }}];
                },
            }"
            @class(['transition-colors motion-reduce:transition-none', 'bg-celeste/60 dark:bg-azul-borde-oscuro/30' => $abiertaInicial])
            x-bind:class="{ 'bg-celeste/60 dark:bg-azul-borde-oscuro/30': abierta }">
            <div class="flex items-center gap-2 px-4 py-4 sm:px-5">
                <button type="button" id="semana-{{ $numero }}-encabezado" x-on:click="alternar()"
                    x-bind:aria-expanded="abierta.toString()" aria-expanded="{{ $abiertaInicial ? 'true' : 'false' }}"
                    aria-controls="semana-{{ $numero }}-contenido"
                    class="group flex min-w-0 flex-1 items-center gap-4 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro focus-visible:ring-offset-2">
                    <span @class(['flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-base font-bold transition-colors motion-reduce:transition-none', $abiertaInicial ? $claseNumeroAbierto : $claseNumeroCerrado])
                        x-bind:class="{ '{{ $claseNumeroAbierto }}': abierta, '{{ $claseNumeroCerrado }}': ! abierta }">
                        {{ $numero }}
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center gap-2">
                            <span class="text-base font-bold text-texto dark:text-slate-100">
                                {{ $seccion['etiqueta'] }}@if ($modulo?->tema())<span class="font-medium text-slate-700 dark:text-slate-300">: {{ $modulo->tema() }}</span>@endif
                            </span>
                            @if ($modulo)
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $modulo->esVisibleParaEstudiante() ? 'bg-exito-base/15 text-exito-oscuro' : $claseBadgeNeutra }}">
                                    {{ $modulo->etiquetaEstadoPublicacion() }}
                                </span>
                            @endif
                        </span>
                        <span class="mt-1 block text-sm text-slate-600 dark:text-slate-300">
                            @if ($seccion['rango'])
                                {{ $seccion['rango'][0]->translatedFormat('d M') }} – {{ $seccion['rango'][1]->translatedFormat('d M') }}
                                @if ($seccion['clases'] > 0) · {{ $seccion['clases'] }} {{ $seccion['clases'] === 1 ? 'clase' : 'clases' }} @endif
                                ·
                            @endif
                            @if ($modulo)
                                {{ $modulo->lecciones_count }} {{ $modulo->lecciones_count === 1 ? 'elemento' : 'elementos' }} · {{ $modulo->tareas_count }} tareas · {{ $modulo->quizzes_count }} quizzes
                            @else
                                Sin contenido
                            @endif
                        </span>
                    </span>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-600 transition group-hover:bg-white/70 dark:text-slate-300 dark:group-hover:bg-azul-borde-oscuro">
                        <x-icono nombre="flecha-abajo" stroke-width="2" class="h-5 w-5 transition-transform duration-200 motion-reduce:transition-none {{ $abiertaInicial ? 'rotate-180' : '' }}" x-bind:class="{ 'rotate-180': abierta }" />
                    </span>
                </button>

                @if ($editable)
                    @include('livewire.compartido.cursos._menu-semana', ['modulo' => $modulo, 'numero' => $numero, 'etiqueta' => $seccion['etiqueta']])
                @endif
            </div>

            <div id="semana-{{ $numero }}-contenido" role="region" aria-labelledby="semana-{{ $numero }}-encabezado"
                x-show="abierta" @unless ($abiertaInicial) style="display: none" @endunless
                class="px-4 pb-5 sm:px-5">
                @include('livewire.compartido.cursos._secciones-leccion', ['modulo' => $modulo, 'semanaClave' => $numero])
            </div>
        </li>
    @endforeach
</ul>
