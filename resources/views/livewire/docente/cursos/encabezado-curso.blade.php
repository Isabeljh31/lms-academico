@use('App\Services\Reportes\PanelProfesor')
@php
    $curso = $detalle;
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo focus-visible:ring-offset-2';
    $pastilla = 'inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-bold';
    $eyebrow = 'text-xs font-semibold uppercase tracking-wide text-slate-500';
    $estadosCurso = \App\Services\Reportes\CursosProfesor::ESTADOS_CURSO;
    [$estadoTexto, $estadoClase] = $estadosCurso[$curso->estado] ?? [ucfirst($curso->estado), 'bg-celeste text-slate-600'];
    $modalidades = \App\Services\Reportes\CursosProfesor::MODALIDADES;
    $servicio = app(\App\Services\Reportes\CursosProfesor::class);
    $meta = array_filter([
        $datos['codigo'],
        $curso->fecha_inicio ? $servicio->fecha($curso->fecha_inicio).($curso->fecha_fin ? ' – '.$servicio->fecha($curso->fecha_fin) : '') : null,
        $datos['semanas'] === 1 ? '1 semana' : $datos['semanas'].' semanas',
    ]);
    $enlaces = [
        ['ruta' => route('cursos.show', $curso), 'texto' => 'Contenido', 'icono' => 'libro', 'activo' => true],
        ['ruta' => route('cursos.actividades', $curso), 'texto' => 'Actividades', 'icono' => 'check-circulo'],
        ['ruta' => route('cursos.matriculas', $curso), 'texto' => 'Matrícula', 'icono' => 'usuario-mas'],
        ['ruta' => route('cursos.grupos.index', $curso), 'texto' => 'Grupos', 'icono' => 'usuarios'],
        ['ruta' => route('cursos.debates.index', $curso), 'texto' => 'Debates', 'icono' => 'chat'],
        ['ruta' => route('asistencia.index', $curso), 'texto' => 'Asistencia', 'icono' => 'calendario'],
        ['ruta' => route('cursos.libro-calificaciones', $curso), 'texto' => 'Libro de calificaciones', 'icono' => 'lista'],
        ['ruta' => route('cursos.mensajeria', $curso), 'texto' => 'Mensajería', 'icono' => 'sobre'],
    ];
@endphp

