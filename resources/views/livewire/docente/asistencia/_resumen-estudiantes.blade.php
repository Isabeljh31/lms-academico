{{--
    Resumen por estudiante: búsqueda, filtro por nivel (botones de la cabecera),
    últimas sesiones como bloques de color, contadores P/T/J/A, porcentaje
    oficial y nivel. Paginado en la base de datos (12 por página), de menor a
    mayor asistencia.
--}}
@use('App\Services\Reportes\AsistenciaProfesor')
@use('App\Services\Reportes\DetalleCursoProfesor')
@use('App\Services\Reportes\PanelProfesor')
@php
    $niveles = [
        'riesgo' => ['En riesgo', 'bg-[var(--err100)] text-[var(--err700)]', '#E04B55'],
        'atencion' => ['Atención', 'bg-[var(--warn200)] text-[var(--warn800)]', '#F2B233'],
        'al_dia' => ['Al día', 'bg-[var(--ok100)] text-[var(--ok900)]', '#136E61'],
    ];
    $letras = ['presente' => 'P', 'tardanza' => 'T', 'justificado' => 'J', 'ausente' => 'A'];
@endphp

<div class="flex flex-wrap items-center gap-x-6 gap-y-3 px-7 pb-3 pt-5">
    <label class="flex h-11 w-full max-w-[420px] items-center gap-2.5 rounded-xl bg-[#eef6f3] px-3.5 text-[#5b706c] focus-within:ring-[3px] focus-within:ring-[rgba(19,110,97,0.16)]">
        <x-icono nombre="lupa" class="h-4 w-4 shrink-0" />
        <span class="sr-only">Buscar estudiante</span>
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar estudiante" autocomplete="off"
            class="w-full min-w-0 border-0 bg-transparent p-0 text-[14px] text-[var(--ink)] placeholder:text-[#5b706c] focus:outline-none focus:ring-0">
    </label>
    <p class="text-[13.5px] text-[#4b625e]">Ordenados: menor asistencia primero</p>
    <p class="ml-auto text-[13px] text-[#4b625e]">
        En riesgo = menos de {{ AsistenciaProfesor::UMBRAL_RIESGO }} % · atención hasta {{ AsistenciaProfesor::UMBRAL_AL_DIA - 1 }} % · base: {{ $resumen['pasadas'] === 1 ? '1 sesión realizada' : $resumen['pasadas'].' sesiones realizadas' }}
    </p>
</div>

