@use('App\Services\Reportes\AsistenciaProfesor')
@use('App\Services\Reportes\DetalleCursoProfesor')
{{-- Cuatro indicadores del curso presencial o remoto (mismo resumen que la vista por semana). --}}
@php
    $totalClases = $resumen['clases']->count();
    $sinLista = $resumen['sin_lista'];
    $primeraSinLista = $sinLista->first();
    $proxima = $resumen['proxima'];
    $promedio = $resumen['promedio'];
@endphp

<dl class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="{{ $tarjeta }} px-6 py-5">
        <dt class="{{ $etiqueta }}">Asistencia del curso</dt>
        <dd class="mt-2 flex items-baseline gap-2">
            <span class="{{ $cifra }}">{{ AsistenciaProfesor::porcentajeTexto($promedio) }}</span>
            @if ($promedio !== null)<span class="text-[14px] font-semibold text-[#5b706c]">promedio</span>@endif
        </dd>
        <dd class="mt-3 h-1.5 overflow-hidden rounded-[3px] bg-[#e1ebe8]" role="progressbar" aria-label="Asistencia promedio del curso" aria-valuenow="{{ (int) round($promedio ?? 0) }}" aria-valuemin="0" aria-valuemax="100">
            <span class="block h-full rounded-[3px] bg-[var(--p700)]" style="width: {{ min(100, $promedio ?? 0) }}%"></span>
        </dd>
        <dd class="mt-2 text-[13px] text-[#4b625e]">
            {{ $resumen['pasadas'] === 0 ? 'Aún no hay clases realizadas' : ($resumen['realizadas'] === 1 ? '1 clase con lista pasada' : $resumen['realizadas'].' clases con lista pasada') }}
        </dd>
    </div>

    <div class="{{ $tarjeta }} px-6 py-5">
        <dt class="{{ $etiqueta }}">Clases realizadas</dt>
        <dd class="mt-2 flex items-baseline gap-2">
            <span class="{{ $cifra }}">{{ $resumen['realizadas'] }}</span>
            <span class="text-[14px] font-semibold text-[#5b706c]">de {{ $totalClases }}</span>
        </dd>
        <dd class="mt-3 h-1.5 overflow-hidden rounded-[3px] bg-[#e1ebe8]" role="progressbar" aria-label="Clases realizadas" aria-valuenow="{{ $resumen['realizadas'] }}" aria-valuemin="0" aria-valuemax="{{ max(1, $totalClases) }}">
            <span class="block h-full rounded-[3px] bg-[var(--p700)]" style="width: {{ $totalClases > 0 ? round($resumen['realizadas'] * 100 / $totalClases) : 0 }}%"></span>
        </dd>
        <dd class="mt-2 truncate text-[13px] text-[#4b625e]">
            @if ($proxima)
                Próxima: {{ DetalleCursoProfesor::fechaCorta($proxima['sesion']->fecha) }} · {{ $proxima['sesion']->fecha->format('H:i') }}
            @else
                {{ $totalClases === 0 ? 'El curso aún no tiene clases' : 'No hay clases próximas' }}
            @endif
        </dd>
    </div>

    <div @class([$tarjeta, 'px-6 py-5', 'ring-[1.5px] ring-[#EBC65A] !bg-[var(--warn100)]' => $sinLista->isNotEmpty()])>
        <dt class="{{ $etiqueta }} {{ $sinLista->isNotEmpty() ? '!text-[var(--warn800)]' : '' }}">Sin pasar lista</dt>
        <dd class="mt-2 flex items-baseline gap-2">
            <span class="{{ $cifra }} {{ $sinLista->isNotEmpty() ? '!text-[var(--warn800)]' : '' }}">{{ $sinLista->count() }}</span>
            <span class="text-[14px] font-semibold {{ $sinLista->isNotEmpty() ? 'text-[var(--warn800)]' : 'text-[#5b706c]' }}">{{ $sinLista->count() === 1 ? 'clase' : 'clases' }}</span>
        </dd>
        @if ($primeraSinLista)
            <dd class="mt-2 truncate text-[13px] text-[var(--warn800)]" title="{{ $primeraSinLista['sesion']->titulo }}">
                {{ $primeraSinLista['sesion']->titulo }} · {{ DetalleCursoProfesor::fechaCorta($primeraSinLista['sesion']->fecha) }} · {{ $primeraSinLista['sesion']->fecha->format('H:i') }}
            </dd>
            @if ($editable)
                <dd class="mt-1">
                    <button type="button" wire:click="$parent.abrirPaseLista({{ $primeraSinLista['id'] }})" class="{{ $enlace }} !text-[var(--warn800)]">
                        Pasar lista ahora <x-icono nombre="flecha-derecha" class="h-4 w-4" />
                    </button>
                </dd>
            @endif
        @else
            <dd class="mt-2 text-[13px] text-[#4b625e]">Todas las clases pasadas tienen lista.</dd>
        @endif
    </div>

    <div class="{{ $tarjeta }} px-6 py-5">
        <dt class="{{ $etiqueta }}">Estudiantes en riesgo</dt>
        <dd class="mt-2 flex items-center gap-2">
            <span class="{{ $cifra }}">{{ $resumen['niveles']['riesgo'] }}</span>
            <span class="rounded-full bg-[var(--err100)] px-2 py-0.5 text-[12px] font-bold text-[var(--err700)]">&lt; {{ AsistenciaProfesor::UMBRAL_RIESGO }} %</span>
        </dd>
        <dd class="mt-2 text-[13px] text-[#4b625e]">
            @php $atencion = $resumen['niveles']['atencion']; @endphp
            {{ $atencion === 0 ? 'Nadie más en atención' : ($atencion === 1 ? '1 más con asistencia en atención' : $atencion.' más con asistencia en atención') }}
        </dd>
        @if ($resumen['activos'] > 0)
            <dd class="mt-1">
                <button type="button" wire:click="verNivel('riesgo')" class="{{ $enlace }}">Ver resumen <x-icono nombre="flecha-derecha" class="h-4 w-4" /></button>
            </dd>
        @endif
    </div>
</dl>
