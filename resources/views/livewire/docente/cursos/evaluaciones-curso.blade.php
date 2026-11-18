{{--
    Tarjeta "Evaluaciones" del detalle del curso del Profesor: las definió el
    Supervisor (solo lectura). Barra con la distribución de pesos y, por
    evaluación, sus actividades y lo que espera nota (urgencia de Por calificar).
    "Asignar" lleva a Actividades, donde el profesor ya asigna actividades.
--}}
@use('App\Livewire\Docente\PorCalificar\Index', 'PorCalificar')
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $plural = fn (int $n, string $uno, string $varios) => $n === 1 ? "1 {$uno}" : "{$n} {$varios}";
    $formatoPeso = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    $tonos = ['#2F5A44', '#3A3731', '#1D1C19', '#7A7264', '#B9AE98', '#E0D8C8'];
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
@endphp

<section aria-labelledby="titulo-evaluaciones" class="rounded-[20px] bg-white px-[22px] py-5 shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]">
    <h2 id="titulo-evaluaciones" class="text-[17px] font-extrabold text-[var(--p900)]">Evaluaciones</h2>
    <p class="mt-[3px] text-[12.5px] text-[#6B6559]">Definidas por supervisión para este curso</p>

    @if ($rubros->isNotEmpty())
        <div class="mb-1 mt-3.5 flex gap-1" role="img" aria-label="Distribución de la nota: {{ $rubros->map(fn ($rubro) => $rubro->nombre.' '.$formatoPeso((float) $rubro->peso).' %')->join(', ') }}">
            @foreach ($rubros as $rubro)
                <span class="block h-2 rounded" style="flex: {{ max(1, (float) $rubro->peso) }}; background-color: {{ $tonos[$loop->index % count($tonos)] }}"></span>
            @endforeach
        </div>
    @endif

    @forelse ($rubros as $rubro)
        @php
            $actividades = $rubro->tareas->count() + $rubro->quizzes->count();
            $porCalificar = (int) $rubro->tareas->sum(fn ($tarea) => $pendientesPorTarea[$tarea->id] ?? 0);
            $masAntigua = $rubro->tareas->map(fn ($tarea) => $masAntiguaPorTarea[$tarea->id] ?? null)->filter()->min();
            $urgencia = $masAntigua ? DetalleCursoProfesor::estiloUrgencia(PorCalificar::diasEsperando($masAntigua)) : null;
            $faltaDefinir = ($avances[$rubro->id]['estado'] ?? null) === 'falta_definir';
        @endphp
        <div wire:key="evaluacion-{{ $rubro->id }}" class="flex gap-3 border-t border-[#F4F0E8] py-3.5 first-of-type:border-t-0">
            <span class="grid h-[30px] w-[30px] shrink-0 place-items-center rounded-[9px] bg-[var(--mint)] text-[11.5px] font-extrabold text-[var(--p700)]" aria-hidden="true">E{{ $loop->iteration }}</span>
            <div class="min-w-0 flex-1">
                <h3 class="break-words text-sm font-bold text-[var(--p900)]">{{ $rubro->nombre }}</h3>
                <p class="mt-0.5 text-[12.5px] text-[#6B6559]">{{ $rubro->semana ? 'Semana '.$rubro->semana : 'Sin semana' }} · {{ $formatoPeso((float) $rubro->peso) }} % de la nota</p>
                @if ($faltaDefinir)
                    <p class="mt-2 flex items-center gap-2 rounded-[10px] bg-[var(--warn200)] px-[11px] py-2 text-[12.5px] font-semibold text-[var(--warn800)]">
                        <x-icono nombre="alerta" class="h-4 w-4 shrink-0" />
                        <span class="min-w-0 flex-1">{{ $actividades === 0 ? 'Aún sin actividades asignadas' : 'Ajusta los pesos de sus actividades para que sumen 100 %' }}</span>
                        @if ($puedeAsignar)
                            <a href="{{ route('cursos.actividades', $curso) }}" wire:navigate class="shrink-0 rounded font-bold text-[var(--p700)] hover:underline {{ $enfoque }}" aria-label="Asignar actividades a {{ $rubro->nombre }}">Asignar</a>
                        @endif
                    </p>
                @elseif ($rubro->esAutomatico())
                    <p class="mt-0.5 flex flex-wrap items-center gap-1.5 text-[12.5px] text-[#6B6559]">
                        {{ $plural($actividades, 'actividad', 'actividades') }} ·
                        @if ($porCalificar > 0)
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-[9px] py-0.5 text-[11.5px] font-bold {{ $urgencia['pastilla'] }}"><span class="h-[9px] w-[9px] rounded-full {{ $urgencia['punto'] }}" aria-hidden="true"></span>{{ $porCalificar }} por calificar</span>
                        @else
                            <span class="font-semibold text-[var(--ok900)]">0 por calificar</span>
                        @endif
                    </p>
                @else
                    <p class="mt-0.5 text-[12.5px] text-[#6B6559]">Nota manual en el libro de calificaciones</p>
                @endif
            </div>
        </div>
    @empty
        <p class="mt-3 flex items-center gap-3 rounded-xl border border-dashed border-[#E0D8C8] p-3.5 text-sm text-[#57524A]">
            <x-icono nombre="info" class="h-5 w-5 shrink-0 text-[var(--p700)]" />
            El Supervisor aún no definió las evaluaciones de este curso.
        </p>
    @endforelse
</section>