<ul class="flex flex-wrap items-center gap-x-4 gap-y-1 px-7 pb-3 text-[13px] text-[#4b625e]" aria-label="Leyenda de las últimas sesiones">
    <li>Últimas {{ AsistenciaProfesor::ULTIMAS_SESIONES }} sesiones:</li>
    @foreach ($coloresEstado as $estilo)
        <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $estilo['color'] }}" aria-hidden="true"></span>{{ $estilo['texto'] }}</li>
    @endforeach
    <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm border border-dashed border-[#b8c9c4] bg-white" aria-hidden="true"></span>Sin lista (cuenta como ausente)</li>
</ul>

@if ($estudiantes->isEmpty())
    <div class="flex flex-col items-center gap-2 border-t border-[#e8f0ee] px-6 py-12 text-center">
        <x-icono nombre="usuarios" class="h-7 w-7 text-[var(--p700)]" />
        <p class="font-bold text-[var(--p900)]">
            {{ $resumen['activos'] === 0 ? 'No hay estudiantes matriculados' : 'Ningún estudiante coincide con la búsqueda o el filtro' }}
        </p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[980px] text-left">
            <caption class="sr-only">Resumen de asistencia por estudiante</caption>
            <thead class="border-t border-[#e8f0ee] bg-[#f6faf9] text-[11.5px] font-bold uppercase tracking-[0.12em] text-[#5b706c]">
                <tr>
                    <th scope="col" class="px-7 py-3">Estudiante</th>
                    <th scope="col" class="px-3 py-3">Últimas {{ AsistenciaProfesor::ULTIMAS_SESIONES }} sesiones</th>
                    <th scope="col" class="px-2 py-3 text-center"><abbr title="Presentes" class="no-underline">Pres.</abbr></th>
                    <th scope="col" class="px-2 py-3 text-center"><abbr title="Tardanzas" class="no-underline">Tard.</abbr></th>
                    <th scope="col" class="px-2 py-3 text-center"><abbr title="Justificadas (no cuentan en el total)" class="no-underline">Just.</abbr></th>
                    <th scope="col" class="px-2 py-3 text-center"><abbr title="Ausencias (incluye clases sin lista)" class="no-underline">Aus.</abbr></th>
                    <th scope="col" class="px-3 py-3">Asistencia</th>
                    <th scope="col" class="px-3 py-3 pr-7">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef4f2] border-t border-[#e8f0ee]">
                @foreach ($estudiantes as $fila)
                    @php [$textoNivel, $claseNivel, $colorNivel] = $niveles[$fila['nivel']] ?? ['Sin datos', 'bg-[#eef3f1] text-[#4b625e]', '#b8c9c4']; @endphp
                    <tr wire:key="resumen-{{ $fila['id'] }}">
                        <td class="px-7 py-3">
                            <span class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--mint)] text-[11.5px] font-bold text-[var(--p700)]" aria-hidden="true">{{ PanelProfesor::iniciales($fila['nombre']) }}</span>
                                <span class="max-w-[240px] truncate text-[14.5px] font-semibold text-[var(--ink)]" title="{{ $fila['nombre'] }}">{{ $fila['nombre'] }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            @if ($fila['ultimas'] === [])
                                <span class="text-[13px] text-[#6b7f7b]">Sin sesiones realizadas</span>
                            @else
                                <ol class="flex gap-[3px]" aria-label="Últimas sesiones de {{ $fila['nombre'] }}">
                                    @foreach ($fila['ultimas'] as $bloque)
                                        @php
                                            $sinLista = $bloque['estado'] === 'sin_lista';
                                            $textoBloque = DetalleCursoProfesor::fechaCorta($bloque['sesion']->fecha).': '.($sinLista ? 'sin lista (cuenta como ausente)' : $coloresEstado[$bloque['estado']]['texto']);
                                        @endphp
                                        <li title="{{ $textoBloque }}" class="h-6 w-[14px] rounded-[4px] {{ $sinLista ? 'border border-dashed border-[#b8c9c4] bg-white' : '' }}"
                                            @unless ($sinLista) style="background-color: {{ $coloresEstado[$bloque['estado']]['color'] ?? '#b8c9c4' }}" @endunless>
                                            <span class="sr-only">{{ $textoBloque }}</span>
                                        </li>
                                    @endforeach
                                </ol>
                            @endif
                        </td>
                        <td class="px-2 py-3 text-center text-[14.5px] font-semibold text-[var(--p900)]">{{ $fila['presentes'] }}</td>
                        <td class="px-2 py-3 text-center text-[14.5px] font-semibold text-[var(--p900)]">{{ $fila['tardanzas'] }}</td>
                        <td class="px-2 py-3 text-center text-[14.5px] font-semibold text-[#4b625e]">{{ $fila['justificados'] }}</td>
                        <td class="px-2 py-3 text-center text-[14.5px] font-semibold {{ $fila['ausentes'] > 0 && $fila['nivel'] !== 'al_dia' ? 'text-[var(--err700)]' : 'text-[var(--p900)]' }}">{{ $fila['ausentes'] }}</td>
                        <td class="px-3 py-3">
                            <span class="flex items-center gap-3">
                                <span class="w-14 text-[14.5px] font-bold text-[var(--p900)]">{{ AsistenciaProfesor::porcentajeTexto($fila['porcentaje']) }}</span>
                                <span class="h-2 w-28 overflow-hidden rounded-full bg-[#e1ebe8]" aria-hidden="true">
                                    <span class="block h-full rounded-full" style="width: {{ min(100, $fila['porcentaje'] ?? 0) }}%; background-color: {{ $colorNivel }}"></span>
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-3 pr-7">
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[12.5px] font-bold {{ $claseNivel }}">
                                @if ($fila['nivel'] === 'riesgo')<span class="h-2 w-2 rounded-full bg-[#E04B55]" aria-hidden="true"></span>@endif
                                {{ $textoNivel }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('livewire.docente.asistencia._paginacion', ['pagina' => $estudiantes])
@endif
