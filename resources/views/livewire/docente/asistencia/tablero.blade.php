{{--
    Indicadores y pestañas de la Asistencia del Profesor. Los datos salen de
    App\Services\Reportes\AsistenciaProfesor (consultas fijas). «Pasar lista»,
    «Ver lista» y «Eliminar» llaman a Asistencia\Index con $parent.
--}}
@use('App\Services\Reportes\AsistenciaProfesor')
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $tarjeta = 'rounded-[20px] bg-white shadow-[0_1px_2px_rgba(9,59,59,0.06),0_6px_20px_rgba(9,59,59,0.05)]';
    $etiqueta = 'text-[11px] font-bold uppercase tracking-[0.12em] text-[#6B6559]';
    $cifra = 'text-[34px] font-extrabold leading-none tracking-[-0.02em] text-[var(--p900)]';
    $enlace = "inline-flex min-h-8 items-center gap-1.5 rounded text-[13px] font-bold text-[var(--p700)] hover:underline {$enfoque}";
    $editable = $curso->usa_asistencia && ! $curso->estaFinalizado();
    // Colores de los estados de asistencia (justificado: gris azulado neutro).
    $coloresEstado = [
        'presente' => ['texto' => 'Presente', 'color' => '#2F5A44'],
        'tardanza' => ['texto' => 'Tardanza', 'color' => '#F2B233'],
        'justificado' => ['texto' => 'Justificado', 'color' => '#8A9BB3'],
        'ausente' => ['texto' => 'Ausente', 'color' => '#E04B55'],
    ];
    $rangoTexto = fn (?array $rango) => $rango ? str_replace('.', '', $rango[0]->copy()->locale('es')->isoFormat('D MMM').' – '.$rango[1]->copy()->locale('es')->isoFormat('D MMM')) : null;
    $actual = $semanas[$semana] ?? null;
    $sinFechas = ($semanas[1]['rango'] ?? null) === null;
@endphp

<div class="flex flex-col gap-4">
    @if ($virtual)
        <div role="note" class="flex items-start gap-3 rounded-xl bg-[var(--inf100)] px-4 py-3 text-[13.5px] text-[var(--inf700)]">
            <x-icono nombre="info" class="mt-0.5 h-5 w-5 shrink-0" />
            <p><strong>Este curso no registra asistencia por clase.</strong> Como referencia, aquí ves las lecciones que completó cada estudiante (solo lectura): las marca el estudiante o se registran al abrir su recurso, según cómo configuraste cada lección.</p>
        </div>
    @else
        @include('livewire.docente.asistencia._indicadores')
    @endif

    <section class="{{ $tarjeta }} overflow-hidden" aria-label="Asistencia por semana y por estudiante">
        <div class="flex flex-wrap items-end justify-between gap-3 border-b border-[#ECE6DA] px-7">
            <div class="flex gap-7" role="tablist" aria-label="Vista de asistencia">
                @foreach (['semana' => ['Por semana', $sinFechas ? null : $totalSemanas], 'resumen' => ['Resumen por estudiante', $virtual ? null : $resumen['activos']]] as $clave => [$texto, $cuenta])
                    <button type="button" role="tab" id="pestana-{{ $clave }}" aria-controls="panel-asistencia" aria-selected="{{ $pestana === $clave ? 'true' : 'false' }}"
                        wire:click="verPestana('{{ $clave }}')"
                        class="relative inline-flex min-h-[56px] items-center gap-2 text-[15px] {{ $enfoque }} {{ $pestana === $clave ? 'font-bold text-[var(--p900)] after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:rounded-t-[3px] after:bg-[var(--p900)]' : 'text-[#57524A] hover:text-[var(--p900)]' }}">
                        {{ $texto }}
                        @if ($cuenta)
                            <span class="rounded-full bg-[#ECE6DA] px-[7px] py-px text-[11.5px] font-bold text-[#57524A]">{{ $cuenta }}</span>
                        @endif
                    </button>
                @endforeach
            </div>

            @if (! $virtual && $pestana === 'resumen')
                <div class="flex flex-wrap gap-2 pb-3" role="group" aria-label="Filtrar estudiantes por nivel">
                    @foreach (['todos' => ['Todos', $resumen['activos'], null], 'riesgo' => ['En riesgo', $resumen['niveles']['riesgo'], '#E04B55'], 'atencion' => ['Atención', $resumen['niveles']['atencion'], '#F2B233'], 'al_dia' => ['Al día', $resumen['niveles']['al_dia'], null]] as $clave => [$texto, $cuenta, $punto])
                        <button type="button" wire:click="verNivel('{{ $clave }}')" aria-pressed="{{ $filtro === $clave ? 'true' : 'false' }}"
                            class="inline-flex h-9 items-center gap-2 rounded-full border-[1.5px] px-3.5 text-[13px] font-bold transition-colors motion-reduce:transition-none {{ $enfoque }} {{ $filtro === $clave ? 'border-[var(--p900)] bg-[var(--p900)] text-white' : 'border-[#E0D8C8] bg-white text-[#3F3B34] hover:bg-[var(--mint)]' }}">
                            @if ($punto)<span class="h-2 w-2 rounded-full" style="background-color: {{ $punto }}" aria-hidden="true"></span>@endif
                            {{ $texto }} <span class="text-[11.5px] {{ $filtro === $clave ? 'text-white/80' : 'text-[#6B6559]' }}">{{ $cuenta }}</span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <div id="panel-asistencia" role="tabpanel" aria-labelledby="pestana-{{ $pestana }}" wire:loading.class="opacity-60" wire:target="irASemana,semanaAnterior,semanaSiguiente,irAEstaSemana,verPestana,verNivel,buscar,gotoPage,nextPage,previousPage" class="transition-opacity motion-reduce:transition-none">
            @if ($pestana === 'semana')
                @unless ($sinFechas)
                    @include('livewire.docente.asistencia._selector-semana')
                @endunless

                @if ($virtual)
                    @include('livewire.docente.asistencia._lecciones-completadas', ['porSemana' => true])
                @else
                    @include('livewire.docente.asistencia._semana')
                @endif
            @else
                @if ($virtual)
                    @include('livewire.docente.asistencia._lecciones-completadas', ['porSemana' => false])
                @else
                    @include('livewire.docente.asistencia._resumen-estudiantes')
                @endif
            @endif
        </div>
    </section>
</div>
