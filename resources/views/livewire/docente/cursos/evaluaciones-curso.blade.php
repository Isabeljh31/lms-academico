@php
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $formatoPeso = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
@endphp

<section aria-labelledby="titulo-evaluaciones" class="rounded-xl border border-celeste bg-white p-4 shadow-sm">
    <h2 id="titulo-evaluaciones" class="text-lg font-semibold text-texto">Evaluaciones</h2>
    <p class="mb-2 mt-1 text-[13px] text-slate-500">Definidas por supervisión para este curso.</p>

    @forelse ($rubros as $rubro)
        @php
            $actividades = $rubro->tareas->count() + $rubro->quizzes->count();
            $porCalificar = (int) $rubro->tareas->sum(fn ($tarea) => $pendientesPorTarea[$tarea->id] ?? 0);
            $faltaDefinir = ($avances[$rubro->id]['estado'] ?? null) === 'falta_definir';
        @endphp
        <div wire:key="evaluacion-{{ $rubro->id }}" class="flex items-start gap-3 border-t border-celeste py-3">
            <span class="mt-0.5 inline-flex items-center whitespace-nowrap rounded-full bg-celeste px-2.5 py-0.5 text-xs font-bold text-azul-oscuro" aria-hidden="true">E{{ $loop->iteration }}</span>
            <div class="min-w-0 flex-1">
                <h3 class="break-words text-[15px] font-semibold text-texto">{{ $rubro->nombre }}</h3>
                <p class="text-[13px] text-slate-500">{{ $rubro->semana ? 'Semana '.$rubro->semana : 'Sin semana' }} · {{ $formatoPeso((float) $rubro->peso) }} % de la nota</p>
                @if ($faltaDefinir)
                    <p class="mt-2 flex items-start gap-2 rounded-lg bg-amber-100 px-2.5 py-1.5 text-[13px] font-medium text-amber-800">
                        <x-icono nombre="alerta" class="mt-px h-4 w-4 shrink-0" />
                        {{ $actividades === 0 ? 'Aún sin actividades asignadas' : 'Ajusta los pesos de sus actividades para que sumen 100 %' }}
                    </p>
                @elseif ($rubro->esAutomatico())
                    <p class="mt-1 text-[13px] font-medium text-exito-oscuro">{{ $plural($actividades, 'actividad', 'actividades') }} · {{ $porCalificar }} por calificar</p>
                @else
                    <p class="mt-1 text-[13px] font-medium text-slate-600">Nota manual en el libro de calificaciones</p>
                @endif
            </div>
        </div>
    @empty
        <p class="flex items-center gap-3 rounded-[10px] border border-dashed border-slate-200 p-3.5 text-sm text-slate-600">
            <x-icono nombre="info" class="h-5 w-5 shrink-0 text-azul-oscuro" />
            El Supervisor aún no definió las evaluaciones de este curso.
        </p>
    @endforelse
</section>
