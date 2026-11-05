{{--
    Calendario del Profesor, filtro "Mis cursos" con muchos cursos (más
    secciones que colores, SeccionesProfesor::MUCHOS_CURSOS): buscador por
    nombre o código, "N de M cursos visibles", atajos "Todos", "Ninguno" y
    "Con actividad esta semana", materias colapsables (cerradas por defecto
    con más de 8 secciones) y lista con scroll propio dentro de la tarjeta.
    La selección se recuerda en este navegador (localStorage) con
    "Restablecer"; sin almacenamiento disponible todo funciona igual.

    Vive en el x-data del calendario; nombres, códigos y orden llegan ya
    resueltos por SeccionesProfesor en bloque.cursos.
--}}
<div class="relative">
    <div class="flex items-baseline justify-between gap-2">
        <h2 class="text-sm font-semibold {{ $titulo }}">Mis cursos</h2>
        <span class="shrink-0 text-xs {{ $suave }}" aria-live="polite"
            x-text="cursosSeleccionados.length + ' de ' + bloque.cursos.length + ' cursos visibles'"></span>
    </div>

    <label for="buscar-curso" class="sr-only">Buscar curso por nombre o código</label>
    <div class="relative mt-2">
        <x-icono nombre="lupa" class="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
        <input id="buscar-curso" type="search" x-model="busquedaCurso" placeholder="Nombre o código" autocomplete="off"
            class="block w-full rounded-lg border border-celeste bg-white py-1.5 pl-8 pr-2 text-sm text-texto placeholder:text-slate-400 focus:border-azul-oscuro focus:outline-none focus:ring-1 focus:ring-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-100">
    </div>

    <div class="mt-2 flex flex-wrap items-center gap-x-1 gap-y-1 text-xs font-semibold {{ $acento }}">
        <button type="button" x-on:click="seleccionar(bloque.cursos.map(c => c.id))" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Todos</button>
        <span class="{{ $suave }}" aria-hidden="true">/</span>
        <button type="button" x-on:click="seleccionar([])" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Ninguno</button>
        <span class="{{ $suave }}" aria-hidden="true">/</span>
        <button type="button" x-on:click="seleccionar(vista.conActividad)" class="rounded px-1 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro">Con actividad esta semana</button>
        <template x-if="seleccionRecordada">
            <span class="ml-auto flex items-center">
                <button type="button" x-on:click="restablecerSeleccion()" class="rounded px-1 font-medium {{ $suave }} hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro"
                    title="Olvida la selección guardada en este navegador y muestra todos los cursos">Restablecer</button>
            </span>
        </template>
    </div>

    {{-- Scroll solo en la lista, dentro de la tarjeta (no estira la página). --}}
    <div class="relative mt-3 max-h-96 overflow-y-auto overscroll-contain pr-1">
        <template x-if="materiasDelFiltro().length === 0">
            <p class="px-2 py-3 text-sm {{ $suave }}">No hay cursos que coincidan con la búsqueda.</p>
        </template>
        <ul class="space-y-1">
            <template x-for="materia in materiasDelFiltro()" :key="materia.clave">
                <li>
                    <div class="flex items-center gap-1">
                        <button type="button" x-on:click="materiasFiltro = { ...materiasFiltro, [materia.clave]: ! materiaFiltroAbierta(materia.clave) }"
                            :aria-expanded="materiaFiltroAbierta(materia.clave).toString()"
                            class="flex min-w-0 flex-1 items-center gap-1.5 rounded-md px-2 py-1.5 text-left text-sm font-medium {{ $titulo }} hover:bg-celeste/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:hover:bg-azul-borde-oscuro/40">
                            <x-icono nombre="flecha-abajo" class="h-3.5 w-3.5 shrink-0 text-slate-500 transition-transform motion-reduce:transition-none dark:text-slate-400" x-bind:class="materiaFiltroAbierta(materia.clave) || '-rotate-90'" />
                            <span class="min-w-0 leading-snug" x-text="materia.nombre"></span>
                        </button>
                        <span class="shrink-0 text-[11px] tabular-nums {{ $suave }}"
                            x-text="materia.secciones.filter(s => cursosSeleccionados.includes(s.id)).length + '/' + materia.secciones.length"></span>
                        {{-- Casilla de la materia: marca o desmarca todas sus secciones (marcada, parcial o vacía). --}}
                        <button type="button" role="checkbox" x-on:click="alternarMateria(materia.secciones.map(s => s.id))"
                            :aria-checked="materia.secciones.every(s => cursosSeleccionados.includes(s.id)) ? 'true' : (materia.secciones.some(s => cursosSeleccionados.includes(s.id)) ? 'mixed' : 'false')"
                            :aria-label="'Todas las secciones de ' + materia.nombre"
                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 border-azul-oscuro transition focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro focus-visible:ring-offset-1 dark:border-azul-fondo"
                            :class="materia.secciones.some(s => cursosSeleccionados.includes(s.id)) && 'bg-azul-oscuro dark:bg-azul-fondo'">
                            <x-icono nombre="check" class="h-3 w-3 text-white" x-show="materia.secciones.every(s => cursosSeleccionados.includes(s.id))" />
                            <span class="block h-0.5 w-2 rounded bg-white" x-show="! materia.secciones.every(s => cursosSeleccionados.includes(s.id)) && materia.secciones.some(s => cursosSeleccionados.includes(s.id))"></span>
                        </button>
                    </div>
                    <ul x-show="materiaFiltroAbierta(materia.clave)" class="ml-3 mt-0.5 space-y-0.5 border-l border-celeste pl-2 dark:border-azul-borde-oscuro">
                        <template x-for="curso in materia.secciones" :key="curso.id">
                            <li>
                                <label class="relative flex cursor-pointer items-center gap-2 rounded-md px-2 py-1 text-sm hover:bg-celeste/40 focus-within:ring-2 focus-within:ring-azul-oscuro dark:hover:bg-azul-borde-oscuro/40">
                                    <input type="checkbox" class="sr-only" x-on:change="alternar('cursosSeleccionados', curso.id)" :checked="cursosSeleccionados.includes(curso.id)">
                                    <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded border-2 transition"
                                        :style="cursosSeleccionados.includes(curso.id) ? 'background-color: ' + curso.color + '; border-color: ' + curso.color : 'border-color: ' + curso.color">
                                        <x-icono nombre="check" class="h-3 w-3 text-white" x-show="cursosSeleccionados.includes(curso.id)" />
                                    </span>
                                    <span class="min-w-0 truncate {{ $titulo }}" x-text="curso.seccion"></span>
                                    <span x-show="vista.conActividad.includes(curso.id)" class="ml-auto h-1.5 w-1.5 shrink-0 rounded-full bg-azul-oscuro dark:bg-azul-fondo" title="Tiene actividades esta semana" aria-hidden="true"></span>
                                    <span x-show="vista.conActividad.includes(curso.id)" class="sr-only">Tiene actividades esta semana</span>
                                </label>
                            </li>
                        </template>
                    </ul>
                </li>
            </template>
        </ul>
    </div>
</div>
