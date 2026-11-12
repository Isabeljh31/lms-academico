{{--
    Franja superior de la pantalla Cursos (diseño "Tus clases, en movimiento"),
    en versión compacta: saludo, siguiente sesión de clase y tres cifras.
    Recibe $siguienteClase, $todosLosCursos, $total, $porCalificar, $matriculados y $enfoque.
--}}
@php
    $hoyTexto = now()->locale('es')->isoFormat('dddd, D [de] MMMM');
    $claseCurso = $siguienteClase ? $todosLosCursos->get($siguienteClase->curso_id) : null;
    $cuandoClase = null;
    if ($siguienteClase) {
        $minutos = (int) abs(now()->diffInMinutes($siguienteClase->fecha));
        $cuandoClase = match (true) {
            $minutos < 60 => 'En '.max(1, $minutos).' min',
            $siguienteClase->fecha->isToday() => 'Hoy',
            $siguienteClase->fecha->isTomorrow() => 'Mañana',
            default => \Illuminate\Support\Str::ucfirst($siguienteClase->fecha->locale('es')->isoFormat('ddd D/MM')),
        };
    }
    $cifras = [
        ['valor' => $total, 'etiqueta' => $total === 1 ? 'Curso a cargo' : 'Cursos a cargo', 'icono' => 'libro'],
        ['valor' => $porCalificar, 'etiqueta' => 'Por calificar', 'icono' => 'check-circulo'],
        ['valor' => $matriculados, 'etiqueta' => 'Matriculados', 'icono' => 'usuarios'],
    ];
@endphp

<section aria-label="Resumen de tus cursos" class="overflow-hidden rounded-2xl border border-celeste bg-white shadow-sm">
    <div class="flex flex-col gap-4 px-5 py-4 sm:px-6 lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-slate-500">{{ $hoyTexto }}</p>
            <h1 class="mt-1 text-xl font-bold leading-tight tracking-tight text-texto sm:text-[1.375rem]">Tus clases, <span class="text-azul-oscuro">en movimiento.</span></h1>
            <p class="mt-0.5 text-[13px] text-slate-500">Lo que avanza, lo que sigue y dónde te necesitan.</p>
        </div>

        @if ($siguienteClase && $claseCurso)
            <a href="{{ route('cursos.show', $claseCurso) }}" wire:navigate
                class="group flex min-w-0 items-center gap-3.5 rounded-xl border border-celeste bg-fondo px-4 py-2.5 transition hover:border-azul-oscuro/30 hover:bg-celeste/60 motion-reduce:transition-none lg:w-[25rem] {{ $enfoque }}"
                aria-label="Tu siguiente clase: {{ $claseCurso->nombre }}, {{ $siguienteClase->fecha->locale('es')->isoFormat('dddd D [a las] HH:mm') }}">
                <span class="shrink-0 text-center">
                    <span class="block text-lg font-bold leading-none tabular-nums text-azul-oscuro">{{ $siguienteClase->fecha->format('H:i') }}</span>
                    <span class="mt-1 block text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ $siguienteClase->fecha->locale('es')->isoFormat('ddd D') }}</span>
                </span>
                <span class="h-9 w-px shrink-0 bg-celeste" aria-hidden="true"></span>
                <span class="min-w-0 flex-1">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-500">Tu siguiente clase</span>
                    <span class="block truncate text-sm font-semibold text-texto">{{ $claseCurso->nombre }}</span>
                    <span class="block truncate text-xs text-slate-500">{{ collect([$claseCurso->codigo, $siguienteClase->titulo])->filter()->join(' · ') }}</span>
                </span>
                <span class="hidden shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-azul-oscuro ring-1 ring-celeste sm:inline-flex">{{ $cuandoClase }}</span>
                <x-icono nombre="flecha-derecha" class="h-4 w-4 shrink-0 text-slate-400 transition group-hover:translate-x-0.5 group-hover:text-azul-oscuro motion-reduce:transition-none" />
            </a>
        @endif
    </div>

    <dl class="grid grid-cols-3 divide-x divide-celeste border-t border-celeste bg-fondo/50">
        @foreach ($cifras as $cifra)
            <div class="flex min-w-0 items-center gap-2.5 px-4 py-2.5 sm:px-6">
                <x-icono :nombre="$cifra['icono']" class="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" />
                <div class="flex min-w-0 items-baseline gap-1.5">
                    <dt class="order-2 truncate text-xs text-slate-500">{{ $cifra['etiqueta'] }}</dt>
                    <dd class="order-1 text-base font-bold tabular-nums text-texto">{{ $cifra['valor'] }}</dd>
                </div>
            </div>
        @endforeach
    </dl>
</section>
