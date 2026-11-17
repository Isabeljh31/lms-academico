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

<div class="border-t border-[#e1ece9] bg-[#f3f9f7] px-7 py-4">
    <h2 class="flex flex-wrap items-baseline gap-x-3">
        <span class="text-[20px] font-extrabold tracking-[-0.01em] text-[var(--p900)]">{{ $porSemana && ! $sinFechas ? 'Semana '.$semana : 'Todo el curso' }}</span>
        @if ($porSemana && ! $sinFechas)
            <span class="text-[14px] text-[#4b625e]">{{ $rangoTexto($actual['rango']) }}</span>
        @endif
        <span class="text-[14px] text-[#3d5753]">· Lecciones completadas · {{ $totalLecciones === 1 ? '1 lección publicada' : $totalLecciones.' lecciones publicadas' }}</span>
    </h2>
</div>

<div class="px-7 pb-3 pt-4">
    <label class="flex h-11 w-full max-w-[420px] items-center gap-2.5 rounded-xl bg-[#eef6f3] px-3.5 text-[#5b706c] focus-within:ring-[3px] focus-within:ring-[rgba(19,110,97,0.16)]">
        <x-icono nombre="lupa" class="h-4 w-4 shrink-0" />
        <span class="sr-only">Buscar estudiante</span>
        <input type="search" wire:model.live.debounce.300ms="buscar" placeholder="Buscar estudiante" autocomplete="off"
            class="w-full min-w-0 border-0 bg-transparent p-0 text-[14px] text-[var(--ink)] placeholder:text-[#5b706c] focus:outline-none focus:ring-0">
    </label>
</div>

@if ($pagina->isEmpty())
    <div class="flex flex-col items-center gap-2 border-t border-[#e8f0ee] px-6 py-12 text-center">
        <x-icono nombre="usuarios" class="h-7 w-7 text-[var(--p700)]" />
        <p class="font-bold text-[var(--p900)]">{{ trim($buscar) === '' ? 'No hay estudiantes matriculados' : 'Ningún estudiante coincide con la búsqueda' }}</p>
    </div>
@else
    @if ($totalLecciones === 0)
        <p class="border-t border-[#e8f0ee] px-7 py-3 text-[13px] text-[#4b625e]">{{ $porSemana ? 'Esta semana no tiene lecciones publicadas.' : 'El curso aún no tiene lecciones publicadas.' }}</p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full min-w-[640px] text-left">
            <caption class="sr-only">Lecciones completadas por estudiante</caption>
            <thead class="border-t border-[#e8f0ee] bg-[#f6faf9] text-[11.5px] font-bold uppercase tracking-[0.12em] text-[#5b706c]">
                <tr>
                    <th scope="col" class="px-7 py-3">Estudiante</th>
                    <th scope="col" class="px-3 py-3">Lecciones completadas</th>
                    <th scope="col" class="px-3 py-3 pr-7">Última completada</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#eef4f2] border-t border-[#e8f0ee]">
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
                                <span class="h-2 w-32 overflow-hidden rounded-full bg-[#e1ebe8]" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-[var(--p700)]" style="width: {{ $totalLecciones > 0 ? min(100, round($hechas * 100 / $totalLecciones)) : 0 }}%"></span>
                                </span>
                            </span>
                        </td>
                        <td class="px-3 py-3 pr-7 text-[13.5px] text-[#4b625e]">
                            {{ $fila->ultima ? \App\Services\Reportes\DetalleCursoProfesor::fechaCorta(\Illuminate\Support\Carbon::parse($fila->ultima)).' · '.\Illuminate\Support\Carbon::parse($fila->ultima)->format('H:i') : '—' }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @include('livewire.docente.asistencia._paginacion', ['pagina' => $pagina])
@endif
