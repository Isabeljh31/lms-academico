{{--
    Curso como tarjeta compacta (mock verde): banda con el color del curso,
    recorrido por semanas, próxima entrega en una línea y acciones. Toda la
    tarjeta abre el curso (enlace extendido de "Abrir"); los demás enlaces
    quedan por encima.
    Recibe $curso, $datos, $estadoTexto, $modalidades, $plural, $pastilla y $enfoque.
--}}
@php
    $color = $datos['color'];
    $totalSemanas = max(1, (int) $datos['semanas']);
    $semanasLlenas = (int) round($datos['avance']['porcentaje'] * $totalSemanas / 100);
    $modalidad = $curso->modalidad ? ($modalidades[$curso->modalidad] ?? ucfirst($curso->modalidad)) : null;
    $colorModalidad = match ($curso->modalidad) {
        'virtual' => 'text-[var(--inf700)]',
        'presencial' => 'text-[var(--ok900)]',
        default => 'text-[var(--p700)]',
    };

    // Próxima entrega: "Práctica 3: tema" en una línea; etiqueta según los días que faltan.
    $cierre = $curso->estaFinalizado() ? null : $datos['cierre'];
    $diasCierre = $cierre ? \App\Services\Reportes\PanelProfesor::diasHasta($cierre['fecha']) : null;
    $tituloCierre = $cierre ? (string) $cierre['titulo'] : '';
    [$tipoCierre, $temaCierre] = str_contains($tituloCierre, ': ')
        ? explode(': ', $tituloCierre, 2)
        : [$tituloCierre, null];
    // Etiqueta de una fecha de entrega: "vencida", "hoy", "mañana" o "en N días", con su color.
    $etiquetaFecha = function (\Illuminate\Support\Carbon $fecha): array {
        $dias = \App\Services\Reportes\PanelProfesor::diasHasta($fecha);

        return match (true) {
            $dias < 0 => ['vencida', 'bg-[var(--err100)] text-[var(--err700)]'],
            $dias <= 1 => [\App\Services\Reportes\PanelProfesor::textoDias($dias), 'bg-[var(--warn200)] text-[var(--warn800)]'],
            default => [\App\Services\Reportes\PanelProfesor::textoDias($dias), 'bg-[var(--mint)] text-[var(--p700)]'],
        };
    };
    [$textoEtiqueta, $claseEtiqueta] = $diasCierre === null ? [null, null] : $etiquetaFecha($cierre['fecha']);

    // Semana actual (CursosProfesor::semanaActual) y sus tareas (CursosProfesor::tareasDeLaSemanaActual).
    $numeroSemana = $curso->estaFinalizado() ? null : ($datos['numero_semana_actual'] ?? null);
    $deLaSemana = ($tareasSemana ?? [])[$curso->id] ?? ['semana' => [], 'practicas' => []];

    // Semana actual vacía: chip corto en el bloque "Esta semana" (la siguiente tiene su propio filtro).
    $semanaVacia = collect($datos['para_hacer'])->firstWhere('tipo', 'semana_actual');
    $chipSemanaVacia = $semanaVacia ? \Illuminate\Support\Str::before((string) $datos['semana_actual'], ' · ').' sin contenido' : null;
@endphp

