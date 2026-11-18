{{--
    Cursos virtuales: lecciones completadas por estudiante (solo lectura, desde
    lecciones_completadas). Por semana: lecciones visibles del módulo de esa
    semana; en el resumen: las de todo el curso. No es asistencia.
--}}
@use('App\Services\Reportes\PanelProfesor')
@php
    $totalLecciones = $lecciones['total'];
    $pagina = $lecciones['estudiantes'];
@endphp

<div class="border-t border-[#ECE6DA] bg-[#F4F0E8] px-7 py-4">
    <h2 class="flex flex-wrap items-baseline gap-x-3">
        <span class="text-[20px] font-extrabold tracking-[-0.01em] text-[var(--p900)]">{{ $porSemana && ! $sinFechas ? 'Semana '.$semana : 'Todo el curso' }}</span>
        @if ($porSemana && ! $sinFechas)
            <span class="text-[14px] text-[#57524A]">{{ $rangoTexto($actual['rango']) }}</span>
        @endif
        <span class="text-[14px] text-[#3F3B34]">· Lecciones completadas · {{ $totalLecciones === 1 ? '1 lección publicada' : $totalLecciones.' lecciones publicadas' }}</span>
    </h2>
</div>

<div class="px-7 pb-3 pt-4">
    <label class="flex h-11 w-full max-w-[420px] items-center gap-2.5 rounded-xl bg-[#F4F0E8] px-3.5 text-[#6B6559] focus-within:ring-[3px] focus-within:ring-[rgba(19,110,97,0.16)]">
        <x-icono nombre="lupa" class="h-4 w-4 shrink-0" />
        <span class="sr-only">Buscar estudiante</span>
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar estudiante" autocomplete="off"
            class="w-full min-w-0 border-0 bg-transparent p-0 text-[14px] text-[var(--ink)] placeholder:text-[#6B6559] focus:outline-none focus:ring-0">
    </label>
</div>

@if ($pagina->isEmpty())
    <div class="flex flex-col items-center gap-2 border-t border-[#ECE6DA] px-6 py-12 text-center">
        <x-icono nombre="usuarios" class="h-7 w-7 text-[var(--p700)]" />
        <p class="font-bold text-[var(--p900)]">{{ trim($buscar) === '' ? 'No hay estudiantes matriculados' : 'Ningún estudiante coincide con la búsqueda' }}</p>
    </div>
@else
    @if ($totalLecciones === 0)
        <p class="border-t border-[#ECE6DA] px-7 py-3 text-[13px] text-[#57524A]">{{ $porSemana ? 'Esta semana no tiene lecciones publicadas.' : 'El curso aún no tiene lecciones publicadas.' }}</p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left">
            <caption class="sr-only">Lecciones completadas por estudiante</caption>
            <thead class="border-t border-[#ECE6DA] bg-[#F4F0E8] text-[11.5px] font-bold uppercase tracking-[0.12em] text-[#6B6559]">
                <tr>
                    <th scope="col" class="px-7 py-3">Estudiante</th>
                    <th scope="col" class="px-3 py-3">Lecciones completadas</th>
                    <th scope="col" class="px-3 py-3 pr-7">Última completada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#F4F0E8] border-t border-[#ECE6DA]">
                @foreach ($pagina as $fila)
                    @php $hechas = (int) $fila->completadas; @endphp
                    <tr wire:key="lecciones-{{ $fila->id }}">
                        <td class="px-7 py-3">
                            <span class="flex items-center gap-3">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[var(--mint)] text-[11.5px] font-bold text-[var(--p700)]" aria-hidden="true">{{ PanelProfesor::iniciales($fila->name) }}</span>
                                <span class="max-w-[260px] truncate text-[14.5px] font-semibold text-[var(--ink)]">{{ $fila->name }}</span>
                            </span>
                        </td>
                        <td class="px-3 py-3">
                            <span class="flex items-center gap-3">
                                <span class="w-16 text-[14.5px] font-bold text-[var(--p900)]">{{ $hechas }} de {{ $totalLecciones }}</span>
                                <span class="h-2 w-32 overflow-hidden rounded-full bg-[#ECE6DA]" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-[var(--p700)]" style="width: {{ $totalLecciones > 0 ? min(100, round($hechas * 100 / $totalLecciones)) : 0 }}%"></span>
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-3 pr-7 text-[13.5px] text-[#57524A]">
                            {{ $fila->ultima ? \App\Services\Reportes\DetalleCursoProfesor::fechaCorta(\Illuminate\Support\Carbon::parse($fila->ultima)).' · '.\Illuminate\Support\Carbon::parse($fila->ultima)->format('H:i') : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('livewire.docente.asistencia._paginacion', ['pagina' => $pagina])
@endif
