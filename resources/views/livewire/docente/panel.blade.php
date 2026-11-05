@use('App\Services\Reportes\PanelProfesor')
@php
    // Estilos del Panel del Profesor (mockup "Panel"), con los tokens de la paleta LearnUp.
    $tarjeta = 'rounded-xl border border-celeste bg-white shadow-sm';
    $eyebrow = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $enfoque = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2';
    $boton = "inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg px-4 text-sm font-semibold transition active:scale-[.97] motion-reduce:transition-none motion-reduce:active:scale-100 {$enfoque}";
    $botonPrimario = "{$boton} bg-azul-oscuro text-white shadow-sm hover:bg-azul-fondo";
    $botonSecundario = "{$boton} border border-slate-200 bg-white text-texto hover:border-azul-oscuro hover:text-azul-oscuro";
    $chip = "inline-flex min-h-11 items-center gap-1.5 rounded-full border px-3.5 text-[13px] font-semibold transition sm:min-h-9 motion-reduce:transition-none {$enfoque}";
    $chipActivo = 'border-azul-oscuro bg-azul-oscuro text-white';
    $chipInactivo = 'border-slate-200 bg-white text-slate-600 hover:border-azul-oscuro hover:text-azul-oscuro';
    $enlacePie = "flex min-h-12 items-center justify-between gap-3 border-t border-celeste px-5 text-sm font-semibold text-azul-oscuro transition hover:bg-fondo motion-reduce:transition-none {$enfoque} focus-visible:ring-inset focus-visible:ring-offset-0";
    $fila = 'border-t border-celeste px-5 py-3.5 transition hover:bg-fondo motion-reduce:transition-none';

    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $nota = fn (?float $valor) => $valor === null ? null : number_format($valor, 1, ',', '');
    $hoy = now()->locale('es')->isoFormat('dddd D [de] MMMM');
    $iconoProxima = [
        'tarea' => 'bg-cielo-claro text-azul-fondo',
        'quiz' => 'bg-cielo-claro text-azul-fondo',
        'calendario' => 'bg-celeste text-azul-oscuro',
        'check-circulo' => 'bg-amber-100 text-amber-800',
    ];
    $tiposProximos = ['todo' => 'Todo', 'cierre' => 'Cierres', 'contenido' => 'Contenido por subir', 'evaluacion' => 'Evaluaciones'];
@endphp