<div class="flex flex-col gap-4">
    <a href="{{ route('cursos.index') }}" wire:navigate
        class="inline-flex min-h-11 items-center gap-1.5 self-start rounded-lg text-sm font-semibold text-azul-oscuro hover:underline {{ $enfoque }}">
        <x-icono nombre="flecha-izquierda" class="h-4 w-4" /> Volver a Cursos
    </a>

    <header class="overflow-hidden rounded-xl border border-t-4 border-celeste bg-white shadow-sm" style="border-top-color: {{ $datos['color'] }}">
        <div class="flex flex-wrap items-start gap-3.5 px-5 pb-4 pt-5 sm:px-6">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl text-lg font-bold text-white" style="background-color: {{ $datos['color'] }}" aria-hidden="true">{{ $datos['iniciales'] }}</span>
            <div class="min-w-0 flex-1 basis-64">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2">
                    <h1 class="break-words text-[1.625rem] font-bold leading-tight tracking-tight text-texto sm:text-[1.75rem]">{{ $curso->nombre }}</h1>
                    <span class="{{ $pastilla }} {{ $estadoClase }}">{{ $estadoTexto }}</span>
                    @if ($curso->modalidad)
                        <span class="{{ $pastilla }} bg-celeste text-slate-600">{{ $modalidades[$curso->modalidad] ?? ucfirst($curso->modalidad) }}</span>
                    @endif
                </div>
                <p class="mt-1 break-words text-[13px] text-slate-500">{{ implode(' · ', $meta) }}</p>
                <p class="mt-0.5 break-words text-[13px] text-slate-600"><span class="sr-only">Profesores: </span>Prof. {{ $datos['docentes'] }}</p>
                @if ($curso->descripcion)
                    <p class="mt-2 whitespace-pre-line break-words text-sm text-texto">{{ $curso->descripcion }}</p>
                @endif
            </div>
            @if ($datos['pendientes'] > 0)
                <a href="{{ route('docente.por-calificar') }}" wire:navigate
                    class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-azul-oscuro px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-azul-fondo active:scale-[.97] motion-reduce:transition-none {{ $enfoque }}">
                    Calificar {{ $datos['pendientes'] }} {{ $datos['pendientes'] === 1 ? 'entrega' : 'entregas' }}
                </a>
            @endif
        </div>

        <dl class="grid grid-cols-2 gap-px border-t border-celeste bg-celeste lg:grid-cols-[minmax(0,1.6fr)_repeat(3,minmax(0,1fr))]">
            <div class="col-span-2 bg-white px-5 py-3 sm:px-6 lg:col-span-1">
                <dt class="{{ $eyebrow }}">Avance del curso</dt>
                <dd>
                    <span class="mb-1.5 mt-2 flex justify-between gap-2 text-sm">
                        <span class="font-semibold text-texto">{{ $datos['avance']['texto'] }}</span>
                        @if ($datos['avance']['detalle'])
                            <span class="whitespace-nowrap text-slate-500">{{ $datos['avance']['detalle'] }}</span>
                        @endif
                    </span>
                    <span class="block h-2 rounded-full bg-celeste" role="progressbar" aria-label="Avance del curso"
                        aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
                        <span class="block h-2 rounded-full" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $datos['color'] }}"></span>
                    </span>
                </dd>
            </div>
            <div class="bg-white px-4 py-3">
                <dt class="{{ $eyebrow }}">Matriculados</dt>
                <dd class="mt-1 text-[1.375rem] font-bold leading-tight tabular-nums text-imperial">
                    {{ $detalle->inscripciones_activas_count }}@if ($curso->cupo_maximo) <span class="text-base font-semibold text-slate-500">de {{ $curso->cupo_maximo }}</span>@endif
                </dd>
                <dd class="text-[13px] text-slate-500">{{ $curso->cupo_maximo ? 'cupos ocupados' : 'estudiantes activos' }}</dd>
            </div>
            <div class="bg-white px-4 py-3">
                <dt class="{{ $eyebrow }}">Por calificar</dt>
                <dd class="mt-1 text-[1.375rem] font-bold leading-tight tabular-nums {{ $datos['pendientes'] > 0 ? 'text-amber-800' : 'text-exito-oscuro' }}">{{ $datos['pendientes'] }}</dd>
                <dd class="text-[13px] text-slate-500">{{ $datos['mas_antigua'] ? 'la más antigua, '.PanelProfesor::textoAntiguedad($datos['mas_antigua']) : 'nada pendiente' }}</dd>
            </div>
            <div class="col-span-2 bg-white px-4 py-3 sm:col-span-1">
                <dt class="{{ $eyebrow }}">Próximo cierre</dt>
                @if ($curso->estaFinalizado())
                    <dd class="mt-1 text-base font-bold text-imperial">Curso cerrado</dd>
                @elseif ($datos['cierre'])
                    <dd class="mt-1 text-[1.375rem] font-bold leading-tight text-imperial">{{ $datos['cierre']['fecha']->formatoDiaMes() }}</dd>
                    <dd class="break-words text-[13px] text-slate-500">{{ $datos['cierre']['titulo'] }}@if ($datos['cierre']['semana']) · Semana {{ $datos['cierre']['semana'] }}@endif</dd>
                @else
                    <dd class="mt-1 text-base font-bold text-imperial">Sin fechas</dd>
                    <dd class="text-[13px] text-slate-500">ninguna tarea ni quiz por cerrar</dd>
                @endif
            </div>
        </dl>

        <nav aria-label="Secciones del curso" class="flex flex-wrap gap-1 border-t border-celeste bg-fondo/60 px-3 py-1.5 sm:px-4">
            @foreach ($enlaces as $enlace)
                <a href="{{ $enlace['ruta'] }}" wire:navigate @if ($enlace['activo'] ?? false) aria-current="page" @endif
                    class="inline-flex min-h-11 items-center gap-2 whitespace-nowrap rounded-full px-3.5 text-sm font-semibold transition motion-reduce:transition-none {{ $enfoque }} {{ ($enlace['activo'] ?? false) ? 'bg-celeste text-azul-oscuro' : 'text-slate-600 hover:bg-fondo hover:text-azul-oscuro' }}">
                    <x-icono :nombre="$enlace['icono']" class="h-[18px] w-[18px] shrink-0" />
                    {{ $enlace['texto'] }}
                </a>
            @endforeach
        </nav>
    </header>
</div>
