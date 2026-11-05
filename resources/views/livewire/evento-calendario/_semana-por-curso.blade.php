{{--
    Calendario del Profesor: "Actividades de la semana por curso". Vive dentro
    del x-data del calendario (wire:ignore): la semana sale de FullCalendar
    (datesSet) y las actividades de los eventos ya cargados, así que cambiar de
    semana no pide nada al servidor. Respeta "Mis cursos" y "Tipos de actividad".

    Jerarquía: 1) la semana (encabezado), 2) el curso (sección colapsable, como
    el acordeón de lecciones; el color del curso solo como punto), 3) la
    actividad (una fila compacta). `relative` contiene los sr-only.
--}}
@php
    $chipTipo = [
        'tarea' => ['Tarea', 'bg-azul-oscuro text-white dark:bg-azul-fondo'],
        'quiz' => ['Quiz', 'bg-cielo-claro text-imperial ring-1 ring-inset ring-azul-fondo/40 dark:bg-azul-borde-oscuro dark:text-cielo'],
        'evaluacion' => ['Evaluación', 'bg-celeste/60 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste'],
        'sesion' => ['Clase', 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200'],
    ];
@endphp
<section class="relative {{ $tarjeta }}" aria-labelledby="semana-por-curso-titulo">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b {{ $borde }} px-4 py-3">
        <h2 id="semana-por-curso-titulo" class="flex items-center gap-2 text-sm font-semibold {{ $titulo }}">
            <span class="flex h-7 w-7 items-center justify-center rounded-lg {{ $acentoFondo }}"><x-icono nombre="calendario" class="h-4 w-4" /></span>
            Actividades de la semana por curso
        </h2>
        <span class="flex items-center gap-3">
            {{-- Muchos cursos: abrir o cerrar todas las materias y secciones de una vez. --}}
            <span x-show="bloque.muchos" class="flex items-center gap-1 text-xs font-semibold {{ $acento }}">
                <button type="button" x-on:click="expandirTodo(true)" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Expandir todo</button>
                <span class="{{ $suave }}" aria-hidden="true">/</span>
                <button type="button" x-on:click="expandirTodo(false)" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Contraer todo</button>
            </span>
            <span class="text-xs font-medium {{ $suave }}" x-text="rangoSemana"></span>
        </span>
    </div>

    <template x-if="bloque.cursos.length === 0">
        <p class="px-4 py-3 text-sm {{ $suave }}">No hay cursos para mostrar.</p>
    </template>
    <template x-if="bloque.cursos.length > 0 && cursosDeLaSemana().length === 0">
        <p class="px-4 py-3 text-sm {{ $suave }}">Marca al menos un curso en «Mis cursos» para ver sus actividades.</p>
    </template>

    <ul class="divide-y {{ $divisor }}">
        {{-- Nivel 2: una materia. Con varias secciones lleva su encabezado ("N secciones") y debajo una fila por sección. --}}
        <template x-for="grupo in gruposDeLaSemana()" :key="grupo.clave">
            <li>
                <div x-show="grupo.varias && ! bloque.muchos" class="flex items-center gap-2 px-4 pb-1 pt-3">
                    <span class="min-w-0 truncate text-sm font-semibold {{ $titulo }}" x-text="grupo.nombre"></span>
                    <span class="shrink-0 rounded-full bg-celeste px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200" x-text="grupo.secciones.length + ' secciones'"></span>
                </div>
                {{-- Muchos cursos: la materia es colapsable y resume sus secciones y actividades. --}}
                <button type="button" x-show="bloque.muchos" x-on:click="alternarMateriaBloque(grupo.clave)"
                    :aria-expanded="materiaAbierta(grupo.clave).toString()"
                    class="flex w-full items-center gap-2 px-4 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-azul-oscuro">
                    <span class="min-w-0 truncate text-sm font-semibold {{ $titulo }}" x-text="grupo.nombre"></span>
                    <span x-show="grupo.secciones.length > 1" class="shrink-0 rounded-full bg-celeste px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200" x-text="grupo.secciones.length + ' secciones'"></span>
                    <span class="ml-auto shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="grupo.total > 0 ? @js('bg-celeste/60 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste') : @js('text-slate-500 dark:text-slate-400')"
                        x-text="grupo.total === 1 ? '1 actividad' : grupo.total + ' actividades'"></span>
                    <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200 motion-reduce:transition-none dark:text-slate-400" x-bind:class="materiaAbierta(grupo.clave) && 'rotate-180'" />
                </button>
        <div x-show="materiaAbierta(grupo.clave)">
        <ul>
        <template x-for="curso in grupo.items" :key="curso.id">
            <li>
                <button type="button" x-on:click="alternarCurso(curso.id)"
                    :id="'semana-curso-' + curso.id + '-encabezado'" :aria-controls="'semana-curso-' + curso.id + '-contenido'"
                    :aria-expanded="estaAbierto(curso.id).toString()"
                    class="group flex w-full items-center gap-3 px-4 py-3 text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-azul-oscuro"
                    :class="grupo.agrupado && 'pl-8 py-2'">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :style="'background-color: ' + curso.color" aria-hidden="true"></span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-sm font-semibold {{ $titulo }}" x-text="grupo.agrupado ? curso.seccion : curso.nombre"></span>
                        <span class="block text-xs {{ $suave }}">
                            <span x-text="curso.numero ? 'Semana ' + curso.numero : (curso.inicio ? 'Fuera del periodo del curso' : 'Sin fecha de inicio')"></span>
                            <span x-show="! grupo.agrupado && curso.codigo" x-text="' · ' + curso.codigo"></span>
                        </span>
                    </span>
                    <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium"
                        :class="curso.actividades.total > 0 ? @js('bg-celeste/60 text-azul-oscuro dark:bg-azul-oscuro/30 dark:text-celeste') : @js('text-slate-500 dark:text-slate-400')"
                        x-text="curso.actividades.total === 1 ? '1 actividad' : curso.actividades.total + ' actividades'"></span>
                    <x-icono nombre="flecha-abajo" class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200 motion-reduce:transition-none dark:text-slate-400" x-bind:class="estaAbierto(curso.id) && 'rotate-180'" />
                </button>

                <div x-show="estaAbierto(curso.id)" :id="'semana-curso-' + curso.id + '-contenido'" role="region"
                    :aria-labelledby="'semana-curso-' + curso.id + '-encabezado'" class="px-4 pb-3" :class="grupo.agrupado && 'pl-8'">
                    <template x-if="curso.actividades.total === 0">
                        <p class="py-1 pl-5 text-sm {{ $suave }}">Sin actividades esta semana.</p>
                    </template>

                    <ul class="space-y-0.5">
                        <template x-for="(fila, i) in visiblesDe(curso)" :key="fila.id">
                            <li>
                                <p x-show="fila.grupo === 'sinFecha' && (i === 0 || visiblesDe(curso)[i - 1].grupo !== 'sinFecha')"
                                    class="mt-2 px-2 pb-1 text-xs font-semibold uppercase tracking-wide {{ $suave }}">Sin fecha definida</p>
                                <a :href="fila.url"
                                    class="flex flex-wrap items-center gap-x-2 gap-y-1 rounded-md px-2 py-1.5 text-sm hover:bg-celeste dark:hover:bg-azul-borde-oscuro focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">
                                    @foreach ($chipTipo as $tipo => [$etiqueta, $clases])
                                        <span x-show="fila.tipo === '{{ $tipo }}'" class="w-20 shrink-0 rounded-md px-1.5 py-0.5 text-center text-[11px] font-bold uppercase tracking-wide {{ $clases }}">{{ $etiqueta }}</span>
                                    @endforeach
                                    <span class="min-w-0 flex-1 truncate font-medium {{ $titulo }}" x-text="fila.titulo"></span>
                                    <span x-show="fila.porCalificar > 0" class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
                                        x-text="fila.porCalificar + ' por calificar'"></span>
                                    <span x-show="fila.estado" class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium" :class="fila.estado && claseEstado(fila.estado)" x-text="fila.estado"></span>
                                    <span x-show="fila.detalle" class="shrink-0 text-xs {{ $suave }}" x-text="fila.detalle"></span>
                                    <span class="w-28 shrink-0 text-right text-xs tabular-nums {{ $suave }}" x-text="fila.fecha ?? 'Sin fecha'"></span>
                                </a>
                            </li>
                        </template>
                    </ul>

                    <button type="button" x-show="curso.actividades.total > 5" x-on:click="expandidos = { ...expandidos, [curso.id]: ! expandidos[curso.id] }"
                        class="mt-1 rounded px-2 text-xs font-semibold {{ $acento }} hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro"
                        x-text="expandidos[curso.id] ? 'Ver menos' : 'Ver todas (' + curso.actividades.total + ')'"></button>
                </div>
            </li>
        </template>
        </ul>
                {{-- Secciones de la materia sin actividades esta semana: una línea discreta con sus códigos. --}}
                <p x-show="grupo.agrupado && grupo.vacias.length > 0" class="px-4 pb-3 pl-8 text-xs {{ $suave }}"
                    :class="grupo.items.length === 0 && ! bloque.muchos && 'pt-1'"
                    x-text="'Sin actividades esta semana: ' + grupo.vacias.map(s => s.seccion).join(', ')"></p>
        </div>
            </li>
        </template>
    </ul>
</section>
