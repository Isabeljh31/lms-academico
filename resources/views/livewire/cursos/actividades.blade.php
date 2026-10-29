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
    // Un color por evaluación (en el orden del esquema), igual en la barra, la tarjeta y las insignias de la lista.
    $paletaEvaluaciones = [
        ['barra' => 'bg-azul-oscuro text-white', 'tenue' => 'bg-azul-oscuro/20 text-azul-oscuro dark:text-azul-fondo', 'borde' => 'border-t-azul-oscuro', 'peso' => 'text-azul-oscuro dark:text-azul-fondo', 'relleno' => 'bg-azul-oscuro dark:bg-azul-fondo', 'insignia' => 'border-azul-oscuro/30 bg-azul-oscuro/10 text-azul-oscuro dark:border-azul-fondo/40 dark:text-azul-fondo'],
        ['barra' => 'bg-cielo text-imperial', 'tenue' => 'bg-cielo/25 text-imperial dark:text-cielo', 'borde' => 'border-t-cielo', 'peso' => 'text-imperial dark:text-cielo', 'relleno' => 'bg-cielo', 'insignia' => 'border-cielo/50 bg-cielo-claro text-imperial dark:bg-cielo/10 dark:text-cielo'],
        ['barra' => 'bg-azul-fondo text-white', 'tenue' => 'bg-azul-fondo/20 text-azul-fondo', 'borde' => 'border-t-azul-fondo', 'peso' => 'text-azul-fondo', 'relleno' => 'bg-azul-fondo', 'insignia' => 'border-azul-fondo/30 bg-azul-fondo/10 text-azul-fondo'],
        ['barra' => 'bg-imperial text-white', 'tenue' => 'bg-imperial/20 text-imperial dark:text-slate-200', 'borde' => 'border-t-imperial', 'peso' => 'text-imperial dark:text-slate-200', 'relleno' => 'bg-imperial dark:bg-slate-300', 'insignia' => 'border-imperial/30 bg-imperial/10 text-imperial dark:text-slate-200'],
    ];
    $colorDeEvaluacion = fn (int $rubroId) => $paletaEvaluaciones[max(0, $rubros->search(fn ($rubro) => $rubro->id === $rubroId)) % count($paletaEvaluaciones)];
    $estadoCalculo = [
        'lista' => ['etiqueta' => 'Lista', 'clase' => 'bg-exito-base/15 text-exito-oscuro dark:text-green-300'],
        'incompleta' => ['etiqueta' => 'Incompleta', 'clase' => 'bg-red-50 text-red-700 dark:bg-red-950/40 dark:text-red-300'],
        'sin_configurar' => ['etiqueta' => 'Sin configurar', 'clase' => 'bg-amber-100 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300'],
    ];
    $formatoPeso = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
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

        @if ($rubros->isNotEmpty())
            @php $listas = $rubros->filter(fn ($rubro) => $rubro->estadoCalculo() === 'lista')->count(); @endphp
            <section aria-labelledby="titulo-nota-final" class="space-y-4">
                <x-gestion.tarjeta>
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="titulo-nota-final" class="flex items-center gap-1.5 text-base font-semibold text-texto dark:text-slate-100">
                            Nota final del curso
                            <x-gestion.ayuda texto="El supervisor define las evaluaciones y sus pesos. Tú decides cómo se calcula cada una." />
                        </h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">{{ $listas }} de {{ $rubros->count() }} {{ $rubros->count() === 1 ? 'evaluación lista' : 'evaluaciones listas' }} · Sobre 20</p>
                    </div>
                    <div class="mt-3 flex h-9 gap-1 overflow-hidden rounded-lg" aria-hidden="true">
                        @foreach ($rubros as $rubro)
                            @php $color = $colorDeEvaluacion($rubro->id); @endphp
                            <div wire:key="barra-{{ $rubro->id }}" style="flex: {{ (float) $rubro->peso }} 1 0%"
                                class="flex min-w-0 items-center px-3 text-xs font-bold {{ $rubro->estadoCalculo() === 'sin_configurar' ? $color['tenue'] : $color['barra'] }}">
                                <span class="truncate">{{ $rubro->nombre }} · {{ (float) $rubro->peso }} %</span>
                            </div>
                        @endforeach
                    </div>
                </x-gestion.tarjeta>

                <ul class="grid gap-4 md:grid-cols-2">
                    @foreach ($rubros as $rubro)
                        @php
                            $color = $colorDeEvaluacion($rubro->id);
                            $estado = $rubro->estadoCalculo();
                            $vinculadas = $rubro->actividadesVinculadas();
                            $siguiente = match (true) {
                                $estado === 'sin_configurar' => 'Elige cómo se calcula esta evaluación.',
                                $estado === 'incompleta' => 'Ajusta los pesos para que sumen 100 %.',
                                ! $rubro->esAutomatico() => 'Registra las notas en el libro de calificaciones.',
                                default => 'Las notas se calculan solas al calificar las actividades.',
                            };
                        @endphp
                        <li wire:key="evaluacion-{{ $rubro->id }}"
                            class="flex flex-col gap-3 rounded-xl border border-t-4 border-celeste bg-white p-4 shadow-sm dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:shadow-none {{ $color['borde'] }}">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 space-y-1.5">
                                    <h3 class="font-semibold text-texto dark:text-slate-100">{{ $rubro->nombre }}</h3>
                                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                                        <span class="rounded-full px-2 py-0.5 font-semibold {{ $estadoCalculo[$estado]['clase'] }}">{{ $estadoCalculo[$estado]['etiqueta'] }}</span>
                                        @if ($rubro->modo_calculo)
                                            <span class="rounded-full bg-celeste px-2 py-0.5 font-semibold text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">{{ $rubro->modo_calculo->etiqueta() }}</span>
                                        @endif
                                        <span class="text-slate-500 dark:text-slate-400">{{ $rubro->semana ? 'Semana '.$rubro->semana : 'Sin semana' }}</span>
                                    </div>
                                </div>
                                <p class="shrink-0 text-right">
                                    <span class="block text-2xl font-extrabold leading-none {{ $color['peso'] }}">{{ (float) $rubro->peso }} %</span>
                                    <span class="text-xs text-slate-500 dark:text-slate-400">de la nota final</span>
                                </p>
                            </div>

                            @if ($vinculadas->isNotEmpty())
                                <ul class="space-y-2.5" aria-label="Actividades de {{ $rubro->nombre }}">
                                    @foreach ($vinculadas as $vinculada)
                                        <li wire:key="vinculada-{{ $rubro->id }}-{{ $vinculada['tipo'] }}-{{ $vinculada['id'] }}" class="space-y-1">
                                            <div class="flex items-center gap-2 text-sm">
                                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[11px] font-bold uppercase tracking-wide {{ $estiloTipo[$vinculada['tipo']]['insignia'] }}">{{ $estiloTipo[$vinculada['tipo']]['etiqueta'] }}</span>
                                                <span class="min-w-0 flex-1 truncate text-texto dark:text-slate-100">{{ $vinculada['titulo'] }}</span>
                                                <span class="shrink-0 font-semibold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($vinculada['peso']) }} %</span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <span class="h-1.5 flex-1 overflow-hidden rounded-full bg-celeste dark:bg-azul-borde-oscuro" aria-hidden="true">
                                                    <span class="block h-full rounded-full {{ $color['relleno'] }}" style="width: {{ min(100, $vinculada['peso']) }}%"></span>
                                                </span>
                                                <span class="shrink-0 text-xs tabular-nums text-slate-500 dark:text-slate-400">{{ $formatoPeso($vinculada['peso'] * (float) $rubro->peso / 100) }} % de la final</span>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="rounded-lg border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">
                                    {{ $estado === 'sin_configurar' ? 'Todavía no elegiste cómo se calcula.' : ($rubro->esAutomatico() ? 'Ninguna actividad vinculada.' : 'La escribes en el libro de calificaciones.') }}
                                </p>
                            @endif

                            <p class="flex items-start gap-1.5 rounded-lg bg-fondo px-3 py-2 text-xs text-slate-600 dark:bg-azul-pagina-oscura dark:text-slate-300">
                                <x-icono nombre="flecha-derecha" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                <span><span class="font-semibold text-texto dark:text-slate-100">Siguiente:</span> {{ $siguiente }}</span>
                            </p>

                            <div class="mt-auto flex flex-wrap gap-2">
                                @if ($editable)
                                    <x-gestion.boton :variante="$estado === 'sin_configurar' ? 'primario' : 'secundario'" tamano="sm" :icono="$estado === 'sin_configurar' ? 'engranaje' : 'lapiz'"
                                        wire:click="configurarCalculo({{ $rubro->id }})" aria-label="{{ $estado === 'sin_configurar' ? 'Configurar' : 'Editar cálculo de' }} {{ $rubro->nombre }}">
                                        {{ $estado === 'sin_configurar' ? 'Configurar' : 'Editar cálculo' }}
                                    </x-gestion.boton>
                                @endif
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="lista" :href="route('cursos.libro-calificaciones', $curso)">
                                    {{ $editable && ! $rubro->esAutomatico() ? 'Registrar notas' : 'Ver notas' }}
                                </x-gestion.boton>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @else
            <x-gestion.tarjeta titulo="Nota final del curso">
                <p class="text-sm text-slate-600 dark:text-slate-300">El supervisor todavía no definió las evaluaciones. Cuando sus pesos sumen 100 % podrás elegir cómo se calcula cada una.</p>
            </x-gestion.tarjeta>
        @endif

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
                    <div role="group" aria-label="Filtrar actividades" class="inline-flex w-full flex-wrap rounded-lg border border-celeste bg-white p-1 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura sm:w-auto">
                        @foreach (\App\Livewire\Cursos\Actividades::FILTROS as $clave => $etiqueta)
                            @php $cantidad = match ($clave) { 'tareas' => $totales['tareas'], 'quizzes' => $totales['quizzes'], 'sin_evaluacion' => $totales['sin_evaluacion'], default => $totales['tareas'] + $totales['quizzes'] }; @endphp
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
                                    <div class="flex shrink-0 items-center gap-1.5 pl-14 sm:pl-0">
                                        @if ($fila['evaluacion'])
                                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg border px-2.5 py-1 text-xs font-bold {{ $colorDeEvaluacion($fila['evaluacion']['id'])['insignia'] }}">
                                                <span class="h-2 w-2 rounded-sm {{ $colorDeEvaluacion($fila['evaluacion']['id'])['relleno'] }}" aria-hidden="true"></span>
                                                {{ $fila['evaluacion']['nombre'] }} · {{ $fila['evaluacion']['examen'] ? 'Examen' : $formatoPeso($fila['evaluacion']['peso']).' %' }}
                                            </span>
                                            <x-gestion.ayuda :texto="'Aporta '.$formatoPeso($fila['evaluacion']['aporte']).' % de la nota final.'" />
                                        @elseif ($editable && $rubros->isNotEmpty())
                                            <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.stop="abierto = false">
                                                <button type="button" x-on:click="abierto = ! abierto" aria-haspopup="menu" x-bind:aria-expanded="abierto.toString()" aria-expanded="false"
                                                    class="inline-flex items-center gap-1 whitespace-nowrap rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-600 hover:border-azul-oscuro hover:text-azul-oscuro focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:border-slate-600 dark:text-slate-300">
                                                    Sin evaluación · Asignar <x-icono nombre="flecha-abajo" class="h-3.5 w-3.5" />
                                                </button>
                                                <div x-show="abierto" style="display: none" role="menu" aria-label="Asignar {{ $fila['titulo'] }} a una evaluación"
                                                    class="absolute right-0 z-30 mt-1 w-56 rounded-xl border border-celeste bg-white p-1.5 shadow-lg dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
                                                    @foreach ($rubros as $rubro)
                                                        <button type="button" role="menuitem" wire:key="asignar-{{ $fila['tipo'] }}-{{ $fila['id'] }}-{{ $rubro->id }}"
                                                            wire:click="configurarCalculo({{ $rubro->id }}, '{{ $fila['tipo'] }}-{{ $fila['id'] }}')" x-on:click="abierto = false"
                                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm text-texto hover:bg-celeste focus:bg-celeste focus:outline-none dark:text-slate-100 dark:hover:bg-azul-borde-oscuro dark:focus:bg-azul-borde-oscuro">
                                                            <span class="h-2 w-2 shrink-0 rounded-sm {{ $colorDeEvaluacion($rubro->id)['relleno'] }}" aria-hidden="true"></span>
                                                            <span class="flex-1">{{ $rubro->nombre }}</span>
                                                            <span class="text-xs text-slate-500">{{ (float) $rubro->peso }} %</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                            <x-gestion.ayuda texto="No cuenta para la nota final." />
                                        @else
                                            <span class="whitespace-nowrap rounded-lg border border-dashed border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-500 dark:border-slate-600 dark:text-slate-400">Sin evaluación</span>
                                        @endif
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

    @if ($mostrarCalculo)
        @php
            $rubroConfigurado = $rubros->firstWhere('id', $rubroCalculoId);
            $disponibles = $tareasDisponibles->map(fn ($tarea) => ['tipo' => 'tarea', 'modelo' => $tarea, 'titulo' => $tarea->titulo])
                ->concat($quizzesDisponibles->map(fn ($quiz) => ['tipo' => 'quiz', 'modelo' => $quiz, 'titulo' => $quiz->tema]));
            $enOtraEvaluacion = fn ($modelo) => $modelo->rubrosEvaluacion->first(fn ($rubro) => $rubro->id !== $rubroCalculoId);
            $puedeGuardar = match ($modoCalculo) {
                'promedio' => $totalCalculo['estado'] === 'ok',
                'examen' => $examenCalculo !== null,
                default => true,
            };
            $estiloTotal = [
                'ok' => ['barra' => 'bg-exito-base', 'texto' => 'text-exito-oscuro dark:text-green-300', 'icono' => 'check-circulo'],
                'aviso' => ['barra' => 'bg-amber-500', 'texto' => 'text-amber-800 dark:text-amber-300', 'icono' => 'alerta'],
                'error' => ['barra' => 'bg-red-500', 'texto' => 'text-red-700 dark:text-red-300', 'icono' => 'alerta'],
            ];
        @endphp
        <x-gestion.modal :titulo="'Cálculo de '.($rubroConfigurado?->nombre ?? 'evaluación')" cerrar="$set('mostrarCalculo', false)" ancho="max-w-2xl" icono="engranaje"
            :descripcion="(float) $rubroConfigurado?->peso.' % de la nota final · '.($rubroConfigurado?->semana ? 'Semana '.$rubroConfigurado->semana : 'Sin semana').' · Sobre 20'">
            <form wire:submit="guardarCalculo" class="space-y-5">
                <fieldset class="space-y-2.5">
                    <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">1</span>
                        ¿Cómo se obtiene la nota?
                    </legend>
                    <div class="grid gap-2.5 sm:grid-cols-3">
                        @foreach (\App\Enums\ModoCalculo::cases() as $modo)
                            <label wire:key="modo-{{ $modo->value }}" @class(['relative flex cursor-pointer flex-col gap-0.5 rounded-xl border p-3 transition-colors motion-reduce:transition-none focus-within:ring-2 focus-within:ring-azul-oscuro',
                                'border-azul-oscuro bg-azul-oscuro/5 ring-1 ring-azul-oscuro dark:border-azul-fondo dark:bg-azul-fondo/10 dark:ring-azul-fondo' => $modoCalculo === $modo->value,
                                'border-celeste hover:border-azul-oscuro/50 dark:border-azul-borde-oscuro' => $modoCalculo !== $modo->value])>
                                <input type="radio" class="sr-only" wire:model.live="modoCalculo" value="{{ $modo->value }}">
                                <span class="text-sm font-semibold text-texto dark:text-slate-100">{{ $modo->etiqueta() }}</span>
                                <span class="text-xs leading-snug text-slate-500 dark:text-slate-400">{{ $modo->descripcion() }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('modoCalculo') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                </fieldset>

                @if ($modoCalculo === 'manual')
                    <div class="space-y-1 rounded-xl bg-fondo px-4 py-3 text-sm text-slate-600 dark:bg-azul-pagina-oscura dark:text-slate-300">
                        <p>Registrarás la nota de cada estudiante (0 a 20) en el libro de calificaciones.</p>
                        <p>Las actividades vinculadas se liberan al guardar.</p>
                    </div>
                @elseif ($modoCalculo === 'promedio')
                    <fieldset class="space-y-2.5">
                        <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">2</span>
                            Elige las actividades y su peso
                            <x-gestion.ayuda texto="Se usa la nota de la entrega calificada y el mejor intento de cada quiz. La evaluación queda pendiente hasta que todas tengan nota." />
                        </legend>
                        @if ($disponibles->isEmpty())
                            <p class="rounded-lg border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">Todavía no hay tareas ni quizzes en el curso. Créalos con «Crear actividad».</p>
                        @else
                            <ul class="max-h-72 divide-y divide-celeste overflow-y-auto rounded-xl border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                @foreach ($disponibles as $disponible)
                                    @php
                                        $clave = $disponible['tipo'].'-'.$disponible['modelo']->id;
                                        $otra = $enOtraEvaluacion($disponible['modelo']);
                                        $marcada = array_key_exists($clave, $pesosCalculo);
                                    @endphp
                                    <li wire:key="calculo-{{ $clave }}" @class(['flex items-center gap-3 px-3 py-2.5', 'opacity-60' => $otra])>
                                        <input type="checkbox" id="calculo-{{ $clave }}" value="{{ $disponible['modelo']->id }}" @disabled($otra)
                                            wire:model.live="{{ $disponible['tipo'] === 'tarea' ? 'tareasCalculo' : 'quizzesCalculo' }}"
                                            class="h-4 w-4 rounded border-slate-300 text-azul-oscuro focus:ring-azul-oscuro">
                                        <label for="calculo-{{ $clave }}" class="min-w-0 flex-1 cursor-pointer">
                                            <span class="flex items-center gap-1.5 text-sm font-medium text-texto dark:text-slate-100">
                                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $estiloTipo[$disponible['tipo']]['insignia'] }}">{{ $estiloTipo[$disponible['tipo']]['etiqueta'] }}</span>
                                                <span class="truncate">{{ $disponible['titulo'] }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">
                                                {{ $disponible['modelo']->modulo?->nombreCompleto() ?? 'Sin semana' }}
                                                @if ($marcada && ! str_starts_with($disponible['modelo']->etiquetaEstadoPublicacion(), 'Publicado')) · Aún no está publicada @endif
                                            </span>
                                        </label>
                                        @if ($otra)
                                            <span class="shrink-0 text-xs text-slate-500 dark:text-slate-400">Ya cuenta para {{ $otra->nombre }}</span>
                                        @elseif ($marcada)
                                            <div class="shrink-0 text-right">
                                                <span @class(['inline-flex items-center overflow-hidden rounded-lg border bg-white dark:bg-azul-superficie-oscura', 'border-red-500' => $errors->has("pesosCalculo.$clave"), 'border-celeste dark:border-azul-borde-oscuro' => ! $errors->has("pesosCalculo.$clave")])>
                                                    <input type="text" inputmode="decimal" wire:model.live.debounce.400ms="pesosCalculo.{{ $clave }}" aria-label="Peso de {{ $disponible['titulo'] }} en porcentaje"
                                                        class="w-16 border-0 bg-transparent py-1.5 pl-2 pr-1 text-right text-sm font-semibold tabular-nums text-texto focus:ring-0 dark:text-slate-100">
                                                    <span class="pr-2 text-sm text-slate-500" aria-hidden="true">%</span>
                                                </span>
                                                @error("pesosCalculo.$clave") <p role="alert" class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @php $estilo = $estiloTotal[$totalCalculo['estado']]; @endphp
                        <div class="space-y-2 rounded-xl bg-fondo p-3 dark:bg-azul-pagina-oscura">
                            <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600 dark:text-slate-300">
                                <span>Total <span class="text-base font-bold tabular-nums text-texto dark:text-slate-100">{{ $formatoPeso($totalCalculo['total']) }} %</span> de 100 %</span>
                                <x-gestion.boton variante="fantasma" tamano="sm" wire:click="repartirPesos" :disabled="$pesosCalculo === []">Repartir en partes iguales</x-gestion.boton>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-celeste dark:bg-azul-borde-oscuro" aria-hidden="true">
                                <div class="h-full rounded-full transition-[width] motion-reduce:transition-none {{ $estilo['barra'] }}" style="width: {{ min(100, $totalCalculo['total']) }}%"></div>
                            </div>
                            <p role="status" class="flex items-center gap-1.5 text-sm font-medium {{ $estilo['texto'] }}">
                                <x-icono :nombre="$estilo['icono']" class="h-4 w-4 shrink-0" /> {{ $totalCalculo['mensaje'] }}
                            </p>
                        </div>
                    </fieldset>
                @else
                    <fieldset class="space-y-2.5">
                        <legend class="mb-2.5 flex items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-celeste text-[11px] font-extrabold text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo" aria-hidden="true">2</span>
                            Elige el examen
                            <x-gestion.ayuda texto="Un quiz o una tarea que vale el 100 % de esta evaluación. En un quiz se usa el mejor intento." />
                        </legend>
                        @if ($disponibles->isNotEmpty())
                            <ul class="max-h-64 divide-y divide-celeste overflow-y-auto rounded-xl border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                @foreach ($disponibles->sortBy(fn ($disponible) => $disponible['tipo'] === 'quiz' ? 0 : 1) as $disponible)
                                    @php
                                        $clave = $disponible['tipo'].'-'.$disponible['modelo']->id;
                                        $otra = $enOtraEvaluacion($disponible['modelo']);
                                    @endphp
                                    <li wire:key="examen-{{ $clave }}" @class(['flex items-center gap-3 px-3 py-2.5', 'opacity-60' => $otra])>
                                        <input type="radio" id="examen-{{ $clave }}" value="{{ $clave }}" wire:model.live="examenCalculo" @disabled($otra)
                                            class="h-4 w-4 border-slate-300 text-azul-oscuro focus:ring-azul-oscuro">
                                        <label for="examen-{{ $clave }}" class="min-w-0 flex-1 cursor-pointer">
                                            <span class="flex items-center gap-1.5 text-sm font-medium text-texto dark:text-slate-100">
                                                <span class="shrink-0 rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide {{ $estiloTipo[$disponible['tipo']]['insignia'] }}">{{ $estiloTipo[$disponible['tipo']]['etiqueta'] }}</span>
                                                <span class="truncate">{{ $disponible['titulo'] }}</span>
                                            </span>
                                            <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $disponible['modelo']->modulo?->nombreCompleto() ?? 'Sin semana' }} · {{ $disponible['modelo']->etiquetaEstadoPublicacion() }}</span>
                                        </label>
                                        <span class="shrink-0 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ $otra ? 'Ya cuenta para '.$otra->nombre : ($examenCalculo === $clave ? '100 %' : '') }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-dashed border-celeste px-3 py-2.5 text-sm text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">
                            <span>¿Aún no lo creas?</span>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="mas" :href="route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades'])">Crear quiz de examen</x-gestion.boton>
                        </div>
                        @error('examenCalculo') <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @foreach ($errors->get('actividadesCalculo') as $mensaje)
                    <p role="alert" class="text-sm text-red-600">{{ $mensaje }}</p>
                @endforeach
                @foreach (['tareasCalculo', 'tareasCalculo.*', 'quizzesCalculo', 'quizzesCalculo.*'] as $campoCalculo)
                    @error($campoCalculo) <p role="alert" class="text-sm text-red-600">{{ $message }}</p> @enderror
                @endforeach

                <div class="flex flex-col-reverse gap-3 border-t border-celeste pt-4 dark:border-azul-borde-oscuro sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Si ya hay notas, se recalculan al guardar. El cambio queda en la auditoría.</p>
                    <div class="flex justify-end gap-2">
                        <x-gestion.boton variante="secundario" wire:click="$set('mostrarCalculo', false)">Cancelar</x-gestion.boton>
                        <x-gestion.boton type="submit" icono="check" :disabled="! $puedeGuardar" wire:loading.attr="disabled" wire:target="guardarCalculo">Guardar cálculo</x-gestion.boton>
                    </div>
                </div>
            </form>
        </x-gestion.modal>
    @endif
</div>
