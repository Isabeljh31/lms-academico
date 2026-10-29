{{--
    "En tu radar": tira de una fila con los cierres de los próximos 7 días
    (el siguiente de cada curso, hasta 4). Recibe $resumen, $todosLosCursos y $enfoque.
--}}
@php
    $limiteRadar = now()->addDays(7)->endOfDay();
    $radar = collect($resumen)
        ->filter(fn (array $datos) => $datos['cierre'] && $datos['cierre']['fecha']->lte($limiteRadar))
        ->sortBy(fn (array $datos) => $datos['cierre']['fecha'])
        ->take(4);
@endphp

<aside aria-labelledby="titulo-radar"
    class="grid grid-cols-1 items-center gap-4 rounded-2xl border-2 border-[#111111] bg-white px-4 py-3 sm:grid-cols-2 lg:grid-cols-[116px_repeat(4,minmax(0,1fr))_auto]">
    <h2 id="titulo-radar" class="text-[13px] font-extrabold leading-tight text-[var(--p900)] sm:col-span-2 lg:col-span-1">
        <span class="mb-[3px] block text-[10px] font-semibold uppercase tracking-[0.12em] text-[var(--p700)]">Próximos 7 días</span>
        En tu radar
    </h2>

    @if ($radar->isEmpty())
        <p class="text-[12.5px] text-[#6B6559] sm:col-span-2 lg:col-span-4 lg:border-l lg:border-[#ECE6DA] lg:pl-4">Nada vence esta semana.</p>
    @else
        <ul class="contents" role="list">
            @foreach ($radar as $cursoId => $datos)
                @php $fecha = $datos['cierre']['fecha']; @endphp
                <li wire:key="radar-{{ $cursoId }}" class="flex min-w-0 items-center gap-2.5 lg:border-l lg:border-[#ECE6DA] lg:pl-4">
                    <span class="flex h-10 min-w-[38px] shrink-0 flex-col items-center justify-center rounded-[10px] bg-[var(--warn100)] text-[var(--warn800)]">
                        <span class="text-sm font-extrabold leading-none tabular-nums">{{ $fecha->format('d') }}</span>
                        <span class="mt-[3px] text-[9px] font-extrabold uppercase tracking-[0.08em]">{{ rtrim($fecha->locale('es')->isoFormat('MMM'), '.') }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="block break-words text-[12.5px] font-bold leading-tight text-[var(--p900)]">{{ $datos['cierre']['titulo'] }}</span>
                        <span class="block break-words text-[11.5px] text-[#6B6559]">{{ $todosLosCursos->get($cursoId)?->nombre }}@if ($datos['cierre']['tipo'] !== 'evaluacion') · {{ $fecha->format('H:i') }}@endif</span>
                    </span>
                </li>
            @endforeach
            @for ($vacio = $radar->count(); $vacio < 4; $vacio++)
                <li class="hidden lg:block" aria-hidden="true"></li>
            @endfor
        </ul>
    @endif

    <a href="{{ route('evento-calendario.index') }}" wire:navigate
        class="inline-flex items-center gap-1.5 justify-self-start whitespace-nowrap rounded-lg text-[12.5px] font-bold text-[var(--p700)] hover:underline lg:justify-self-end {{ $enfoque }}">
        Ver calendario completo
        <x-icono nombre="flecha-derecha" class="h-[14px] w-[14px]" />
    </a>
</aside>
