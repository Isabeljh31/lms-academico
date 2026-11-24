{{--
    Reparto de las prácticas de campo entre las evaluaciones (Profesor). El
    Supervisor fijó el % total; aquí el profesor elige en qué evaluación cuenta
    cada tramo de prácticas y cuánto vale dentro de ella (partes iguales por práctica).
--}}
@php
    $formato = fn (float $valor) => rtrim(rtrim(number_format($valor, 2, ',', ''), '0'), ',');
    $cuadra = $pesoTotal > 0 && abs($repartido - $pesoTotal) < 0.005;
    $control = 'block w-full min-h-11 rounded-lg border border-slate-300 bg-white px-3 text-sm text-texto shadow-sm focus:border-azul-oscuro focus:outline-none focus:ring-2 focus:ring-cielo disabled:bg-slate-100 dark:border-azul-borde-oscuro dark:bg-azul-pagina-oscura dark:text-slate-100';
@endphp
<x-gestion.tarjeta>
    <section aria-labelledby="titulo-practicas-evaluaciones" class="space-y-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 flex-1 basis-72">
                <h2 id="titulo-practicas-evaluaciones" class="flex items-center gap-2 text-base font-semibold text-texto dark:text-slate-100">
                    <x-icono nombre="maletin" class="h-5 w-5 text-azul-oscuro dark:text-azul-fondo" /> Prácticas de campo en las evaluaciones
                </h2>
                @if ($pesoTotal > 0)
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        El Supervisor definió que las {{ $totalPracticas }} prácticas de campo valen <strong>{{ $formato($pesoTotal) }} %</strong> dentro de las evaluaciones.
                        Tú decides si ese % va a una sola evaluación o lo repartes entre varias. Cada práctica vale partes iguales dentro de su tramo.
                    </p>
                @else
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">El Supervisor aún no definió cuánto valen las prácticas de campo. Cuando lo haga, aquí podrás repartirlas entre las evaluaciones.</p>
                @endif
            </div>
            @if ($pesoTotal > 0 && $editable)
                <x-gestion.boton variante="secundario" tamano="sm" icono="refrescar" wire:click="sugerirReparto">Repartir por igual</x-gestion.boton>
            @endif
        </div>

        @if ($pesoTotal > 0)
            <form wire:submit="guardar" class="space-y-3">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[34rem] text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            <tr>
                                <th scope="col" class="py-2 pr-3 font-semibold">Evaluación</th>
                                <th scope="col" class="w-24 px-2 py-2 font-semibold">Desde práctica</th>
                                <th scope="col" class="w-24 px-2 py-2 font-semibold">Hasta práctica</th>
                                <th scope="col" class="w-28 px-2 py-2 font-semibold">% en la evaluación</th>
                                <th scope="col" class="py-2 pl-2"><span class="sr-only">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-celeste dark:divide-azul-borde-oscuro">
                            @foreach ($rubros as $rubro)
                                @php
                                    $tramo = $tramos[$rubro->id] ?? ['desde' => '', 'hasta' => '', 'peso' => ''];
                                    $cantidad = is_numeric($tramo['desde']) && is_numeric($tramo['hasta']) && (int) $tramo['hasta'] >= (int) $tramo['desde'] ? (int) $tramo['hasta'] - (int) $tramo['desde'] + 1 : 0;
                                    $peso = is_numeric(str_replace(',', '.', $tramo['peso'])) ? (float) str_replace(',', '.', $tramo['peso']) : 0;
                                @endphp
                                <tr wire:key="tramo-{{ $rubro->id }}" class="align-top">
                                    <th scope="row" class="py-2.5 pr-3 font-normal">
                                        <span class="block font-semibold text-texto dark:text-slate-100">{{ $rubro->nombre }}</span>
                                        <span class="block text-xs text-slate-500 dark:text-slate-400">
                                            {{ (float) $rubro->peso }} % de la nota final{{ $rubro->semana ? ' · semana '.$rubro->semana : '' }}
                                            @if ($cantidad > 0 && $peso > 0)
                                                · {{ $cantidad }} {{ $cantidad === 1 ? 'práctica' : 'prácticas' }} de {{ $formato($peso / $cantidad) }} % c/u
                                            @endif
                                        </span>
                                        @error('tramos.'.$rubro->id)
                                            <span class="mt-1 flex items-start gap-1 text-xs font-medium text-red-600"><x-icono nombre="alerta" class="mt-0.5 h-3.5 w-3.5 shrink-0" /> {{ $message }}</span>
                                        @enderror
                                    </th>
                                    <td class="px-2 py-2.5">
                                        <label for="tramo-{{ $rubro->id }}-desde" class="sr-only">Desde práctica ({{ $rubro->nombre }})</label>
                                        <input id="tramo-{{ $rubro->id }}-desde" type="number" min="1" max="{{ $totalPracticas }}" wire:model.live.debounce.400ms="tramos.{{ $rubro->id }}.desde" class="{{ $control }}" @disabled(! $editable)>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <label for="tramo-{{ $rubro->id }}-hasta" class="sr-only">Hasta práctica ({{ $rubro->nombre }})</label>
                                        <input id="tramo-{{ $rubro->id }}-hasta" type="number" min="1" max="{{ $totalPracticas }}" wire:model.live.debounce.400ms="tramos.{{ $rubro->id }}.hasta" class="{{ $control }}" @disabled(! $editable)>
                                    </td>
                                    <td class="px-2 py-2.5">
                                        <label for="tramo-{{ $rubro->id }}-peso" class="sr-only">% en {{ $rubro->nombre }}</label>
                                        <input id="tramo-{{ $rubro->id }}-peso" type="text" inputmode="decimal" wire:model.live.debounce.400ms="tramos.{{ $rubro->id }}.peso" placeholder="0" class="{{ $control }}" @disabled(! $editable)>
                                    </td>
                                    <td class="py-2.5 pl-2 text-right">
                                        @if ($editable)
                                            <button type="button" wire:click="todasEn({{ $rubro->id }})" class="min-h-11 whitespace-nowrap rounded-lg px-2 text-xs font-semibold text-azul-oscuro hover:bg-celeste focus:outline-none focus-visible:ring-2 focus-visible:ring-cielo dark:text-azul-fondo dark:hover:bg-azul-borde-oscuro">
                                                Todas aquí
                                            </button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-celeste pt-3 dark:border-azul-borde-oscuro">
                    <p class="text-sm font-medium {{ $cuadra ? 'text-exito-oscuro' : 'text-amber-700 dark:text-amber-300' }}" aria-live="polite">
                        Repartido {{ $formato($repartido) }} de {{ $formato($pesoTotal) }} %
                        @unless ($cuadra) · {{ $repartido < $pesoTotal ? 'faltan '.$formato($pesoTotal - $repartido) : 'te pasas por '.$formato($repartido - $pesoTotal) }} % @endunless
                    </p>
                    @if ($editable)
                        <x-gestion.boton type="submit" icono="check">Guardar reparto</x-gestion.boton>
                    @endif
                </div>
                @error('tramos')
                    <p role="alert" class="flex items-start gap-1 text-sm font-medium text-red-600"><x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $message }}</p>
                @enderror
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    Las demás actividades de cada evaluación (tareas, quizzes, exámenes) suman el % restante. Si una evaluación ya tenía actividades, sus pesos se ajustan manteniendo sus proporciones.
                </p>
            </form>
        @endif
    </section>
</x-gestion.tarjeta>
