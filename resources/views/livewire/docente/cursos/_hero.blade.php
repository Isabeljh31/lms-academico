{{--
    Cabecera de la pantalla Cursos (mock verde): fecha, saludo con el nombre
    completo, lo pendiente, buscador y la siguiente sesión de clase.
    Recibe $siguienteClase, $todosLosCursos, $porCalificar, $sinContenidoProximaSemana,
    $conControles y $plural.
--}}
@php
    $hoyTexto = now()->locale('es')->isoFormat('dddd, D [de] MMMM');
    // El modelo User no tiene campo de género: tratamiento neutro "Prof.".
    $tratamiento = 'Prof.';
    $nombreProfesor = (string) auth()->user()->name;
    $sinContenido = $sinContenidoProximaSemana;
    $claseCurso = $siguienteClase ? $todosLosCursos->get($siguienteClase->curso_id) : null;
    $detalleClase = $siguienteClase && $claseCurso
        ? collect([
            $claseCurso->codigo,
            $siguienteClase->numero_sesion ? 'Sesión '.$siguienteClase->numero_sesion : $siguienteClase->titulo,
            $siguienteClase->fecha->locale('es')->isoFormat('dddd D [de] MMMM'),
        ])->filter()->join(' · ')
        : null;
    $enfoque = 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-cielo focus-visible:ring-offset-2';
@endphp

{{-- Tarjeta blanca (tema editorial): fecha en rótulo, saludo en serif y, a la derecha, la siguiente clase en un recuadro oscuro. --}}
<section aria-label="Resumen de tus cursos"
    class="grid grid-cols-1 items-center gap-6 rounded-2xl border border-[var(--sec2)] bg-white px-5 py-6 sm:px-8 sm:py-8 lg:grid-cols-[minmax(0,1fr)_340px] lg:gap-9">
    <div class="min-w-0">
        <p class="rotulo text-[var(--muted)]">{{ $hoyTexto }}</p>
        {{-- El saludo se calcula en el navegador (hora local del profesor); el servidor envía "Hola". --}}
        <h1 class="mb-3 mt-2 break-words text-[38px] text-[var(--ink)] sm:text-[50px]">
            <span x-data="{ saludo: 'Hola' }"
                x-init="const hora = new Date().getHours(); saludo = hora >= 5 && hora < 12 ? 'Buenos días' : (hora >= 12 && hora < 19 ? 'Buenas tardes' : 'Buenas noches')"
                x-text="saludo">Hola</span>,<br>
            <em>{{ $tratamiento }} {{ $nombreProfesor }}</em>
        </h1>
        <p class="max-w-[560px] text-[15px] leading-[1.55] text-[var(--muted)]">
            {{-- "Tienes N entregas por calificar y M cursos sin contenido para la próxima semana." (en una línea para no dejar espacios sueltos) --}}
            @if ($porCalificar > 0 || $sinContenido > 0)Tienes @if ($porCalificar > 0)<b class="font-semibold text-[var(--ink)]">{{ $plural($porCalificar, 'entrega', 'entregas') }}</b><span> por calificar</span>@endif{{ $porCalificar > 0 && $sinContenido > 0 ? ' y ' : '' }}@if ($sinContenido > 0)<b class="font-semibold text-[var(--ink)]">{{ $plural($sinContenido, 'curso', 'cursos') }}</b><span> sin contenido para la próxima semana</span>@endif.@else Todo al día.@endif
        </p>

        {{-- Buscador: la propiedad "busqueda" de ListaCursos solo filtra con MIN_CONTROLES cursos o más. --}}
        @if ($conControles)
            <label class="mt-5 flex h-[46px] max-w-[560px] items-center gap-2.5 rounded-xl border border-[var(--sec2)] bg-[var(--mint)] px-3 text-[var(--muted)] focus-within:ring-2 focus-within:ring-cielo">
                <x-icono nombre="lupa" class="h-[18px] w-[18px] shrink-0" />
                <span class="sr-only">Buscar curso por nombre o código</span>
                <input type="search" wire:model.live.debounce.350ms="busqueda" placeholder="Buscar curso por nombre o código" autocomplete="off"
                    class="w-full min-w-0 border-0 bg-transparent p-0 text-[15px] text-[var(--ink)] placeholder:text-[var(--muted)] focus:outline-none focus:ring-0">
            </label>
        @endif
    </div>

    <div class="rounded-2xl bg-[var(--ink)] px-[22px] py-5 text-white">
        <p class="rotulo text-[var(--lime)]">Tu siguiente clase</p>
        @if ($siguienteClase && $claseCurso)
            <p class="mb-0.5 mt-2 font-serif text-[48px] leading-none tabular-nums">{{ $siguienteClase->fecha->format('H:i') }}</p>
            <p class="mt-2 break-words text-[17px] font-semibold">{{ $claseCurso->nombre }}</p>
            <p class="mt-0.5 break-words text-xs text-[var(--claro)]">{{ $detalleClase }}</p>
            <a href="{{ route('cursos.show', $claseCurso) }}" wire:navigate
                aria-label="Ver sesión: {{ $claseCurso->nombre }}, {{ $siguienteClase->fecha->locale('es')->isoFormat('dddd D [a las] HH:mm') }}"
                class="mt-4 inline-flex items-center gap-2 rounded-lg bg-white px-[15px] py-[9px] text-[13px] font-semibold text-[var(--ink)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoque }} focus-visible:ring-offset-[var(--ink)]">
                Ver sesión
                <x-icono nombre="flecha-derecha" class="h-[14px] w-[14px]" />
            </a>
        @else
            <p class="mt-2 font-serif text-[26px] leading-tight">Sin clases programadas</p>
        @endif
    </div>
</section>
