{{--
    "En tu radar": los cierres de los próximos 7 días (el siguiente de cada curso).
    Recibe $resumen, $todosLosCursos y $enfoque.
--}}
@php
    $limiteRadar = now()->addDays(7)->endOfDay();
    $radar = collect($resumen)
        ->filter(fn (array $datos) => $datos['cierre'] && $datos['cierre']['fecha']->lte($limiteRadar))
        ->sortBy(fn (array $datos) => $datos['cierre']['fecha'])
        ->take(5);
@endphp

<aside aria-labelledby="titulo-radar" class="flex flex-col overflow-hidden rounded-2xl border border-celeste bg-white shadow-sm">
    <div class="flex items-center justify-between gap-3 px-4 pb-2.5 pt-4">
        <div>
            <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-slate-500">Próximos 7 días</p>
            <h2 id="titulo-radar" class="text-base font-bold text-texto">En tu radar</h2>
        </div>
        <x-icono nombre="calendario" class="h-5 w-5 text-slate-400" />
    </div>

    @if ($radar->isEmpty())
        <p class="mx-4 mb-3 rounded-xl bg-fondo px-3 py-4 text-center text-[13px] text-slate-500">Nada vence esta semana.</p>
    @else
        <ul class="flex flex-col px-4">
            @foreach ($radar as $cursoId => $datos)
                @php $fecha = $datos['cierre']['fecha']; @endphp
                <li wire:key="radar-{{ $cursoId }}" class="flex items-center gap-3 border-t border-celeste py-2.5 first:border-t-0">
                    <span class="flex w-10 shrink-0 flex-col items-center rounded-lg bg-celeste py-1 text-azul-oscuro">
                        <span class="text-[15px] font-bold leading-none tabular-nums">{{ $fecha->format('d') }}</span>
                        <span class="mt-0.5 text-[9px] font-semibold uppercase tracking-wide text-slate-500">{{ rtrim($fecha->locale('es')->isoFormat('MMM'), '.') }}</span>
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-[13px] font-semibold text-texto">{{ $datos['cierre']['titulo'] }}</span>
                        <span class="flex min-w-0 items-center gap-1.5 text-[11px] text-slate-500">
                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $datos['color'] }}" aria-hidden="true"></span>
                            <span class="truncate">{{ $todosLosCursos->get($cursoId)?->nombre }}@if ($datos['cierre']['tipo'] !== 'evaluacion') · {{ $fecha->format('H:i') }}@endif</span>
                        </span>
                    </span>
                </li>
            @endforeach
        </ul>
    @endif

    <a href="{{ route('evento-calendario.index') }}" wire:navigate
        class="mt-auto flex min-h-11 items-center justify-between border-t border-celeste px-4 text-xs font-semibold text-azul-oscuro transition hover:bg-fondo motion-reduce:transition-none {{ $enfoque }} focus-visible:ring-inset focus-visible:ring-offset-0">
        Ver calendario completo
        <x-icono nombre="flecha-derecha" class="h-4 w-4" />
    </a>
</aside>
