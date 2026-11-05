{{--
    Calendario del Profesor, filtro "Mis cursos" cuando alguna materia tiene
    varias secciones: agrupado por nombre del curso con sus secciones (código)
    debajo, y "Todos / Ninguno". Nombre, sección y orden salen de
    App\Services\Calendario\SeccionesProfesor (el mismo criterio de toda la
    pantalla). Misma casilla que la lista sin secciones.
--}}
@php
    $secciones = $this->secciones;
    $todosLosCursos = $this->cursos->pluck('id')->values();
@endphp
<h2 class="text-sm font-semibold {{ $titulo }}">Mis cursos</h2>
<p class="mt-0.5 text-xs {{ $suave }}">Muestra u oculta las actividades de cada sección.</p>
<div class="mt-2 flex items-center gap-1 text-xs font-semibold {{ $acento }}">
    <button type="button" x-on:click="cursosSeleccionados = @js($todosLosCursos); aplicar()" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Todos</button>
    <span class="{{ $suave }}" aria-hidden="true">/</span>
    <button type="button" x-on:click="cursosSeleccionados = []; aplicar()" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Ninguno</button>
</div>

<ul class="mt-3 space-y-2">
    @foreach ($secciones->grupos() as $grupo)
        @php $variasSecciones = $grupo['secciones']->count() > 1; @endphp
        <li>
            @if ($variasSecciones)
                <p class="flex items-baseline justify-between gap-2 px-2 text-sm font-medium {{ $titulo }}">
                    <span class="truncate">{{ $grupo['nombre'] }}</span>
                    <span class="shrink-0 text-[11px] font-normal {{ $suave }}">{{ $grupo['secciones']->count() }} secciones</span>
                </p>
            @endif
            <ul @class(['space-y-0.5', 'mt-0.5 border-l border-celeste pl-2 ml-3 dark:border-azul-borde-oscuro' => $variasSecciones])>
                @foreach ($grupo['secciones'] as $curso)
                    @php $color = $this->coloresCursos[$curso->id]; @endphp
                    <li>
                        <label class="relative flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-celeste/40 dark:hover:bg-azul-borde-oscuro/40">
                            <input type="checkbox" class="sr-only" x-on:change="alternar('cursosSeleccionados', {{ $curso->id }})" :checked="cursosSeleccionados.includes({{ $curso->id }})" checked>
                            <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition"
                                style="border-color: {{ $color }}"
                                :style="cursosSeleccionados.includes({{ $curso->id }}) ? 'background-color: {{ $color }}; border-color: {{ $color }}' : 'border-color: {{ $color }}'">
                                <x-icono nombre="check" class="h-3 w-3 text-white" x-show="cursosSeleccionados.includes({{ $curso->id }})" />
                            </span>
                            <span class="min-w-0">
                                @if ($variasSecciones)
                                    <span class="block truncate {{ $titulo }}">{{ $secciones->codigo($curso->id) }}</span>
                                @else
                                    <span class="block truncate {{ $titulo }}">{{ $curso->nombre }}</span>
                                    @if ($curso->codigo)
                                        <span class="block text-[11px] {{ $suave }}">{{ $curso->codigo }}</span>
                                    @endif
                                @endif
                            </span>
                        </label>
                    </li>
                @endforeach
            </ul>
        </li>
    @endforeach
</ul>
