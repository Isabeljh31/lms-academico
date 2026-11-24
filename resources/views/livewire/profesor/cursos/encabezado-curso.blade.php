{{--
    Cabecera del detalle del curso del Profesor (maqueta "CursoDetalle"):
    banda pastel con el color del curso (misma fórmula que las tarjetas de
    /cursos), botón "Calificar", datos del curso, cuatro indicadores y las
    pestañas hacia las pantallas existentes del curso.
--}}
@use('App\Livewire\Profesor\PorCalificar\Index', 'PorCalificar')
@use('App\Services\Reportes\DetalleCursoProfesor')
@use('App\Services\Reportes\PanelProfesor')
@php
    $curso = $detalle;
    $color = $datos['color'];
    $pestanas = [
        ['clave' => 'contenido', 'ruta' => route('cursos.show', $curso), 'texto' => 'Contenido'],
        ['clave' => 'actividades', 'ruta' => route('cursos.actividades', $curso), 'texto' => 'Actividades', 'cuenta' => $curso->tareas_count + $curso->quizzes_count],
        ['clave' => 'evaluacion', 'ruta' => route('cursos.evaluacion', $curso), 'texto' => 'Evaluación'],
        ['clave' => 'matricula', 'ruta' => route('cursos.matriculas', $curso), 'texto' => 'Matrícula', 'cuenta' => $curso->inscripciones_activas_count],
        ['clave' => 'grupos', 'ruta' => route('cursos.grupos.index', $curso), 'texto' => 'Grupos', 'cuenta' => $curso->grupos_count],
        ['clave' => 'debates', 'ruta' => route('cursos.debates.index', $curso), 'texto' => 'Debates', 'cuenta' => $curso->debates_count],
        ['clave' => 'asistencia', 'ruta' => route('asistencia.index', $curso), 'texto' => 'Asistencia'],
        ['clave' => 'libro', 'ruta' => route('cursos.libro-calificaciones', $curso), 'texto' => 'Libro de calificaciones'],
        ['clave' => 'mensajeria', 'ruta' => route('cursos.mensajeria', $curso), 'texto' => 'Mensajería'],
    ];
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $chip = 'inline-flex items-center whitespace-nowrap rounded-full px-[11px] py-[3px] text-xs font-bold';
    $estadoTexto = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO[$curso->estado][0] ?? ucfirst($curso->estado);
    $modalidad = $curso->modalidad ? (\App\Services\Reportes\CursosProfesor::MODALIDADES[$curso->modalidad] ?? ucfirst($curso->modalidad)) : null;
@endphp