<div class="flex flex-col gap-6">
    {{-- Encabezado --}}
    <header class="flex flex-wrap items-center justify-between gap-3">
        <div class="min-w-0">
            <h1 class="break-words text-3xl font-bold leading-tight tracking-tight text-texto sm:text-[2.125rem]">Hola, {{ $profesor->name }}</h1>
            <p class="mt-1.5 text-base text-slate-600">{{ mb_strtoupper(mb_substr($hoy, 0, 1)).mb_substr($hoy, 1) }}. Resumen de tus cursos y pendientes.</p>
        </div>
        <span class="{{ $pastilla }} bg-lavanda-fondo/40 px-3 py-1 text-[13px] text-lavanda-oscuro">Rol: Profesor</span>
    </header>

    {{-- Indicadores --}}
    <section aria-label="Indicadores" class="rounded-xl border border-celeste shadow-sm grid grid-cols-2 gap-px overflow-hidden bg-celeste sm:grid-cols-3 lg:grid-cols-5">
        @php
            $indicadores = [
                ['etiqueta' => 'Cursos a cargo', 'valor' => $indicadores['cursos'], 'detalle' => null, 'icono' => 'libro', 'tono' => 'bg-celeste text-azul-oscuro',
                    'ruta' => route('cursos.index'), 'aria' => 'Cursos a cargo: '.$indicadores['cursos'].'. Ver mis cursos'],
                ['etiqueta' => 'Estudiantes', 'valor' => $indicadores['estudiantes'], 'detalle' => null, 'icono' => 'usuarios', 'tono' => 'bg-celeste text-azul-oscuro'],
                ['etiqueta' => 'Por calificar', 'valor' => $indicadores['porCalificar'], 'detalle' => 'entregas', 'icono' => 'check-circulo', 'tono' => 'bg-amber-100 text-amber-800', 'ayuda' => 'Cada grupo cuenta como 1.'],
                ['etiqueta' => 'Quizzes activos', 'valor' => $indicadores['quizzesActivos'], 'detalle' => null, 'icono' => 'quiz', 'tono' => 'bg-cielo-claro text-azul-fondo'],
                ['etiqueta' => 'Promedio a la fecha', 'valor' => $nota($indicadores['promedio']) ?? 'Sin notas', 'detalle' => $indicadores['promedio'] !== null ? 'sobre 20' : null, 'icono' => 'grafico', 'tono' => 'bg-menta-fondo text-exito-oscuro'],
            ];
        @endphp
        @foreach ($indicadores as $indice => $indicador)
            @php
                // Todas las celdas comparten la altura de su fila (grid) y una altura mínima común;
                // la etiqueta usa a lo sumo 2 líneas (11 px en pantallas angostas).
                $celda = 'relative flex h-full min-h-[6.25rem] min-w-0 items-center gap-2.5 bg-white px-4 py-4 sm:gap-3 sm:px-5 '.($indice === 4 ? 'col-span-2 sm:col-span-2 lg:col-span-1' : '');
                $esEnlace = isset($indicador['ruta']);
            @endphp
            @if ($esEnlace)
                <a href="{{ $indicador['ruta'] }}" wire:navigate wire:key="indicador-{{ $indice }}" aria-label="{{ $indicador['aria'] }}"
                    class="{{ $celda }} group transition hover:bg-fondo motion-reduce:transition-none focus-visible:z-10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-cielo">
            @else
                <div class="{{ $celda }}" wire:key="indicador-{{ $indice }}">
            @endif
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-[10px] sm:h-10 sm:w-10 {{ $indicador['tono'] }}">
                    <x-icono :nombre="$indicador['icono']" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="{{ $eyebrow }} flex items-center gap-1 text-[11px] leading-tight sm:text-xs {{ $esEnlace ? 'group-hover:text-azul-oscuro' : '' }}">
                        <span>{{ $indicador['etiqueta'] }}</span>
                        @isset($indicador['ayuda'])
                            {{-- Ayuda: aparece al pasar el mouse y al enfocar con el teclado; el texto se anuncia con aria-describedby. --}}
                            <span class="group/ayuda relative inline-flex">
                                <button type="button" aria-label="Más información sobre {{ $indicador['etiqueta'] }}" aria-describedby="ayuda-indicador-{{ $indice }}"
                                    class="relative inline-flex h-5 w-5 items-center justify-center rounded-full text-slate-400 transition hover:text-azul-oscuro after:absolute after:-inset-3 after:content-[''] motion-reduce:transition-none focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo">
                                    <x-icono nombre="info" class="h-4 w-4" />
                                </button>
                                <span id="ayuda-indicador-{{ $indice }}" role="tooltip"
                                    class="pointer-events-none invisible absolute left-0 top-full z-20 mt-1.5 w-40 rounded-lg bg-texto px-2.5 py-1.5 text-xs font-medium normal-case tracking-normal text-white opacity-0 shadow-lg transition group-focus-within/ayuda:visible group-focus-within/ayuda:opacity-100 group-hover/ayuda:visible group-hover/ayuda:opacity-100 motion-reduce:transition-none sm:left-auto sm:right-0">{{ $indicador['ayuda'] }}</span>
                            </span>
                        @endisset
                    </p>
                    <p class="mt-0.5 flex flex-wrap items-baseline gap-x-1.5">
                        <span class="{{ is_string($indicador['valor']) && ! is_numeric(str_replace(',', '.', $indicador['valor'])) ? 'text-xl' : 'text-[1.75rem] sm:text-[1.875rem]' }} font-bold leading-tight text-imperial">{{ $indicador['valor'] }}</span>
                        @if ($indicador['detalle'])
                            <span class="text-xs text-slate-500">{{ $indicador['detalle'] }}</span>
                        @endif
                    </p>
                </div>
            @if ($esEnlace)
                </a>
            @else
                </div>
            @endif
        @endforeach
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(20rem,1fr)] lg:items-start xl:gap-8">
        <div class="flex min-w-0 flex-col gap-6">

            {{-- Para hacer ahora --}}
            <section aria-labelledby="titulo-para-hacer" class="{{ $tarjeta }} relative overflow-hidden"
                wire:loading.class="opacity-60" wire:target="cursoPendientes">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-[18px]">
                    <div class="min-w-0">
                        <h2 id="titulo-para-hacer" class="text-xl font-semibold text-texto">Para hacer ahora</h2>
                        <p class="mt-0.5 text-sm text-slate-500">
                            @if ($pendientes['total'] > 0)
                                {{ $plural($pendientes['total'], 'entrega', 'entregas') }} en {{ $plural($pendientes['actividades'], 'actividad', 'actividades') }}, la más antigua primero. Cada grupo cuenta como 1.
                            @else
                                Entregas de tus estudiantes que esperan nota.
                            @endif
                        </p>
                    </div>
                    @if ($pendientes['masAntigua'])
                        <a href="{{ route('cursos.tareas.show', [$pendientes['masAntigua']['curso_id'], $pendientes['masAntigua']['tarea_id']]) }}" wire:navigate class="{{ $botonPrimario }}">Calificar la más antigua</a>
                    @endif
                </div>

                @if ($pendientes['porCurso']->count() > 1)
                    <div class="px-5 pb-3.5">
                        @if ($pendientes['porCurso']->count() <= \App\Livewire\Docente\Panel::MAX_CHIPS_CURSO)
                            <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por curso">
                                <button type="button" wire:click="$set('cursoPendientes', 0)" aria-pressed="{{ $cursoPendientes === 0 ? 'true' : 'false' }}"
                                    class="{{ $chip }} {{ $cursoPendientes === 0 ? $chipActivo : $chipInactivo }}">Todos {{ $pendientes['total'] }}</button>
                                @foreach ($pendientes['porCurso'] as $cursoId => $cantidad)
                                    <button type="button" wire:key="chip-pendientes-{{ $cursoId }}" wire:click="$set('cursoPendientes', {{ $cursoId }})" aria-pressed="{{ $cursoPendientes === $cursoId ? 'true' : 'false' }}"
                                        class="{{ $chip }} {{ $cursoPendientes === $cursoId ? $chipActivo : $chipInactivo }}">{{ $secciones->nombreVisible($cursoId) }} {{ $cantidad }}</button>
                                @endforeach
                            </div>
                        @else
                            <div class="max-w-sm">
                                <x-gestion.desplegable id="panel-curso-pendientes" etiqueta="Filtrar por curso" wire:model.live="cursoPendientes" :seleccionado="$cursoPendientes" class="min-h-11"
                                    :opciones="['0' => 'Todos los cursos ('.$pendientes['total'].')'] + $pendientes['porCurso']->mapWithKeys(fn ($cantidad, $cursoId) => [(string) $cursoId => $secciones->nombreVisible($cursoId).' ('.$cantidad.')'])->all()" />
                            </div>
                        @endif
                    </div>
                @endif

                @if ($cursos->isEmpty())
                    <x-panel-profesor.vacio icono="libro" titulo="Aún no tienes cursos asignados."
                        descripcion="Cuando el Supervisor te los asigne, aquí verás las entregas que esperan nota." class="border-t border-celeste px-5 py-4" />
                @elseif ($pendientes['filas']->isEmpty())
                    <x-panel-profesor.vacio icono="check-circulo" tono="bg-menta-fondo text-exito-oscuro" titulo="Estás al día."
                        descripcion="No tienes entregas por calificar." class="border-t border-celeste px-5 py-4" />
                @else
                    @if ($cursoPendientes)
                        @php $cursoElegido = $cursos->firstWhere('id', $cursoPendientes); @endphp
                        <div class="flex items-center gap-3 border-t border-celeste bg-fondo px-5 py-3.5">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold text-white" style="background-color: {{ $colores[$cursoPendientes] ?? \App\Services\Calendario\ColoresCursos::NEUTRO }}" aria-hidden="true">{{ PanelProfesor::iniciales($cursoElegido->nombre) }}</span>
                            <span class="min-w-0 flex-1">
                                <b class="text-[15px] font-semibold text-texto">{{ $secciones->nombreVisible($cursoPendientes) }}</b>
                                <span class="ml-2 text-[13px] text-slate-500">{{ $secciones->codigo($cursoPendientes) }}</span>
                            </span>
                            <span class="{{ $pastilla }} bg-amber-100 text-amber-800">{{ $pendientes['porCurso'][$cursoPendientes] ?? 0 }} por calificar</span>
                        </div>
                    @endif
                    <ul>
                        @foreach ($pendientes['filas'] as $actividad)
                            <li class="{{ $fila }} flex flex-col gap-3 sm:flex-row sm:items-center sm:gap-4" wire:key="pendiente-{{ $actividad['tarea_id'] }}">
                                <div class="min-w-0 max-w-prose flex-1">
                                    @unless ($cursoPendientes)
                                        <p class="{{ $eyebrow }} flex min-w-0 items-center gap-1.5">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background-color: {{ $colores[$actividad['curso_id']] ?? \App\Services\Calendario\ColoresCursos::NEUTRO }}" aria-hidden="true"></span>
                                            <span class="truncate">{{ $secciones->nombreVisible($actividad['curso_id']) }}</span>
                                        </p>
                                    @endunless
                                    <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <b class="break-words text-[15px] font-semibold text-texto">{{ $actividad['titulo'] }}</b>
                                        <span class="{{ $pastilla }} bg-celeste text-slate-600">{{ $actividad['grupal'] ? 'Grupal' : 'Individual' }}</span>
                                    </p>
                                    <p class="mt-0.5 text-sm text-slate-500">
                                        {{ $plural($actividad['pendientes'], $actividad['grupal'] ? 'entrega de grupo' : 'entrega', $actividad['grupal'] ? 'entregas de grupo' : 'entregas') }} · la más antigua, {{ PanelProfesor::textoAntiguedad($actividad['mas_antigua']) }}
                                    </p>
                                </div>
                                <a href="{{ route('cursos.tareas.show', [$actividad['curso_id'], $actividad['tarea_id']]) }}" wire:navigate
                                    class="{{ $botonPrimario }} self-start sm:ml-auto sm:self-auto"
                                    aria-label="Calificar {{ $actividad['pendientes'] }} {{ $actividad['pendientes'] === 1 ? 'entrega' : 'entregas' }} de {{ $actividad['titulo'] }}">Calificar {{ $actividad['pendientes'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($pendientes['total'] > 0)
                    <a href="{{ route('docente.por-calificar') }}" wire:navigate class="{{ $enlacePie }}">
                        <span>{{ $pendientes['restantes'] > 0 ? 'Ver las '.$pendientes['restantes'].' restantes en Por calificar' : 'Abrir Por calificar con filtros y orden' }}</span>
                        <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px] shrink-0" />
                    </a>
                @endif
            </section>

            {{-- Lo que se viene --}}
            <section aria-labelledby="titulo-se-viene" class="{{ $tarjeta }} relative overflow-hidden"
                wire:loading.class="opacity-60" wire:target="tipoProximo,cursoProximo">
                <div class="px-5 py-[18px]">
                    <h2 id="titulo-se-viene" class="text-xl font-semibold text-texto">Lo que se viene</h2>
                    <p class="mt-0.5 text-sm text-slate-500">Las {{ \App\Livewire\Docente\Panel::MAX_PROXIMAS }} fechas más cercanas. Filtra para ver el resto.</p>
                </div>

                @if ($proximas['conteos']['todo'] > 0 || $tipoProximo !== 'todo' || $cursoProximo)
                    <div class="flex flex-col gap-2.5 px-5 pb-3.5">
                        <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por tipo">
                            @foreach ($tiposProximos as $tipo => $etiqueta)
                                <button type="button" wire:key="chip-tipo-{{ $tipo }}" wire:click="$set('tipoProximo', '{{ $tipo }}')" aria-pressed="{{ $tipoProximo === $tipo ? 'true' : 'false' }}"
                                    class="{{ $chip }} {{ $tipoProximo === $tipo ? $chipActivo : $chipInactivo }}">{{ $etiqueta }} {{ $proximas['conteos'][$tipo] }}</button>
                            @endforeach
                        </div>
                        @if ($proximas['cursosConFechas']->count() > 1)
                            @if ($proximas['cursosConFechas']->count() <= \App\Livewire\Docente\Panel::MAX_CHIPS_CURSO)
                                <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por curso">
                                    <button type="button" wire:click="$set('cursoProximo', 0)" aria-pressed="{{ $cursoProximo === 0 ? 'true' : 'false' }}"
                                        class="{{ $chip }} {{ $cursoProximo === 0 ? $chipActivo : $chipInactivo }}">Todos los cursos</button>
                                    @foreach ($proximas['cursosConFechas'] as $cursoId)
                                        <button type="button" wire:key="chip-proximo-{{ $cursoId }}" wire:click="$set('cursoProximo', {{ $cursoId }})" aria-pressed="{{ $cursoProximo === $cursoId ? 'true' : 'false' }}"
                                            class="{{ $chip }} {{ $cursoProximo === $cursoId ? $chipActivo : $chipInactivo }}">{{ $secciones->nombreVisible($cursoId) }}</button>
                                    @endforeach
                                </div>
                            @else
                                <div class="max-w-sm">
                                    <x-gestion.desplegable id="panel-curso-proximo" etiqueta="Filtrar por curso" wire:model.live="cursoProximo" :seleccionado="$cursoProximo" class="min-h-11"
                                        :opciones="['0' => 'Todos los cursos'] + $proximas['cursosConFechas']->mapWithKeys(fn ($cursoId) => [(string) $cursoId => $secciones->nombreVisible($cursoId)])->all()" />
                                </div>
                            @endif
                        @endif
                    </div>
                @endif

                @if ($proximas['filas']->isEmpty())
                    @if ($proximas['conteos']['todo'] > 0)
                        <x-panel-profesor.vacio icono="filtro" titulo="Nada en este filtro." descripcion="Elige otro tipo para ver las demás fechas." class="border-t border-celeste px-5 py-4" />
                    @else
                        <x-panel-profesor.vacio icono="calendario" titulo="Sin fechas próximas." class="border-t border-celeste px-5 py-4"
                            descripcion="No hay cierres ni evaluaciones en los próximos {{ PanelProfesor::HORIZONTE_DIAS }} días, ni semanas sin contenido en los próximos {{ PanelProfesor::DIAS_CONTENIDO }}." />
                    @endif
                @else
                    <ul>
                        @foreach ($proximas['filas'] as $item)
                            <li class="{{ $fila }} flex flex-wrap items-center gap-x-4 gap-y-3" wire:key="proxima-{{ $item['clave'] }}">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[10px] {{ $iconoProxima[$item['icono']] ?? 'bg-celeste text-azul-oscuro' }}">
                                    <x-icono :nombre="$item['icono']" class="h-5 w-5" />
                                </span>
                                <div class="min-w-0 max-w-prose flex-1 basis-48">
                                    <p class="{{ $eyebrow }} truncate">{{ $secciones->nombreVisible($item['curso_id']) }}</p>
                                    <p class="mb-1.5 mt-0.5 break-words text-[15px] font-semibold leading-snug text-texto">{{ $item['titulo'] }}</p>
                                    <span class="{{ $pastilla }} {{ $item['dias'] <= PanelProfesor::DIAS_ALERTA ? 'bg-amber-100 text-amber-800' : 'bg-celeste text-azul-oscuro' }}">{{ PanelProfesor::textoDias($item['dias'], $item['prefijo']) }}</span>
                                </div>
                                <a href="{{ $item['url'] }}" wire:navigate class="{{ $botonSecundario }} ml-14 sm:ml-auto">{{ $item['accion'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <a href="{{ route('evento-calendario.index') }}" wire:navigate class="{{ $enlacePie }}">
                    <span>{{ $proximas['restantes'] > 0 ? 'Ver las '.$proximas['restantes'].' restantes en el Calendario' : 'Ver todo en el Calendario' }}</span>
                    <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px] shrink-0" />
                </a>
            </section>
        </div>

        {{-- Comunicados --}}
        <div class="min-w-0 lg:sticky lg:top-6">
            <aside aria-labelledby="titulo-comunicados" class="{{ $tarjeta }} relative flex flex-col p-5 lg:max-h-[calc(100vh-8rem)]"
                wire:loading.class="opacity-60" wire:target="filtroComunicados,todosLosComunicados">
                <h2 id="titulo-comunicados" class="mb-3 text-xl font-semibold text-texto">Comunicados</h2>

                @if ($comunicados['total'] === 0)
                    <x-panel-profesor.vacio icono="megafono" titulo="Sin comunicados." descripcion="Los avisos de la institución para profesores aparecerán aquí." />
                @else
                    <div class="mb-3.5 flex flex-wrap gap-2" role="group" aria-label="Filtrar comunicados">
                        @foreach (['todos' => ['Todos', $comunicados['total']], 'urgentes' => ['Urgentes', $comunicados['urgentes']], 'institucionales' => ['Institucionales', $comunicados['institucionales']]] as $filtro => [$etiqueta, $cantidad])
                            <button type="button" wire:key="chip-comunicados-{{ $filtro }}" wire:click="$set('filtroComunicados', '{{ $filtro }}')" aria-pressed="{{ $filtroComunicados === $filtro ? 'true' : 'false' }}"
                                class="{{ $chip }} {{ $filtroComunicados === $filtro ? $chipActivo : $chipInactivo }}">{{ $etiqueta }} {{ $cantidad }}</button>
                        @endforeach
                    </div>

                    <p class="mb-1.5 text-[13px] text-slate-500">Los más recientes</p>
                    <div class="-mx-1 min-h-0 overflow-y-auto px-1 lg:flex-1 {{ $todosLosComunicados ? 'max-h-[28rem]' : '' }}">
                        @forelse ($comunicados['lista'] as $comunicado)
                            @if ($comunicado->prioridad === 'urgente')
                                <div class="mb-1.5 rounded-xl border border-red-200 bg-red-50 p-3.5 text-red-800" wire:key="comunicado-{{ $comunicado->id }}">
                                    <p class="{{ $eyebrow }} flex items-center gap-2 !text-red-700">
                                        <x-icono nombre="alerta" class="h-[18px] w-[18px] shrink-0" />
                                        Urgente · {{ $comunicado->created_at->locale('es')->diffForHumans() }}
                                    </p>
                                    <p class="mb-1 mt-1.5 break-words text-[15px] font-semibold leading-snug">{{ $comunicado->titulo }}</p>
                                    <p class="whitespace-pre-line break-words text-sm leading-relaxed">{{ $comunicado->contenido }}</p>
                                </div>
                            @else
                                <details class="group border-t border-celeste first:border-t-0" wire:key="comunicado-{{ $comunicado->id }}">
                                    <summary class="flex min-h-11 cursor-pointer list-none items-center gap-3 rounded-lg py-2.5 [&::-webkit-details-marker]:hidden {{ $enfoque }}">
                                        <span class="h-2 w-2 shrink-0 rounded-full bg-azul-oscuro" aria-hidden="true"></span>
                                        <span class="min-w-0 flex-1">
                                            <b class="block break-words text-sm font-semibold leading-snug text-texto">{{ $comunicado->titulo }}</b>
                                            <span class="text-xs text-slate-500">Institucional · {{ $comunicado->created_at->locale('es')->diffForHumans() }}</span>
                                        </span>
                                        <x-icono nombre="flecha-derecha" class="h-4 w-4 shrink-0 text-slate-500 transition-transform group-open:rotate-90 motion-reduce:transition-none" />
                                    </summary>
                                    <p class="mb-3 ml-5 whitespace-pre-line break-words text-sm leading-relaxed text-slate-600">{{ $comunicado->contenido }}</p>
                                </details>
                            @endif
                        @empty
                            <x-panel-profesor.vacio icono="megafono" class="py-2" :titulo="$filtroComunicados === 'urgentes' ? 'No hay comunicados urgentes.' : 'No hay comunicados institucionales.'" />
                        @endforelse
                    </div>

                    @php
                        $totalDelFiltro = match ($filtroComunicados) {
                            'urgentes' => $comunicados['urgentes'],
                            'institucionales' => $comunicados['institucionales'],
                            default => $comunicados['total'],
                        };
                    @endphp
                    @if ($todosLosComunicados || $totalDelFiltro > $comunicados['lista']->count())
                        <button type="button" wire:click="$toggle('todosLosComunicados')" aria-expanded="{{ $todosLosComunicados ? 'true' : 'false' }}"
                            class="mt-1.5 flex min-h-11 w-full items-center justify-between gap-3 border-t border-celeste text-left text-sm font-semibold text-azul-oscuro {{ $enfoque }}">
                            <span>{{ $todosLosComunicados ? 'Ver solo los más recientes' : 'Ver los '.$totalDelFiltro.' comunicados' }}</span>
                            <x-icono :nombre="$todosLosComunicados ? 'flecha-arriba' : 'flecha-abajo'" class="h-[18px] w-[18px] shrink-0" />
                        </button>
                    @endif
                @endif
            </aside>
        </div>
    </div>
</div>
