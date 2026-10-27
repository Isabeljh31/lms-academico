{{--
    Actividades del Profesor del curso: pestañas "Actividades" (tareas y
    quizzes en una lista) y "Banco de preguntas". La pestaña, el filtro y la
    búsqueda viven en el componente (y en la URL), así que sobreviven a los
    re-renders. Alpine cambia de pestaña sin pedir al servidor.
--}}
@php
    $estiloTipo = [
        'tarea' => ['etiqueta' => 'Tarea', 'icono' => 'tarea', 'caja' => 'bg-azul-oscuro/15 text-azul-oscuro dark:bg-azul-oscuro/25 dark:text-azul-fondo', 'insignia' => 'bg-azul-oscuro text-white dark:bg-azul-fondo'],
        'quiz' => ['etiqueta' => 'Quiz', 'icono' => 'quiz', 'caja' => 'bg-cielo-claro text-imperial dark:bg-azul-borde-oscuro dark:text-cielo', 'insignia' => 'bg-cielo-claro text-imperial ring-1 ring-inset ring-azul-fondo/40 dark:bg-azul-borde-oscuro dark:text-cielo'],
    ];
    $claseEstado = fn (string $estado) => match (true) {
        str_starts_with($estado, 'Publicado') => 'bg-exito-base/15 text-exito-oscuro',
        str_starts_with($estado, 'Programado') => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        default => 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200',
    };
    $opcionesCrear = [
        ['etiqueta' => 'Tarea', 'icono' => 'tarea', 'descripcion' => 'Entrega de archivo, texto o enlace con fecha límite y nota.', 'url' => route('cursos.tareas.crear', [$curso, 'desde' => 'actividades']), 'caja' => $estiloTipo['tarea']['caja']],
        ['etiqueta' => 'Quiz', 'icono' => 'quiz', 'descripcion' => 'Preguntas que se califican solas al terminar, con tiempo e intentos.', 'url' => route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades']), 'caja' => $estiloTipo['quiz']['caja']],
    ];
@endphp
{{-- relative: contiene los textos sr-only (position: absolute) para que no estiren la página fuera de la columna con scroll. --}}
<div class="relative py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">
        <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="inline-flex items-center gap-1 text-sm font-medium text-azul-oscuro hover:underline dark:text-azul-fondo">
            <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> {{ $curso->nombre }}
        </a>

        <x-gestion.encabezado titulo="Actividades" icono="tarea"
            descripcion="Tareas y quizzes del curso en un solo lugar, y el banco de preguntas para armar tus quizzes.">
            @if ($editable)
                {{-- Menú propio (no <select>): Enter/Espacio/flecha abajo abre, flechas recorren, Inicio/Fin, Esc cierra. --}}
                <div class="relative" x-data="{ abierto: false }"
                    x-on:keydown.escape.stop="if (abierto) { abierto = false; $refs.boton.focus() }"
                    x-on:click.outside="abierto = false">
                    <x-gestion.boton icono="mas" x-ref="boton" aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="abierto.toString()"
                        aria-controls="menu-crear-actividad"
                        x-on:click="abierto = ! abierto; if (abierto) $nextTick(() => $focus.within($refs.menu).first())"
                        x-on:keydown.arrow-down.prevent="abierto = true; $nextTick(() => $focus.within($refs.menu).first())">
                        Crear actividad
                        <x-icono nombre="flecha-abajo" stroke-width="2" class="h-4 w-4 transition-transform motion-reduce:transition-none" x-bind:class="{ 'rotate-180': abierto }" />
                    </x-gestion.boton>
                    <div id="menu-crear-actividad" x-ref="menu" x-show="abierto" style="display: none" role="menu" aria-label="Crear actividad"
                        x-transition:enter="transition ease-out duration-100 motion-reduce:transition-none" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                        x-on:keydown.arrow-down.prevent="$focus.within($refs.menu).wrap().next()"
                        x-on:keydown.arrow-up.prevent="$focus.within($refs.menu).wrap().previous()"
                        x-on:keydown.home.prevent="$focus.within($refs.menu).first()"
                        x-on:keydown.end.prevent="$focus.within($refs.menu).last()"
                        x-on:keydown.tab="abierto = false"
                        class="absolute right-0 z-30 mt-2 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-xl border border-celeste bg-white p-1.5 shadow-lg dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
                        @foreach ($opcionesCrear as $opcion)
                            <a href="{{ $opcion['url'] }}" wire:navigate role="menuitem"
                                class="flex items-start gap-3 rounded-lg px-3 py-2.5 text-left hover:bg-celeste focus:bg-celeste focus:outline-none dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $opcion['caja'] }}">
                                    <x-icono :nombre="$opcion['icono']" stroke-width="2" class="h-5 w-5" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-texto dark:text-slate-100">{{ $opcion['etiqueta'] }}</span>
                                    <span class="block text-xs text-slate-600 dark:text-slate-300">{{ $opcion['descripcion'] }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif
        </x-gestion.encabezado>

        <x-gestion.alertas />

        @if (! $editable)
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
                <p>Curso archivado (finalizado): se puede consultar, pero no admite cambios.</p>
            </div>
        @endif

        <div x-data="{
                pestanas: @js(array_keys(\App\Livewire\Cursos\Actividades::PESTANAS)),
                get activa() { return this.$wire.pestana },
                elegir(clave, enfocar = false) {
                    this.$wire.pestana = clave;
                    if (enfocar) this.$nextTick(() => document.getElementById('pestana-' + clave)?.focus());
                },
                mover(paso) {
                    const i = this.pestanas.indexOf(this.activa);
                    this.elegir(this.pestanas[(i + paso + this.pestanas.length) % this.pestanas.length], true);
                },
            }">
            <div role="tablist" aria-label="Apartados de Actividades" class="flex gap-1 border-b border-celeste dark:border-azul-borde-oscuro"
                x-on:keydown.arrow-right.prevent="mover(1)" x-on:keydown.arrow-left.prevent="mover(-1)"
                x-on:keydown.home.prevent="elegir(pestanas[0], true)" x-on:keydown.end.prevent="elegir(pestanas[pestanas.length - 1], true)">
                @foreach (\App\Livewire\Cursos\Actividades::PESTANAS as $clave => $etiqueta)
                    @php $esActiva = $pestana === $clave; @endphp
                    <button type="button" role="tab" id="pestana-{{ $clave }}" aria-controls="panel-{{ $clave }}"
                        aria-selected="{{ $esActiva ? 'true' : 'false' }}" x-bind:aria-selected="(activa === '{{ $clave }}').toString()"
                        tabindex="{{ $esActiva ? '0' : '-1' }}" x-bind:tabindex="activa === '{{ $clave }}' ? 0 : -1"
                        x-on:click="elegir('{{ $clave }}')"
                        @class(['-mb-px inline-flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-semibold transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro',
                            'border-azul-oscuro text-azul-oscuro dark:border-azul-fondo dark:text-azul-fondo' => $esActiva,
                            'border-transparent text-slate-600 hover:text-azul-oscuro dark:text-slate-300' => ! $esActiva])
                        x-bind:class="{
                            'border-azul-oscuro text-azul-oscuro dark:border-azul-fondo dark:text-azul-fondo': activa === '{{ $clave }}',
                            'border-transparent text-slate-600 hover:text-azul-oscuro dark:text-slate-300': activa !== '{{ $clave }}',
                        }">
                        <x-icono :nombre="$clave === 'banco' ? 'base-datos' : 'lista'" class="h-4 w-4" />
                        {{ $etiqueta }}
                    </button>
                @endforeach
            </div>

            {{-- Pestaña Actividades --}}
            <div id="panel-actividades" role="tabpanel" aria-labelledby="pestana-actividades" tabindex="0"
                x-show="activa === 'actividades'" @unless ($pestana === 'actividades') style="display: none" @endunless
                class="space-y-4 pt-5 focus:outline-none">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div role="group" aria-label="Filtrar por tipo" class="inline-flex w-full rounded-lg border border-celeste bg-white p-1 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura sm:w-auto">
                        @foreach (\App\Livewire\Cursos\Actividades::FILTROS as $clave => $etiqueta)
                            @php $cantidad = match ($clave) { 'tareas' => $totales['tareas'], 'quizzes' => $totales['quizzes'], default => $totales['tareas'] + $totales['quizzes'] }; @endphp
                            <button type="button" wire:click="filtrar('{{ $clave }}')" aria-pressed="{{ $tipo === $clave ? 'true' : 'false' }}"
                                @class(['flex-1 rounded-md px-3 py-1.5 text-sm font-medium transition-colors motion-reduce:transition-none focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro sm:flex-none',
                                    'bg-azul-oscuro text-white dark:bg-azul-fondo' => $tipo === $clave,
                                    'text-slate-700 hover:bg-celeste dark:text-slate-200 dark:hover:bg-azul-borde-oscuro' => $tipo !== $clave])>
                                {{ $etiqueta }} <span class="ml-0.5 text-xs opacity-80">{{ $cantidad }}</span>
                            </button>
                        @endforeach
                    </div>
                    <div class="w-full sm:max-w-xs">
                        <label for="busqueda-actividades" class="sr-only">Buscar actividades</label>
                        <x-gestion.control id="busqueda-actividades" type="search" icono="lupa" wire:model.live.debounce.300ms="busqueda" placeholder="Buscar por título" />
                    </div>
                </div>

                @if ($actividades->isEmpty())
                    <x-gestion.vacio icono="tarea"
                        :titulo="trim($busqueda) !== '' || $tipo !== 'todas' ? 'Ninguna actividad coincide con los filtros' : 'Todavía no hay actividades en este curso'"
                        :descripcion="trim($busqueda) !== '' || $tipo !== 'todas' ? 'Prueba con otro tipo o texto de búsqueda.' : 'Crea una tarea o un quiz con el botón «Crear actividad».'" />
                @else
                    <x-gestion.tarjeta :relleno="false">
                        <ul class="divide-y divide-celeste dark:divide-azul-borde-oscuro">
                            @foreach ($actividades as $fila)
                                @php $estilo = $estiloTipo[$fila['tipo']]; @endphp
                                <li wire:key="actividad-{{ $fila['tipo'] }}-{{ $fila['id'] }}" class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center">
                                    <div class="flex min-w-0 flex-1 items-start gap-3">
                                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $estilo['caja'] }}" aria-hidden="true">
                                            <x-icono :nombre="$estilo['icono']" stroke-width="2" class="h-6 w-6" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="rounded-md px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $estilo['insignia'] }}">{{ $estilo['etiqueta'] }}</span>
                                                <a href="{{ $fila['urlVer'] }}" wire:navigate class="truncate font-semibold text-texto hover:text-azul-oscuro hover:underline dark:text-slate-100">{{ $fila['titulo'] }}</a>
                                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $claseEstado($fila['estado']) }}">{{ $fila['estado'] }}</span>
                                            </div>
                                            <p class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                                                <span class="inline-flex items-center gap-1">
                                                    <x-icono nombre="calendario" class="h-3.5 w-3.5" />
                                                    {{ $fila['fecha'] ? ($fila['tipo'] === 'tarea' ? 'Vence ' : 'Cierra ').$fila['fecha']->formatoFechaHora() : 'Sin fecha límite' }}
                                                </span>
                                                <span @class(['inline-flex items-center gap-1', 'font-semibold text-azul-oscuro dark:text-azul-fondo' => $fila['destacarDato']])>
                                                    <x-icono :nombre="$fila['tipo'] === 'tarea' ? 'documento' : 'check-circulo'" class="h-3.5 w-3.5" /> {{ $fila['dato'] }}
                                                </span>
                                                @if ($fila['semana'])
                                                    <span>{{ $fila['semana'] }}</span>
                                                @endif
                                            </p>
                                        </div>
                                    </div>
                                    @if ($editable)
                                        <div class="flex shrink-0 gap-1 pl-14 sm:pl-0">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" :href="$fila['urlEditar']" aria-label="Editar {{ $fila['titulo'] }}">Editar</x-gestion.boton>
                                            @if ($fila['puedeEliminarse'])
                                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="confirmarEliminar('{{ $fila['tipo'] }}', {{ $fila['id'] }})" aria-label="Eliminar {{ $fila['titulo'] }}">Eliminar</x-gestion.boton>
                                            @else
                                                <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" disabled title="{{ $fila['motivoNoEliminar'] }}" aria-label="Eliminar {{ $fila['titulo'] }} (no disponible: {{ $fila['motivoNoEliminar'] }})">Eliminar</x-gestion.boton>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </x-gestion.tarjeta>
                    <div>{{ $actividades->links() }}</div>
                @endif
            </div>

            {{-- Pestaña Banco de preguntas (componente propio: su estado no se pierde al cambiar de pestaña). --}}
            <div id="panel-banco" role="tabpanel" aria-labelledby="pestana-banco" tabindex="0"
                x-show="activa === 'banco'" @unless ($pestana === 'banco') style="display: none" @endunless
                class="pt-5 focus:outline-none">
                <livewire:quizzes.banco-preguntas :curso="$curso" :incrustado="true" :key="'banco-'.$curso->id" />
            </div>
        </div>
    </div>

    @if ($mostrarConfirmacion)
        <x-gestion.modal titulo="Eliminar actividad" icono="papelera" cerrar="cerrarConfirmacion()">
            <p class="text-sm text-slate-700 dark:text-slate-300">
                Se eliminará {{ $eliminarTipo === 'tarea' ? 'la tarea' : 'el quiz' }} <span class="font-semibold">"{{ $eliminarTitulo }}"</span>.
                Los estudiantes dejarán de verlo. Esta acción no se puede deshacer.
            </p>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="cerrarConfirmacion">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="papelera" wire:click="eliminar">Eliminar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
