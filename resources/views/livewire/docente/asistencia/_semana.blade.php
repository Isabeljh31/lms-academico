{{--
    Clases de la semana elegida (presencial o remoto), agrupadas por día y
    ordenadas por hora. Cada clase: título (texto libre), hora, aula (solo si la
    descripción tiene el formato «Aula: X»), barra apilada P/T/J/A y su estado.
    Sin un curso con fecha de inicio, se listan todas las clases sin semanas.
--}}
@use('App\Services\Reportes\AsistenciaProfesor')
@php
    $clasesVista = $sinFechas ? $resumen['clases'] : ($actual['clases'] ?? collect());
    $porDia = $clasesVista->groupBy(fn (array $clase) => $clase['sesion']->fecha->toDateString());
    $estadosClase = [
        'realizada' => ['Realizada', 'bg-[var(--ok100)] text-[var(--ok900)]', 'check'],
        'sin_lista' => ['Sin lista', 'bg-[var(--warn200)] text-[var(--warn800)]', null],
        'proxima' => ['Próxima', 'bg-[var(--inf100)] text-[var(--inf700)]', null],
        'programada' => ['Programada', 'bg-[#eef3f1] text-[#4b625e]', null],
    ];
    $botonAccion = "inline-flex h-10 min-w-[128px] items-center justify-center gap-2 whitespace-nowrap rounded-[11px] px-4 text-[13.5px] font-bold transition-colors motion-reduce:transition-none {$enfoque}";
    $contorno = 'border-[1.5px] border-[#c9ddd8] bg-white text-[var(--p700)] hover:bg-[var(--mint)]';
    $oscuro = 'bg-[var(--p900)] text-white shadow-[0_6px_16px_rgba(9,59,59,0.22)] hover:bg-[var(--p800)]';
@endphp

<div class="border-t border-[#e1ece9] bg-[#f3f9f7]">
    <div class="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-2 px-7 py-4">
        <h2 class="flex items-baseline gap-3">
            @if ($sinFechas)
                <span class="text-[20px] font-extrabold text-[var(--p900)]">Todas las clases</span>
                <span class="text-[13.5px] text-[#4b625e]">El curso no tiene fecha de inicio, por eso las clases no se agrupan por semana.</span>
            @else
                <span class="text-[22px] font-extrabold tracking-[-0.01em] text-[var(--p900)]">Semana {{ $semana }}</span>
                <span class="text-[14px] text-[#4b625e]">{{ $rangoTexto($actual['rango']) }}</span>
            @endif
        </h2>
        @unless ($sinFechas)
            <p class="flex flex-wrap gap-x-6 gap-y-1 text-[14px] text-[#3d5753]">
                <span><strong class="text-[var(--p900)]">{{ $actual['clases']->count() }}</strong> {{ $actual['clases']->count() === 1 ? 'clase' : 'clases' }}</span>
                <span><strong class="text-[var(--p900)]">{{ $actual['con_lista'] }}</strong> con lista</span>
                <span><strong class="{{ $actual['sin_lista'] > 0 ? 'text-[var(--warn800)]' : 'text-[var(--p900)]' }}">{{ $actual['sin_lista'] }}</strong> sin lista</span>
                <span>Asistencia de la semana <strong class="text-[var(--p900)]">{{ AsistenciaProfesor::porcentajeTexto($actual['porcentaje']) }}</strong></span>
            </p>
        @endunless
    </div>
</div>

@if ($clasesVista->isEmpty())
    <div class="flex flex-col items-center gap-2 border-t border-[#e8f0ee] px-6 py-12 text-center">
        <x-icono nombre="calendario" class="h-7 w-7 text-[var(--p700)]" />
        <p class="font-bold text-[var(--p900)]">{{ $resumen['clases']->isEmpty() ? 'Este curso aún no tiene clases' : 'No hay clases en esta semana' }}</p>
        <p class="max-w-md text-[13px] text-[#4b625e]">
            {{ $resumen['clases']->isEmpty() ? 'Las clases se generan desde el horario del curso al crearlo. También puedes agregar una con «Nueva clase».' : 'Elige otra semana en el selector o en la cinta de semanas.' }}
        </p>
    </div>
