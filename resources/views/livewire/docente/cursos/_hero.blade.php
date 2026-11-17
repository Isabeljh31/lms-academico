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
    $enfoqueOscuro ='focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--lime)]';
@endphp

<section aria-label="Resumen de tus cursos"
    class="grid grid-cols-1 items-center gap-6 rounded-[20px] bg-[var(--p900)] px-[22px] py-[26px] text-white sm:rounded-3xl sm:px-12 sm:py-9 lg:grid-cols-[minmax(0,1fr)_360px] lg:gap-9">
    <div class="min-w-0">
        <p class="text-[11px] font-semibold uppercase tracking-[0.22em] text-[var(--sec2)]">{{ $hoyTexto }}</p>
        {{-- El saludo se calcula en el navegador (hora local del profesor); el servidor envía "Hola". --}}
        <h1 class="mb-3 mt-2.5 break-words text-[28px] font-extrabold leading-[1.12] tracking-[-0.02em] sm:text-[36px]">
            <span x-data="{ saludo: 'Hola' }"
                x-init="const hora = new Date().getHours(); saludo = hora >= 5 && hora < 12 ? 'Buenos días' : (hora >= 12 && hora < 19 ? 'Buenas tardes' : 'Buenas noches')"
                x-text="saludo">Hola</span>,<br>
            {{ $tratamiento }} {{ $nombreProfesor }}
        </h1>
        <p class="max-w-[560px] text-[15px] leading-[1.55] text-[#d3e3df]">
            {{-- "Tienes N entregas por calificar y M cursos sin contenido para la próxima semana." (en una línea para no dejar espacios sueltos) --}}
            @if ($porCalificar > 0 || $sinContenido > 0)Tienes @if ($porCalificar > 0)<b class="font-bold text-[var(--lime)]">{{ $plural($porCalificar, 'entrega', 'entregas') }}</b><span> por calificar</span>@endif{{ $porCalificar > 0 && $sinContenido > 0 ? ' y ' : '' }}@if ($sinContenido > 0)<b class="font-bold text-[var(--lime)]">{{ $plural($sinContenido, 'curso', 'cursos') }}</b><span> sin contenido para la próxima semana</span>@endif.@else Todo al día.@endif
        </p>

        {{-- Buscador: la propiedad "busqueda" de ListaCursos solo filtra con MIN_CONTROLES cursos o más. --}}
        @if ($conControles)
            <label class="mt-5 flex h-[46px] max-w-[560px] items-center gap-2.5 rounded-[10px] border-2 border-[#111111] bg-[var(--mint)] px-3 text-[#5b706c] focus-within:outline focus-within:outline-[3px] focus-within:outline-offset-2 focus-within:outline-[color:var(--lime)]">
                <x-icono nombre="lupa" class="h-[18px] w-[18px] shrink-0" />
                <span class="sr-only">Buscar curso por nombre o código</span>
                <input type="search" wire:model.live.debounce.350ms="busqueda" placeholder="Buscar curso por nombre o código" autocomplete="off"
                    class="w-full min-w-0 border-0 bg-transparent p-0 text-[15px] text-[var(--ink)] placeholder:text-[#5b706c] focus:outline-none focus:ring-0">
            </label>
        @endif
    </div>

    <div class="rounded-[18px] bg-[var(--p800)] px-[22px] py-5">
        <p class="text-[11px] font-semibold uppercase tracking-[0.16em] text-[var(--sec2)]">Tu siguiente clase</p>
        @if ($siguienteClase && $claseCurso)
            <p class="mb-0.5 mt-2 text-[44px] font-extrabold leading-none tracking-[-0.02em] tabular-nums">{{ $siguienteClase->fecha->format('H:i') }}</p>
            <p class="mt-2 break-words text-[17px] font-bold">{{ $claseCurso->nombre }}</p>
            <p class="mt-0.5 break-words text-xs text-[#cfe0db]">{{ $detalleClase }}</p>
            <a href="{{ route('cursos.show', $claseCurso) }}" wire:navigate
                aria-label="Ver sesión: {{ $claseCurso->nombre }}, {{ $siguienteClase->fecha->locale('es')->isoFormat('dddd D [a las] HH:mm') }}"
                class="mt-4 inline-flex items-center gap-2 rounded-[10px] bg-white px-[15px] py-[9px] text-[13px] font-bold text-[var(--p900)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {{ $enfoqueOscuro }}">
                Ver sesión
                <x-icono nombre="flecha-derecha" class="h-[14px] w-[14px]" />
            </a>
        @else
            <p class="mt-2 text-[17px] font-bold">Sin clases programadas</p>
        @endif
    </div>
</section>