<article aria-labelledby="curso-{{ $curso->id }}-nombre"
    class="group relative flex w-full flex-col rounded-[14px] border-2 border-[#111111] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_4px_14px_rgba(9,59,59,0.05)] transition-[transform,box-shadow] duration-150 hover:-translate-y-[2px] hover:shadow-[0_10px_28px_rgba(9,59,59,0.16)] motion-reduce:transition-none motion-reduce:hover:translate-y-0">

    {{-- Banda del curso en pastel: todo sale del color de ColoresCursos (--c) con color-mix.
         Con el neutro (#94A3B8) el cuadro de iniciales al 85 % no llega a AA con texto blanco (3,5:1): se oscurece al 60 % (6,2:1). --}}
    <div class="relative flex min-h-[58px] items-center gap-2.5 rounded-t-[12px] border-b border-[color:color-mix(in_srgb,var(--c)_30%,#fff)] bg-[color:color-mix(in_srgb,var(--c)_20%,#fff)] py-2 pl-3 pr-[76px]"
        style="--c: {{ $color }}; --c-cuadro: {{ $color === \App\Services\Calendario\ColoresCursos::NEUTRO ? '60%' : '85%' }};">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-[9px] bg-[color:color-mix(in_srgb,var(--c)_var(--c-cuadro),#000)] text-[13px] font-extrabold text-white" aria-hidden="true">{{ $datos['iniciales'] }}</span>
        <div class="min-w-0">
            <p class="mb-px flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] font-bold tracking-[0.06em] text-[color:color-mix(in_srgb,var(--c)_60%,#000)]">
                @if ($datos['codigo'])
                    <span class="break-words">{{ $datos['codigo'] }}</span>
                @endif
                @if ($curso->estado !== 'publicado')
                    <span class="inline-flex items-center whitespace-nowrap rounded-full bg-white/70 px-1.5 py-px text-[10px] font-bold tracking-normal text-slate-700">{{ $estadoTexto }}</span>
                @endif
            </p>
            <h3 id="curso-{{ $curso->id }}-nombre" class="break-words text-[15px] font-extrabold leading-[1.15] tracking-[-0.01em] text-[color:color-mix(in_srgb,var(--c)_45%,#000)]">{{ $curso->nombre }}</h3>
        </div>
        @if ($modalidad)
            <span class="absolute right-2 top-2 rounded-full bg-white px-2 py-px text-[10px] font-semibold ring-1 ring-black/[.08] {{ $colorModalidad }}">{{ $modalidad }}</span>
        @endif
    </div>

    {{-- Cuerpo: recorrido y próxima entrega. --}}
    <div class="flex-1 px-3 pb-2.5 pt-2">
        <div class="mb-1 flex items-baseline justify-between gap-x-3 text-[11px] font-medium text-[#4b625e]">
            <span class="truncate">{{ $datos['avance']['texto'] }}</span>
            @if ($datos['avance']['detalle'])
                <span class="shrink-0 font-bold tabular-nums text-[var(--p900)]">{{ $datos['avance']['detalle'] }}</span>
            @endif
        </div>
        <div class="flex gap-[2px]" role="progressbar" aria-label="Avance del curso {{ $curso->nombre }}"
            aria-valuenow="{{ $datos['avance']['porcentaje'] }}" aria-valuemin="0" aria-valuemax="100">
            @if ($totalSemanas <= 24)
                @foreach (range(1, $totalSemanas) as $semana)
                    <span class="h-[3px] flex-1 rounded-full {{ $semana > $semanasLlenas ? 'bg-[#e4eeeb]' : '' }}"
                        @if ($semana <= $semanasLlenas) style="background-color: {{ $color }}" @endif></span>
                @endforeach
            @else
                <span class="h-[3px] flex-1 rounded-full bg-[#e4eeeb]"><span class="block h-[3px] rounded-full" style="width: {{ $datos['avance']['porcentaje'] }}%; background-color: {{ $color }}"></span></span>
            @endif
        </div>

        @if ($numeroSemana !== null)
            {{-- Semana actual: tareas del módulo de la semana (título tal como está guardado) y, aparte, prácticas de campo con fecha en esta semana. --}}
            <div class="mt-2 flex min-w-0 flex-col gap-1.5">
                <p class="text-[10.5px] font-bold uppercase tracking-[0.08em] text-[#5b706c]">Esta semana · Semana {{ $numeroSemana }}</p>
                @if ($semanaVacia)
                    <a href="{{ $semanaVacia['url'] }}" wire:navigate
                        class="relative z-10 inline-flex items-center gap-1 self-start whitespace-nowrap rounded-full bg-[var(--warn200)] px-2 py-px text-[11px] font-semibold text-[var(--warn800)] transition-colors hover:bg-[#ffe68a] motion-reduce:transition-none {{ $enfoque }}"
                        aria-label="{{ $semanaVacia['texto'] }}: {{ $semanaVacia['accion'] }} ({{ $curso->nombre }})">
                        <x-icono nombre="alerta" class="h-[13px] w-[13px] shrink-0" />
                        {{ $chipSemanaVacia }}
                    </a>
                @else
                    @foreach (array_filter(['Tarea de semana' => $deLaSemana['semana'], 'Práctica de campo' => $deLaSemana['practicas']]) as $tipoActividad => $actividades)
                        @php
                            $primera = $actividades[0];
                            $otras = count($actividades) - 1;
                            [$textoFecha, $claseFecha] = $etiquetaFecha($primera['fecha']);
                        @endphp
                        <div class="min-w-0 text-xs text-[#44605b]" data-actividad="{{ $tipoActividad }}">
                            <div class="flex min-w-0 items-center gap-1.5">
                                <p class="min-w-0 flex-1 truncate font-bold text-[var(--p900)]" title="{{ $primera['titulo'] }}">{{ $primera['titulo'] }}</p>
                                <time datetime="{{ $primera['fecha']->toDateString() }}" title="{{ $primera['fecha']->format('d/m/Y H:i') }}"
                                    class="shrink-0 whitespace-nowrap rounded-full px-1.5 py-px text-[11px] font-bold {{ $claseFecha }}">{{ $textoFecha }}</time>
                            </div>
                            <div class="mt-0.5 flex items-center gap-1.5">
                                <span class="rounded bg-[#eef4f2] px-1.5 py-px text-[10.5px] font-semibold text-[#4b625e]">{{ $tipoActividad }}</span>
                                @if ($otras > 0)
                                    <span class="text-[11px] font-semibold text-[var(--p700)]"
                                        title="{{ collect($actividades)->map(fn (array $actividad) => $actividad['titulo'].' · '.$etiquetaFecha($actividad['fecha'])[0])->join("\n") }}">+{{ $otras }} más</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if ($deLaSemana['semana'] === [] && $deLaSemana['practicas'] === [])
                        <p class="text-xs text-[#5b706c]">Sin tareas esta semana</p>
                    @endif
                @endif
            </div>
        @else
            <div class="mt-2 flex min-w-0 items-center gap-1.5 text-xs text-[#44605b]">
                <x-icono nombre="calendario" class="h-[14px] w-[14px] shrink-0 text-[var(--p700)]" />
                <p class="min-w-0 flex-1 truncate" @if ($cierre) title="{{ $tituloCierre }}" @endif>
                    @if ($cierre)
                        <span class="sr-only">Próxima entrega: </span><b class="font-bold text-[var(--p900)]">{{ $tipoCierre }}</b>@if ($temaCierre)<span>: {{ $temaCierre }}</span>@endif
                    @else
                        {{ $curso->estaFinalizado() ? 'Curso cerrado' : 'Sin entregas próximas' }}
                    @endif
                </p>
                @if ($textoEtiqueta)
                    <time datetime="{{ $cierre['fecha']->toDateString() }}" title="{{ $cierre['fecha']->format('d/m/Y') }}"
                        class="shrink-0 whitespace-nowrap rounded-full px-1.5 py-px text-[11px] font-bold {{ $claseEtiqueta }}">{{ $textoEtiqueta }}</time>
                @endif
            </div>
        @endif
    </div>

    {{-- Pie: matriculados, "Abrir" (su área cubre la tarjeta) y Calificar o "Al día". --}}
    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1.5 border-t border-[#edf3f1] px-3 py-2">
        <p class="flex items-baseline gap-1 text-[11.5px] text-[#4b625e]">
            <b class="text-[13px] font-bold tabular-nums text-[var(--p900)]">{{ $curso->inscripciones_activas_count }}{{ $curso->cupo_maximo ? ' / '.$curso->cupo_maximo : '' }}</b>
            matriculados
        </p>
        <div class="flex items-center gap-1.5">
            <a href="{{ route('cursos.show', $curso) }}" wire:navigate aria-label="Abrir curso {{ $curso->nombre }}"
                class="inline-flex h-8 items-center gap-1 rounded-full border-[1.5px] border-[#c9ddd8] px-3 text-[12.5px] font-bold text-[var(--p700)] transition-colors after:absolute after:inset-0 after:rounded-[14px] after:content-[''] hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoque }}">
                Abrir
                <x-icono nombre="flecha-derecha" class="h-[13px] w-[13px]" />
            </a>
            @if ($datos['pendientes'] > 0)
                <a href="{{ route('docente.por-calificar', ['curso' => $curso->id]) }}" wire:navigate
                    aria-label="Calificar {{ $plural($datos['pendientes'], 'entrega', 'entregas') }} de {{ $curso->nombre }}"
                    class="relative z-10 inline-flex h-8 items-center gap-1.5 rounded-full bg-[var(--p900)] pl-3 pr-[5px] text-[12.5px] font-bold text-white transition-colors hover:bg-[var(--p800)] motion-reduce:transition-none {{ $enfoque }}">
                    Calificar
                    <span class="grid h-5 min-w-[22px] place-items-center rounded-full bg-white px-1.5 text-[11px] font-extrabold tabular-nums text-[var(--p900)]">{{ $datos['pendientes'] }}</span>
                </a>
            @else
                <span class="inline-flex h-8 items-center gap-1 rounded-full bg-[var(--ok100)] px-3 text-[12.5px] font-bold text-[var(--ok900)]">
                    <x-icono nombre="check" class="h-[14px] w-[14px]" />
                    Al día
                </span>
            @endif
        </div>
    </div>
</article>