@else
    <div class="overflow-x-auto">
        <table class="w-full min-w-[860px] text-left">
            <caption class="sr-only">Clases {{ $sinFechas ? 'del curso' : 'de la semana '.$semana }} con su asistencia</caption>
            <thead class="border-t border-[#e8f0ee] bg-[#f6faf9] text-[11.5px] font-bold uppercase tracking-[0.12em] text-[#5b706c]">
                <tr>
                    <th scope="col" class="px-7 py-3">Clase</th>
                    <th scope="col" class="px-3 py-3">Hora y aula</th>
                    <th scope="col" class="px-3 py-3">Asistencia</th>
                    <th scope="col" class="px-3 py-3">Estado</th>
                    <th scope="col" class="px-3 py-3"><span class="sr-only">Acciones</span></th>
                </tr>
            </thead>
            @foreach ($porDia as $dia => $clasesDia)
                @php $fechaDia = $clasesDia->first()['sesion']->fecha; @endphp
                <tbody class="border-t border-[#e8f0ee]" wire:key="dia-{{ $dia }}">
                    <tr>
                        <th scope="colgroup" colspan="5" class="px-7 pb-1 pt-4 text-[12.5px] font-bold uppercase tracking-[0.1em] text-[#4b625e]">
                            {{ str_replace('.', '', \Illuminate\Support\Str::upper($fechaDia->copy()->locale('es')->isoFormat('dddd D MMM'))) }} · {{ $clasesDia->count() === 1 ? '1 clase' : $clasesDia->count().' clases' }}
                        </th>
                    </tr>
                    @foreach ($clasesDia->sortBy(fn ($clase) => $clase['sesion']->fecha) as $clase)
                        @php
                            $sesion = $clase['sesion'];
                            [$textoEstado, $claseEstado, $iconoEstado] = $estadosClase[$clase['estado']];
                            $base = max(1, $resumen['activos']);
                            $segmentos = ['presente' => $clase['presentes'], 'tardanza' => $clase['tardanzas'], 'justificado' => $clase['justificados'], 'ausente' => $clase['ausentes']];
                            $detalleBarra = "{$clase['presentes']} presentes, {$clase['tardanzas']} tardanzas, {$clase['justificados']} justificados, {$clase['ausentes']} ausentes";
                        @endphp
                        <tr wire:key="clase-{{ $sesion->id }}" @class(['bg-[var(--warn100)]' => $clase['estado'] === 'sin_lista'])>
                            <td class="px-7 py-3.5">
                                <p class="max-w-[260px] truncate text-[14.5px] font-bold text-[var(--p900)]" title="{{ $sesion->titulo }}">{{ $sesion->titulo }}</p>
                                @if ($clase['solicitud']?->esperaSustento())
                                    <span class="mt-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[var(--warn200)] px-2.5 py-0.5 text-[11.5px] font-bold text-[var(--warn800)]">
                                        <x-icono nombre="documento" class="h-3 w-3" /> Sustento solicitado
                                    </span>
                                @elseif ($clase['cambio_pendiente'])
                                    <span class="mt-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[var(--warn200)] px-2.5 py-0.5 text-[11.5px] font-bold text-[var(--warn800)]">
                                        <x-icono nombre="reloj" class="h-3 w-3" /> Reprogramación en revisión
                                    </span>
                                @elseif ($clase['solicitud']?->estado === 'aprobada')
                                    <span class="mt-1 inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[#F5FCD2] px-2.5 py-0.5 text-[11.5px] font-bold text-[#3B6506]">
                                        <x-icono nombre="calendario" class="h-3 w-3" /> Reprogramada
                                    </span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <p class="text-[15px] font-bold text-[var(--p900)]">{{ $sesion->fecha->format('H:i') }} – {{ $clase['fin']->format('H:i') }}</p>
                                @if ($clase['aula'])
                                    <p class="text-[13px] text-[#4b625e]">Aula {{ $clase['aula'] }}</p>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                @if ($clase['estado'] === 'realizada')
                                    <div class="flex items-center gap-3">
                                        <span class="flex h-2.5 w-44 overflow-hidden rounded-full bg-[#e1ebe8]" role="img" aria-label="{{ $detalleBarra }}" title="{{ $detalleBarra }}">
                                            @foreach ($segmentos as $estado => $cantidad)
                                                @if ($cantidad > 0)
                                                    <span class="h-full" style="width: {{ round($cantidad * 100 / $base, 2) }}%; background-color: {{ $coloresEstado[$estado]['color'] }}"></span>
                                                @endif
                                            @endforeach
                                        </span>
                                        <span>
                                            <span class="block text-[15px] font-extrabold text-[var(--p900)]">{{ AsistenciaProfesor::porcentajeTexto($clase['porcentaje']) }}</span>
                                            <span class="block text-[12px] text-[#5b706c]" aria-hidden="true">{{ implode(' · ', $segmentos) }}</span>
                                        </span>
                                    </div>
                                @elseif ($clase['estado'] === 'sin_lista')
                                    <span class="text-[14px] font-medium text-[var(--warn800)]">Aún no se pasó lista</span>
                                @else
                                    <span class="text-[13.5px] text-[#6b7f7b]">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3.5">
                                <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-1 text-[12.5px] font-bold {{ $claseEstado }}">
                                    @if ($iconoEstado)
                                        <x-icono :nombre="$iconoEstado" class="h-3.5 w-3.5" />
                                    @elseif ($clase['estado'] === 'sin_lista')
                                        <span class="h-2 w-2 rounded-full bg-[#F2B233]" aria-hidden="true"></span>
                                    @endif
                                    {{ $textoEstado }}
                                </span>
                            </td>
                            <td class="px-3 py-3.5">
                                <div class="flex items-center justify-end gap-2 pr-4">
                                    @if (! $clase['permite_lista'])
                                        {{-- Clase de un día futuro: no hay lista que ver ni se puede pasar todavía. --}}
                                        @if ($clase['estado'] === 'proxima')
                                            <span class="min-w-[128px] text-center text-[12.5px] font-semibold text-[#5b706c]">Disponible el día de la clase</span>
                                        @else
                                            <span class="min-w-[128px]" aria-hidden="true"></span>
                                        @endif
                                    @elseif (! $editable || $clase['con_lista'])
                                        <button type="button" wire:click="$parent.abrirPaseLista({{ $sesion->id }})" class="{{ $botonAccion }} {{ $contorno }}">
                                            <x-icono nombre="documento" class="h-4 w-4" /> Ver lista
                                        </button>
                                    @else
                                        <button type="button" wire:click="$parent.abrirPaseLista({{ $sesion->id }})" class="{{ $botonAccion }} {{ $clase['estado'] === 'sin_lista' ? $oscuro : $contorno }}">
                                            <x-icono nombre="documento" class="h-4 w-4" /> Pasar lista
                                        </button>
                                    @endif

                                    {{-- Menú ⋮ (clase sin registros): «Reprogramar clase» (clase futura) o «Ver reprogramación»
                                         (en revisión o resuelta), «Reprogramar de nuevo» tras un rechazo o una reversión,
                                         y Eliminar, bloqueado mientras haya una reprogramación en revisión. --}}
                                    {{-- «Enlace de esta clase»: clase remota futura o en curso, aunque ya tenga lista (las demás opciones siguen igual). --}}
                                    @if ($editable && (! $clase['con_lista'] || $clase['admite_enlace']))
                                        <div class="relative" x-data="{ abierto: false }" x-on:click.outside="abierto = false" x-on:keydown.escape.stop="abierto = false; $refs.menu.focus()">
                                            <button type="button" x-ref="menu" x-on:click="abierto = ! abierto" aria-haspopup="menu" aria-expanded="false" x-bind:aria-expanded="abierto ? 'true' : 'false'"
                                                aria-label="Más acciones de {{ $sesion->titulo }}" class="grid h-9 w-9 place-items-center rounded-lg text-[#4b625e] hover:bg-[var(--mint)] {{ $enfoque }}">
                                                <x-icono nombre="puntos" class="h-5 w-5" />
                                            </button>
                                            <div x-show="abierto" x-cloak style="display: none" role="menu"
                                                class="absolute right-0 top-full z-30 mt-1 min-w-[230px] rounded-xl border border-[#e1ece9] bg-white p-1.5 shadow-[0_10px_30px_rgba(9,59,59,0.14)]">
                                                @if ($clase['admite_enlace'])
                                                    <button type="button" role="menuitem" x-on:click="abierto = false; Livewire.dispatch('abrir-enlace-clase', { sesion: {{ $sesion->id }} })"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[var(--p900)] hover:bg-[var(--mint)] {{ $enfoque }}">
                                                        <x-icono nombre="enlace" class="h-4 w-4" /> Enlace de esta clase
                                                    </button>
                                                @endif
                                                @unless ($clase['con_lista'])
                                                @if ($clase['solicitud'])
                                                    <button type="button" role="menuitem" x-on:click="abierto = false; Livewire.dispatch('abrir-solicitud-cambio', { sesion: {{ $sesion->id }} })"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[var(--p900)] hover:bg-[var(--mint)] {{ $enfoque }}">
                                                        <x-icono nombre="ojo" class="h-4 w-4" /> Ver reprogramación
                                                    </button>
                                                    @if (in_array($clase['solicitud']->estado, ['rechazada', 'revertida'], true) && ! $clase['pasada'])
                                                        <button type="button" role="menuitem" x-on:click="abierto = false; Livewire.dispatch('abrir-solicitud-cambio', { sesion: {{ $sesion->id }}, nueva: true })"
                                                            class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[var(--p900)] hover:bg-[var(--mint)] {{ $enfoque }}">
                                                            <x-icono nombre="calendario" class="h-4 w-4" /> Reprogramar de nuevo
                                                        </button>
                                                    @endif
                                                @elseif (! $clase['pasada'])
                                                    <button type="button" role="menuitem" x-on:click="abierto = false; Livewire.dispatch('abrir-solicitud-cambio', { sesion: {{ $sesion->id }} })"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[var(--p900)] hover:bg-[var(--mint)] {{ $enfoque }}">
                                                        <x-icono nombre="calendario" class="h-4 w-4" /> Reprogramar clase
                                                    </button>
                                                @endif
                                                @if ($clase['cambio_pendiente'])
                                                    <button type="button" role="menuitem" aria-disabled="true" disabled aria-describedby="bloqueo-eliminar-{{ $sesion->id }}"
                                                        class="flex w-full cursor-not-allowed flex-col items-start gap-0.5 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[#9aa9a6]">
                                                        <span class="inline-flex items-center gap-2"><x-icono nombre="papelera" class="h-4 w-4" /> Eliminar clase</span>
                                                        <span id="bloqueo-eliminar-{{ $sesion->id }}" class="text-[11.5px] font-medium text-[#5b706c]">Tiene una reprogramación pendiente: cancélala para poder eliminar la clase.</span>
                                                    </button>
                                                @else
                                                    <button type="button" role="menuitem"
                                                        x-on:click="abierto = false; if (confirm(@js('¿Eliminar la clase «'.$sesion->titulo.'»?'))) { $wire.$parent.eliminarSesion({{ $sesion->id }}).then(() => $wire.$refresh()) }"
                                                        class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-[13px] font-semibold text-[var(--err700)] hover:bg-[var(--err100)] {{ $enfoque }}">
                                                        <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar clase
                                                    </button>
                                                @endif
                                                @endunless
                                            </div>
                                        </div>
                                    @else
                                        <span class="w-9" aria-hidden="true"></span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            @endforeach
        </table>
    </div>
@endif

<div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#e8f0ee] bg-[#fafcfb] px-7 py-3.5 text-[12.5px] text-[#4b625e]">
    <p>Las clases salen del horario del curso o de «Nueva clase». Una clase sin registros se puede eliminar desde su menú ⋮.</p>
    <ul class="flex flex-wrap gap-x-4 gap-y-1" aria-label="Leyenda de asistencia">
        @foreach ($coloresEstado as $estilo)
            <li class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm" style="background-color: {{ $estilo['color'] }}" aria-hidden="true"></span>{{ $estilo['texto'] }}</li>
        @endforeach
    </ul>
</div>
