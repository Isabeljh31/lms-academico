{{--
    Curso como fila (diseño "Tu mapa de trabajo"): identidad, recorrido por
    semanas, matriculados y entregas por revisar. Recibe $curso, $datos,
    $estadoTexto, $estadoClase, $modalidades, $cierreTexto, $plural, $pastilla y $enfoque.
--}}
@php
    $totalSemanas = max(1, (int) $datos['semanas']);
    $semanasLlenas = (int) round($datos['avance']['porcentaje'] * $totalSemanas / 100);
    $actualEnCurso = $datos['avance']['estado'] === 'en_curso';
    $modalidad = $curso->modalidad ? ($modalidades[$curso->modalidad] ?? ucfirst($curso->modalidad)) : null;
@endphp

<article aria-labelledby="curso-{{ $curso->id }}-nombre"
    class="relative grid grid-cols-[auto_minmax(0,1fr)_auto] items-center gap-x-4 gap-y-3 overflow-hidden rounded-2xl border border-celeste bg-white py-3.5 pl-5 pr-4 shadow-sm transition-shadow hover:shadow-md hover:shadow-texto/10 motion-reduce:transition-none md:grid-cols-[auto_minmax(0,1fr)_minmax(0,1.3fr)_8.5rem_auto]">
    <span class="absolute inset-y-0 left-0 w-1" style="background-color: {{ $datos['color'] }}" aria-hidden="true"></span>

    <span class="order-1 flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-[13px] font-bold text-white shadow-sm" style="background-color: {{ $datos['color'] }}" aria-hidden="true">{{ $datos['iniciales'] }}</span>

    <div class="order-2 min-w-0">
        @if ($datos['codigo'])
            <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-500">{{ $datos['codigo'] }}</p>
        @endif
        <a href="{{ route('cursos.show', $curso) }}" wire:navigate class="rounded-md text-texto hover:text-azul-oscuro {{ $enfoque }}">
            <h3 id="curso-{{ $curso->id }}-nombre" class="break-words text-[15px] font-bold leading-snug">{{ $curso->nombre }}</h3>
        </a>
        <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            @if ($modalidad)
                <span>{{ $modalidad }}</span>
            @endif
            @if ($curso->estado !== 'publicado')
                <span class="{{ $pastilla }} {{ $estadoClase }} px-2 text-[11px]">{{ $estadoTexto }}</span>
            @endif
        </p>
    </div>

    {{-- Recorrido: un tramo por semana (barra continua si son muchas). --}}
    <div class="order-4 col-span-3 min-w-0 md:order-3 md:col-span-1">
        <div class="mb-1.5 flex items-baseline justify-between gap-3 text-[11px]">
            <span class="font-semibold uppercase tracking-wide text-slate-500">Recorrido</span>
            <span class="truncate font-bold text-texto">{{ $datos['avance']['texto'] }}{{ $actualEnCurso && $datos['avance']['detalle'] ? ' · '.$datos['avance']['detalle'] : '' }}@if (! $actualEnCurso && $datos['avance']['detalle'])<span class="font-medium text-slate-500"> · {{ $datos['avance']['detalle'] }}</span>@endif</span>
        </div>
        <div class="flex gap-1" role="progressbar" aria-label="Avance del curso {{ $curso->nombre }}"
            aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
            @if ($totalSemanas <= 24)
                @foreach (range(1, $totalSemanas) as $semana)
                    <span class="h-1.5 flex-1 rounded-full {{ $semana > $semanasLlenas ? 'bg-celeste' : '' }}"
                        @if ($semana <= $semanasLlenas) style="background-color: {{ $datos['color'] }}{{ $actualEnCurso && $semana === $semanasLlenas ? 'A6' : '' }}" @endif></span>
                @endforeach
            @else
                <span class="h-1.5 flex-1 rounded-full bg-celeste"><span class="block h-1.5 rounded-full" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $datos['color'] }}"></span></span>
            @endif
        </div>
        <p class="mt-1.5 truncate text-xs text-slate-500" title="{{ $cierreTexto($curso, $datos) }}">{{ $cierreTexto($curso, $datos) }}</p>
        @if ($datos['aviso'])
            @php $accionAviso = collect($datos['para_hacer'])->firstWhere('texto', $datos['aviso']); @endphp
            <p class="mt-1 flex min-w-0 items-center gap-1 text-xs font-medium text-amber-800">
                <x-icono nombre="alerta" class="h-3.5 w-3.5 shrink-0" />
                <span class="truncate" title="{{ $datos['aviso'] }}">{{ $datos['aviso'] }}</span>
                @if ($accionAviso)
                    <a href="{{ $accionAviso['url'] }}" wire:navigate class="shrink-0 rounded font-semibold text-azul-oscuro underline-offset-2 hover:underline {{ $enfoque }}"
                        aria-label="{{ $accionAviso['accion'] }}: {{ $accionAviso['texto'] }} ({{ $curso->nombre }})">· {{ $accionAviso['accion'] }}</a>
                @endif
            </p>
        @endif
    </div>

    <div class="order-5 col-span-3 flex items-center gap-3 md:order-4 md:col-span-1 md:flex-col md:items-start md:gap-1.5">
        <p class="flex items-center gap-1.5 text-xs text-slate-600">
            <x-icono nombre="usuarios" class="h-4 w-4 shrink-0 text-slate-400" />
            <span><b class="font-bold tabular-nums text-texto">{{ $curso->inscripciones_activas_count }}</b>{{ $curso->cupo_maximo ? ' / '.$curso->cupo_maximo : '' }}<span class="sr-only"> matriculados</span></span>
        </p>
        @if ($datos['pendientes'] > 0)
            <a href="{{ route('docente.por-calificar') }}" wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg bg-amber-50 py-1 pl-1 pr-2.5 text-xs font-semibold text-amber-800 ring-1 ring-amber-200 transition hover:bg-amber-100 motion-reduce:transition-none {{ $enfoque }}"
                aria-label="Calificar {{ $plural($datos['pendientes'], 'entrega', 'entregas') }} de {{ $curso->nombre }}">
                <span class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-md bg-amber-600 px-1 text-[11px] font-bold tabular-nums text-white">{{ $datos['pendientes'] }}</span>
                por revisar
            </a>
        @else
            <span class="inline-flex items-center gap-1.5 rounded-lg bg-exito-base/10 py-1 pl-1 pr-2.5 text-xs font-semibold text-exito-oscuro ring-1 ring-exito-base/25">
                <span class="flex h-5 w-5 items-center justify-center rounded-md bg-exito-oscuro text-white"><x-icono nombre="check" class="h-3 w-3" /></span>
                al día
            </span>
        @endif
    </div>

    <a href="{{ route('cursos.show', $curso) }}" wire:navigate aria-label="Abrir curso {{ $curso->nombre }}"
        class="order-3 flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-celeste text-azul-oscuro transition hover:border-azul-oscuro hover:bg-azul-oscuro hover:text-white motion-reduce:transition-none md:order-5 {{ $enfoque }}">
        <x-icono nombre="flecha-derecha" class="h-[18px] w-[18px]" />
    </a>
</article>