{{-- Una sola raíz fuera del @if: Livewire toma la etiqueta raíz del hijo del primer tag (los @if agregan comentarios antes). --}}
<div class="flex flex-col">
@if ($compacto)
    {{-- Variante compacta (subpantallas del curso): banda pastel con la misma fórmula de color y las pestañas, sin indicadores. --}}
        <a href="{{ route('cursos.index') }}" wire:navigate
            class="mb-3.5 inline-flex min-h-11 items-center gap-2 self-start rounded-lg text-[13.5px] font-bold text-[var(--p700)] hover:underline {{ $enfoque }}">
            <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> Volver a Cursos
        </a>

        <header class="overflow-hidden rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]" style="--c: {{ $color }}; --c-cuadro: {{ $color === \App\Services\Calendario\ColoresCursos::NEUTRO ? '60%' : '85%' }};">
            <div class="flex flex-wrap items-center gap-4 border-b border-[color:color-mix(in_srgb,var(--c)_30%,#fff)] bg-[color:color-mix(in_srgb,var(--c)_20%,#fff)] px-6 py-4">
                <span class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[color:color-mix(in_srgb,var(--c)_var(--c-cuadro),#000)] text-lg font-extrabold text-white" aria-hidden="true">{{ $datos['iniciales'] }}</span>
                <div class="min-w-0 flex-1">
                    @if ($datos['codigo'])
                        <p class="text-[11px] font-bold uppercase tracking-[0.12em] text-[color:color-mix(in_srgb,var(--c)_60%,#000)]">{{ $datos['codigo'] }}</p>
                    @endif
                    <p class="break-words text-[22px] font-extrabold leading-tight tracking-[-0.02em] text-[color:color-mix(in_srgb,var(--c)_45%,#000)]">{{ $curso->nombre }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $chip }} {{ $curso->estado === 'publicado' ? 'bg-[var(--ok100)] text-[var(--ok900)]' : 'bg-white/75 text-[#3F3B34]' }}">{{ $estadoTexto }}</span>
                    @if ($modalidad)
                        <span class="{{ $chip }} bg-white/75 text-[#3F3B34]">{{ $modalidad }}</span>
                    @endif
                </div>
            </div>
            @include('livewire.profesor.cursos._pestanas-curso')
        </header>
@else
@php
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $etiqueta = 'text-[11px] font-bold uppercase tracking-[0.12em] text-[#6B6559]';
    $chip = 'inline-flex items-center whitespace-nowrap rounded-full px-[11px] py-[3px] text-xs font-bold';
    $pastilla = 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-[9px] py-0.5 text-[11.5px] font-bold';
    $servicio = app(\App\Services\Reportes\CursosProfesor::class);
    $estadoTexto = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO[$curso->estado][0] ?? ucfirst($curso->estado);
    $estadoAviso = match ($curso->estado) {
        'borrador' => 'No visible para estudiantes · pendiente de publicación por el Supervisor',
        'despublicado' => 'No visible para estudiantes · lo despublicó el Supervisor',
        'archivado' => 'Curso finalizado · solo lectura',
        default => null,
    };
    $modalidad = $curso->modalidad ? (\App\Services\Reportes\CursosProfesor::MODALIDADES[$curso->modalidad] ?? ucfirst($curso->modalidad)) : null;

    // Por calificar: misma cifra que el dock; días y color con las reglas de Por calificar.
    $pendientes = (int) $datos['pendientes'];
    $diasEspera = $datos['mas_antigua'] ? PorCalificar::diasEsperando($datos['mas_antigua']) : null;
    $urgencia = $diasEspera !== null ? DetalleCursoProfesor::estiloUrgencia($diasEspera) : null;

    // Avance: N segmentos (o una barra continua en cursos muy largos).
    $totalSemanas = max(1, (int) $datos['semanas']);
    $semanasLlenas = (int) round($datos['avance']['porcentaje'] * $totalSemanas / 100);

    $cierre = $curso->estaFinalizado() ? null : $datos['cierre'];
    $diasCierre = $cierre ? PanelProfesor::diasHasta($cierre['fecha']) : null;

    $meta = array_filter([
        'calendario' => $curso->fecha_inicio ? $servicio->fecha($curso->fecha_inicio).($curso->fecha_fin ? ' – '.$servicio->fecha($curso->fecha_fin) : '').' · '.($totalSemanas === 1 ? '1 semana' : "{$totalSemanas} semanas") : null,
        'reloj' => DetalleCursoProfesor::horario($curso),
        'ubicacion' => DetalleCursoProfesor::aulas($curso),
        'persona' => $datos['docentes'] ? 'Prof. '.$datos['docentes'] : null,
    ]);
@endphp

    <a href="{{ route('cursos.index') }}" wire:navigate
        class="mb-3.5 inline-flex min-h-11 items-center gap-2 self-start rounded-lg text-[13.5px] font-bold text-[var(--p700)] hover:underline {{ $enfoque }}">
        <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> Volver a Cursos
    </a>

    <header class="overflow-hidden rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]" style="--c: {{ $color }}; --c-cuadro: {{ $color === \App\Services\Calendario\ColoresCursos::NEUTRO ? '60%' : '85%' }};">
        {{-- Banda pastel: la misma fórmula color-mix que las tarjetas de /cursos. --}}
        <div class="flex flex-wrap items-center gap-[18px] border-b border-[color:color-mix(in_srgb,var(--c)_30%,#fff)] bg-[color:color-mix(in_srgb,var(--c)_20%,#fff)] px-7 py-[22px]">
            <span class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-[color:color-mix(in_srgb,var(--c)_var(--c-cuadro),#000)] text-[22px] font-extrabold text-white" aria-hidden="true">{{ $datos['iniciales'] }}</span>
            <div class="min-w-0 flex-1">
                @if ($datos['codigo'])
                    <p class="text-[11.5px] font-bold uppercase tracking-[0.12em] text-[color:color-mix(in_srgb,var(--c)_60%,#000)]">{{ $datos['codigo'] }}</p>
                @endif
                <h1 class="mb-2 mt-[3px] break-words text-[30px] font-extrabold leading-[1.1] tracking-[-0.02em] text-[color:color-mix(in_srgb,var(--c)_45%,#000)]">{{ $curso->nombre }}</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="{{ $chip }} {{ $curso->estado === 'publicado' ? 'bg-[var(--ok100)] text-[var(--ok900)]' : 'bg-white/75 text-[#3F3B34]' }}">{{ $estadoTexto }}</span>
                    @if ($modalidad)
                        <span class="{{ $chip }} bg-white/75 text-[#3F3B34]">{{ $modalidad }}</span>
                    @endif
                    @if ($estadoAviso)
                        <span class="text-[12.5px] font-medium text-[#3F3B34]">{{ $estadoAviso }}</span>
                    @endif
                </div>
            </div>
            <div class="flex flex-col items-end gap-2">
                @if ($pendientes > 0)
                    <a href="{{ route('docente.por-calificar', ['curso' => $curso->id]) }}" wire:navigate
                        class="inline-flex h-[46px] items-center gap-2 whitespace-nowrap rounded-xl bg-[var(--p900)] px-5 text-sm font-bold text-white shadow-[0_6px_16px_rgba(9,59,59,0.25)] transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                        Calificar {{ $pendientes }} {{ $pendientes === 1 ? 'entrega' : 'entregas' }}
                        <x-icono nombre="flecha-derecha" class="h-4 w-4" />
                    </a>
                    @if ($diasEspera !== null)
                        <p class="flex items-center gap-[7px] text-[12.5px] text-[#3F3B34]">
                            <span class="h-[9px] w-[9px] shrink-0 rounded-full {{ $urgencia['punto'] }}" aria-hidden="true"></span>
                            La más antigua espera {{ PorCalificar::textoEspera($diasEspera) }}
                        </p>
                    @endif
                @else
                    <p class="inline-flex items-center gap-2 rounded-xl bg-white/75 px-4 py-2.5 text-sm font-bold text-[var(--ok900)]">
                        <x-icono nombre="check-circulo" class="h-[18px] w-[18px]" /> Estás al día
                    </p>
                @endif
            </div>
        </div>

        @if ($meta !== [])
            <ul class="flex flex-wrap gap-x-5 gap-y-1.5 px-7 pt-3.5 text-[13.5px] text-[#3F3B34]" aria-label="Datos del curso">
                @foreach ($meta as $icono => $texto)
                    <li class="flex items-center gap-[7px]"><x-icono :nombre="['calendario' => 'calendario', 'reloj' => 'reloj', 'ubicacion' => 'inicio', 'persona' => 'persona'][$icono]" class="h-[15px] w-[15px] shrink-0 text-[var(--p700)]" />{{ $texto }}</li>
                @endforeach
            </ul>
        @endif
        @if ($curso->descripcion)
            <p class="truncate px-7 pt-1 text-[13.5px] text-[#6B6559]" title="{{ $curso->descripcion }}">{{ $curso->descripcion }}</p>
        @endif

        <dl class="mt-4 grid grid-cols-[1.3fr_1fr_1fr_1.2fr] border-t border-[#ECE6DA]">
            <div class="px-7 py-4">
                <dt class="{{ $etiqueta }}">Avance del curso</dt>
                <dd class="mt-1 flex items-baseline gap-2 text-[26px] font-extrabold tracking-[-0.02em] text-[var(--p900)]">
                    @if ($datos['avance']['estado'] === 'en_curso')
                        Semana {{ $datos['numero_semana_actual'] }} <small class="text-[13px] font-semibold tracking-normal text-[#6B6559]">de {{ $totalSemanas }} · {{ $datos['avance']['porcentaje'] }} %</small>
                    @else
                        <span class="text-lg">{{ $datos['avance']['texto'] }}</span>
                    @endif
                </dd>
                <dd>
                    <span class="mt-[9px] flex gap-[3px]" role="progressbar" aria-label="Avance del curso" aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
                        @if ($totalSemanas <= 24)
                            @foreach (range(1, $totalSemanas) as $segmento)
                                <span class="h-1.5 flex-1 rounded-[3px] {{ $segmento > $semanasLlenas ? 'bg-[#ECE6DA]' : '' }}" @if ($segmento <= $semanasLlenas) style="background-color: {{ $color }}" @endif></span>
                            @endforeach
                        @else
                            <span class="h-1.5 flex-1 rounded-[3px] bg-[#ECE6DA]"><span class="block h-1.5 rounded-[3px]" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $color }}"></span></span>
                        @endif
                    </span>
                </dd>
            </div>
            <div class="border-l border-[#ECE6DA] px-7 py-4">
                <dt class="{{ $etiqueta }}">Matriculados</dt>
                <dd class="mt-1 flex items-baseline gap-2 text-[26px] font-extrabold tracking-[-0.02em] text-[var(--p900)]">
                    {{ $curso->inscripciones_activas_count }}
                    <small class="text-[13px] font-semibold tracking-normal text-[#6B6559]">{{ $curso->cupo_maximo ? 'de '.$curso->cupo_maximo.' cupos' : 'estudiantes activos' }}</small>
                </dd>
                @if ($curso->cupo_maximo)
                    <dd class="mt-[9px] h-1.5 overflow-hidden rounded-[3px] bg-[#ECE6DA]" role="progressbar" aria-label="Cupos ocupados" aria-valuenow="{{ $curso->inscripciones_activas_count }}" aria-valuemin="0" aria-valuemax="{{ $curso->cupo_maximo }}">
                        <span class="block h-full rounded-[3px] bg-[var(--p700)]" style="width: {{ min(100, round($curso->inscripciones_activas_count * 100 / max(1, $curso->cupo_maximo))) }}%"></span>
                    </dd>
                @endif
            </div>
            <div class="border-l border-[#ECE6DA] px-7 py-4">
                <dt class="{{ $etiqueta }}">Por calificar</dt>
                <dd class="mt-1 flex items-center gap-2 text-[26px] font-extrabold tracking-[-0.02em] text-[var(--p900)]">
                    {{ $pendientes }}
                    @if ($diasEspera !== null)
                        <span class="{{ $pastilla }} tracking-normal {{ $urgencia['pastilla'] }}"><span class="h-[9px] w-[9px] rounded-full {{ $urgencia['punto'] }}" aria-hidden="true"></span>{{ $diasEspera === 1 ? '1 día' : $diasEspera.' días' }}</span>
                    @endif
                </dd>
                <dd class="mt-[3px] text-[12.5px] text-[#57524A]">{{ $diasEspera !== null ? 'la más antigua espera' : 'nada pendiente' }}</dd>
            </div>
            <div class="border-l border-[#ECE6DA] px-7 py-4">
                <dt class="{{ $etiqueta }}">Próximo cierre</dt>
                @if ($cierre)
                    <dd class="mt-1 flex items-center gap-2 text-[26px] font-extrabold tracking-[-0.02em] text-[var(--p900)]">
                        {{ DetalleCursoProfesor::fechaCorta($cierre['fecha']) }}
                        @if ($diasCierre !== null && $diasCierre <= 1)
                            <span class="{{ $pastilla }} tracking-normal bg-[var(--warn200)] text-[var(--warn800)]">{{ PanelProfesor::textoDias($diasCierre) }}</span>
                        @endif
                    </dd>
                    <dd class="mt-[3px] truncate text-[12.5px] text-[#57524A]" title="{{ $cierre['titulo'] }}">{{ $cierre['titulo'] }}@if ($cierre['semana']) · Semana {{ $cierre['semana'] }}@endif</dd>
                @else
                    <dd class="mt-1 text-[26px] font-extrabold text-[var(--p900)]">—</dd>
                @endif
            </div>
        </dl>

        @include('livewire.profesor.cursos._pestanas-curso')
    </header>
@endif
</div>
