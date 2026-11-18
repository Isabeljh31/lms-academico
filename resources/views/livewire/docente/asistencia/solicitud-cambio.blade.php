{{--
    Modal «Reprogramar clase» / «Ver reprogramación» de Asistencia del
    Profesor (Docente\Asistencia\SolicitudCambio). Cambiar fecha y hora con
    horas disponibles y salones libres (nunca se muestra qué curso ocupa un
    salón) o dictarla en remoto. Si cumple las reglas se aplica sola; si no,
    pasa a revisión del Supervisor. Sin videoconferencia.
--}}
@use('App\Livewire\Docente\Asistencia\SolicitudCambio')
@use('App\Services\Asistencia\SolicitudesCambioClase')
@use('App\Services\Reportes\AsistenciaProfesor')
@use('App\Services\Reportes\DetalleCursoProfesor')
@php
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $botonContorno = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-full border-[1.5px] border-[#E0D8C8] bg-white px-4 text-[13px] font-bold text-[var(--p900)] transition-colors hover:bg-[var(--mint)] motion-reduce:transition-none {$enfoque}";
    $botonOscuro = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-full bg-[#1D1C19] px-5 text-[13px] font-bold text-white transition-colors hover:bg-[var(--p800)] disabled:opacity-60 motion-reduce:transition-none {$enfoque}";
    $botonPeligro = "inline-flex h-10 items-center justify-center gap-[7px] whitespace-nowrap rounded-full border-[1.5px] border-[#f3c9c2] bg-white px-4 text-[13px] font-bold text-[var(--err700)] transition-colors hover:bg-[var(--err100)] motion-reduce:transition-none {$enfoque}";
    $etiqueta = 'mb-1 block text-[13px] font-semibold text-[var(--ink)]';
    $control = 'w-full rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-3 py-2 text-[13.5px] text-[var(--ink)] focus:border-[var(--p700)] focus:outline-none focus:ring-[3px] focus:ring-[rgba(19,110,97,0.14)]';
    $segmento = 'cursor-pointer rounded-lg px-3 py-1.5 text-[13px] font-semibold text-[#334155] transition-colors hover:bg-[var(--mint)] has-[:checked]:bg-[#1D1C19] has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#1D1C19] has-[:focus-visible]:ring-offset-1 motion-reduce:transition-none';
    $chipHora = 'relative flex cursor-pointer flex-col items-center rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white px-2 py-1.5 text-[13px] font-bold text-[var(--p900)] transition-colors hover:bg-[var(--mint)] has-[:checked]:border-[#1D1C19] has-[:checked]:bg-[#1D1C19] has-[:checked]:text-white has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#1D1C19] has-[:focus-visible]:ring-offset-1 has-[:disabled]:cursor-not-allowed has-[:disabled]:border-dashed has-[:disabled]:bg-[#F4F0E8] has-[:disabled]:text-[#8F887B] has-[:disabled]:hover:bg-[#F4F0E8] motion-reduce:transition-none';
    $tarjetaSalon = 'flex cursor-pointer items-center justify-between gap-3 rounded-[12px] border-[1.5px] border-[#E0D8C8] bg-white px-3.5 py-2.5 text-[13px] transition-colors hover:bg-[var(--mint)] has-[:checked]:border-[#1D1C19] has-[:checked]:bg-[var(--mint)] has-[:focus-visible]:ring-2 has-[:focus-visible]:ring-[#1D1C19] has-[:focus-visible]:ring-offset-1 motion-reduce:transition-none';
    $error = 'mt-1 flex items-center gap-1 text-xs font-semibold text-[var(--err700)]';
    $nota = 'flex items-start gap-2 rounded-[10px] bg-[var(--mint)] px-3 py-2 text-xs text-[var(--p900)]';
    $notaAviso = 'flex items-start gap-2 rounded-[10px] bg-[var(--warn100)] px-3 py-2 text-xs font-semibold text-[var(--warn800)]';
    $dt = 'font-semibold text-[#6B6559]';
    $dd = 'text-[var(--ink)]';

    // Estado tal como lo ve el Profesor (la espera de sustento no es un estado aparte).
    [$textoEstado, $claseEstado] = match (true) {
        $solicitud?->esperaSustento() => ['Sustento solicitado', 'bg-[var(--warn200)] text-[var(--warn800)]'],
        $solicitud?->estado === 'pendiente' => ['Reprogramación en revisión', 'bg-[var(--warn200)] text-[var(--warn800)]'],
        $solicitud?->estado === 'aprobada' => ['Reprogramada', 'bg-[#F5FCD2] text-[#3B6506]'],
        $solicitud?->estado === 'rechazada' => ['Rechazada', 'bg-[var(--err100)] text-[var(--err700)]'],
        $solicitud?->estado === 'revertida' => ['Revertida', 'bg-[#F4F0E8] text-[#334155]'],
        default => ['', ''],
    };
    $resumenClase = $clase ? collect([
        $clase->numero_sesion ? 'Sesión '.$clase->numero_sesion : $clase->titulo,
        DetalleCursoProfesor::fechaCorta($clase->fecha),
        $clase->fecha->format('H:i').' – '.$clase->fecha->copy()->addMinutes((int) $clase->duracion_minutos)->format('H:i'),
        $clase->salon ? 'Salón '.$clase->salon->codigo : (($aula = AsistenciaProfesor::aula($clase)) ? 'Aula '.$aula : null),
        $curso->nombre,
    ])->filter()->join(' · ') : '';
    $hayHoraDisponible = collect($horas)->contains('disponible', true);
@endphp

<div>
    {{-- Confirmación flotante: visible sin hacer scroll (arriba a la derecha) y se cierra sola o con la X. --}}
    @if ($aviso)
        <div wire:key="aviso-{{ md5($aviso.$sesionId) }}" data-aviso-flotante role="status" aria-live="polite"
            x-data="{ visible: true }" x-init="setTimeout(() => visible = false, 6000)" x-show="visible" x-transition.opacity
            class="fixed right-4 top-4 z-[60] flex max-w-[380px] items-start gap-2.5 rounded-xl border border-[#E0D8C8] bg-white px-4 py-3 text-[13.5px] font-semibold text-[var(--p900)] shadow-[0_12px_28px_rgba(9,59,59,0.18)]">
            <x-icono nombre="check-circulo" class="h-5 w-5 shrink-0 text-[var(--ok900)]" />
            <p class="flex-1">{{ $aviso }}</p>
            <button type="button" x-on:click="visible = false" aria-label="Cerrar aviso" class="grid h-6 w-6 shrink-0 place-items-center rounded text-[#57524A] hover:bg-[var(--mint)] {{ $enfoque }}">
                <x-icono nombre="cerrar" class="h-4 w-4" />
            </button>
        </div>
    @endif

    @if ($abierto && $clase)
        <div class="fixed inset-0 z-50 flex items-end justify-center bg-[rgba(9,59,59,0.45)] p-0 sm:items-center sm:p-4"
            x-data x-on:keydown.escape.window="$wire.cerrar()">
            <div role="dialog" aria-modal="true" aria-labelledby="titulo-solicitud-cambio" x-trap.noscroll="true"
                class="max-h-[92vh] w-full max-w-[600px] overflow-y-auto rounded-t-2xl border border-[#E0D8C8] bg-white shadow-[0_20px_50px_rgba(9,59,59,0.18)] sm:rounded-2xl">
                <div class="flex items-start justify-between gap-4 border-b border-[#ECE6DA] px-5 py-4">
                    <div class="min-w-0">
                        <h2 id="titulo-solicitud-cambio" class="text-[15px] font-extrabold text-[var(--p900)]">{{ $solicitud ? 'Ver reprogramación' : 'Reprogramar clase' }}</h2>
                        <p class="mt-0.5 text-xs text-[#6B6559]">{{ $resumenClase }}</p>
                    </div>
                    <button type="button" wire:click="cerrar" aria-label="Cerrar" class="grid h-9 w-9 shrink-0 place-items-center rounded-lg text-[#57524A] hover:bg-[var(--mint)] {{ $enfoque }}">
                        <x-icono nombre="cerrar" class="h-5 w-5" />
                    </button>
                </div>

                @error('solicitud')
                    <p role="alert" class="mx-5 mt-4 flex items-start gap-2 rounded-xl bg-[var(--err100)] px-3 py-2 text-[13px] font-semibold text-[var(--err700)]"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> {{ $message }}</p>
                @enderror

                @if ($solicitud)
                    {{-- Ver reprogramación: lo pedido, la decisión del Supervisor y, si lo pidió, el sustento. --}}
                    <dl class="grid grid-cols-[150px_1fr] gap-x-4 gap-y-2.5 px-5 py-4 text-[13px]">
                        <dt class="{{ $dt }}">Estado</dt>
                        <dd><span class="rounded-full px-2.5 py-0.5 text-xs font-bold {{ $claseEstado }}">{{ $textoEstado }}</span></dd>
                        <dt class="{{ $dt }}">Qué pediste</dt>
                        <dd class="{{ $dd }}">{{ $tipos[$solicitud->tipo] ?? $solicitud->tipo }} · {{ $modalidades[$solicitud->modalidad_solicitada] ?? $solicitud->modalidad_solicitada }}</dd>
                        <dt class="{{ $dt }}">Motivo</dt>
                        <dd class="{{ $dd }}">{{ $motivos[$solicitud->motivo] ?? $solicitud->motivo }}@if ($solicitud->detalle) · {{ $solicitud->detalle }}@endif</dd>
                        <dt class="{{ $dt }}">Clase original</dt>
                        <dd class="{{ $dd }}">{{ DetalleCursoProfesor::fechaCorta($solicitud->fecha_original) }} · {{ $solicitud->fecha_original->format('H:i') }} – {{ $solicitud->fecha_original->copy()->addMinutes($solicitud->duracion_original)->format('H:i') }}</dd>
                        @if ($solicitud->fecha_propuesta && $solicitud->estado !== 'aprobada')
                            <dt class="{{ $dt }}">Propusiste</dt>
                            <dd class="{{ $dd }}">{{ DetalleCursoProfesor::fechaCorta($solicitud->fecha_propuesta) }} · {{ $solicitud->fecha_propuesta->format('H:i') }}@if ($solicitud->salonNuevo) · Salón {{ $solicitud->salonNuevo->codigo }}@endif</dd>
                        @endif
                        @if ($solicitud->estaPendiente() && $solicitud->razon_revision)
                            <dt class="{{ $dt }}">Por qué está en revisión</dt>
                            <dd class="{{ $dd }}">{{ $solicitud->razon_revision }}</dd>
                        @endif
                        @if (in_array($solicitud->estado, ['aprobada', 'revertida'], true) && $solicitud->fecha_nueva)
                            <dt class="{{ $dt }}">{{ $solicitud->estado === 'revertida' ? 'Se había movido a' : 'Nueva clase' }}</dt>
                            <dd class="font-bold text-[var(--p900)]">
                                {{ DetalleCursoProfesor::fechaCorta($solicitud->fecha_nueva) }} · {{ $solicitud->fecha_nueva->format('H:i') }} – {{ $solicitud->fecha_nueva->copy()->addMinutes((int) $solicitud->duracion_nueva)->format('H:i') }}
                                · {{ $modalidades[$solicitud->modalidad_nueva] ?? $solicitud->modalidad_nueva }}@if ($solicitud->modalidad_nueva === 'presencial') · {{ $solicitud->salonNuevo ? 'Salón '.$solicitud->salonNuevo->codigo : ($solicitud->aula_nueva ? 'Aula '.$solicitud->aula_nueva : 'Sin salón asignado') }}@endif
                            </dd>
                        @endif
                        @if ($solicitud->comentario_decision)
                            <dt class="{{ $dt }}">Comentario</dt>
                            <dd class="{{ $dd }}">{{ $solicitud->comentario_decision }}</dd>
                        @endif
                        @if ($solicitud->origen === 'automatica')
                            <dt class="{{ $dt }}">Decisión</dt>
                            <dd class="{{ $dd }}">Aplicada automáticamente · cumplió todas las reglas</dd>
                        @elseif ($solicitud->decisor)
                            <dt class="{{ $dt }}">Decidió</dt>
                            <dd class="{{ $dd }}">{{ $solicitud->decisor->name }} · {{ $solicitud->decidido_en?->format('d/m/Y H:i') }}</dd>
                        @endif
                        @if ($solicitud->estado === 'revertida')
                            <dt class="{{ $dt }}">Reversión</dt>
                            <dd class="{{ $dd }}">La clase volvió a su horario original.@if ($solicitud->comentario_reversion) {{ $solicitud->comentario_reversion }}@endif</dd>
                        @endif
                        @if ($solicitud->sustento_pedido)
                            <dt class="{{ $dt }}">El supervisor pidió</dt>
                            <dd class="{{ $dd }}">{{ $solicitud->sustento_pedido }}</dd>
                        @endif
                        @if ($solicitud->sustento_respondido_en)
                            <dt class="{{ $dt }}">Tu respuesta</dt>
                            <dd class="{{ $dd }}">
                                {{ $solicitud->sustento_respuesta }}
                                @if ($solicitud->sustento_archivo)
                                    <a href="{{ route('reprogramaciones.sustento', $solicitud) }}" target="_blank" rel="noopener" class="ml-1 inline-flex items-center gap-1 font-bold text-[var(--p700)] underline {{ $enfoque }}">
                                        <x-icono nombre="documento" class="h-3.5 w-3.5" /> Ver archivo
                                    </a>
                                @endif
                            </dd>
                        @endif
                    </dl>

                    @if ($solicitud->esperaSustento())
                        {{-- Respuesta única al sustento: texto y, opcional, un PDF o una imagen. --}}
                        <form wire:submit="responderSustento" class="mx-5 mb-4 flex flex-col gap-3 rounded-xl border border-[#ECE6DA] p-4">
                            <p class="text-[13px] font-bold text-[var(--p900)]">Responder al supervisor</p>
                            <div>
                                <label for="sustento-respuesta" class="{{ $etiqueta }}">Tu respuesta <span class="text-[var(--err700)]" aria-hidden="true">*</span></label>
                                <textarea id="sustento-respuesta" wire:model="respuestaSustento" rows="3" maxlength="1000" class="{{ $control }}"></textarea>
                                @error('respuesta') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="sustento-archivo" class="{{ $etiqueta }}">Archivo <span class="font-normal text-[#6B6559]">(opcional: PDF o imagen, hasta {{ (int) round(config('archivos.max_kb') / 1024) }} MB)</span></label>
                                <input id="sustento-archivo" type="file" wire:model="archivoSustento" accept=".{{ implode(',.', SolicitudesCambioClase::EXTENSIONES_SUSTENTO) }}"
                                    class="block w-full text-[13px] text-[var(--ink)] file:mr-3 file:rounded-full file:border-[1.5px] file:border-[#E0D8C8] file:bg-white file:px-4 file:py-1.5 file:text-[13px] file:font-bold file:text-[var(--p900)] hover:file:bg-[var(--mint)]">
                                <p wire:loading wire:target="archivoSustento" class="mt-1 text-xs text-[#6B6559]">Subiendo archivo…</p>
                                @error('archivoSustento') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                @error('archivo') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>
                            <p class="text-xs text-[#6B6559]">Solo puedes responder una vez. El archivo lo ven solo tú y el supervisor.</p>
                            <div class="flex justify-end">
                                <button type="submit" wire:loading.attr="disabled" wire:target="responderSustento,archivoSustento" class="{{ $botonOscuro }}">Enviar respuesta</button>
                            </div>
                        </form>
                    @endif

                    @if ($solicitud->estaPendiente())
                        <p class="mx-5 mb-4 rounded-[10px] bg-[var(--mint)] px-3 py-2 text-xs text-[var(--p900)]">Tu clase sigue en su horario hasta que el supervisor decida.</p>
                    @endif
                    <div class="flex flex-wrap justify-end gap-2 border-t border-[#ECE6DA] px-5 py-3">
                        @if ($solicitud->estaPendiente())
                            <button type="button" wire:click="cancelarSolicitud" wire:loading.attr="disabled" class="{{ $botonPeligro }}"
                                wire:confirm="¿Cancelar la reprogramación? La clase seguirá en su horario.">Cancelar reprogramación</button>
                        @endif
                        <button type="button" wire:click="cerrar" class="{{ $botonContorno }}">Cerrar</button>
                    </div>
                @else
                    {{-- Reprogramar: se aplica sola si cumple las reglas; si no, va a revisión del supervisor. --}}
                    <form wire:submit="enviar" class="flex flex-col gap-4 px-5 py-4">
                        <fieldset>
                            <legend class="{{ $etiqueta }}">¿Qué necesitas? <span class="text-[var(--err700)]" aria-hidden="true">*</span></legend>
                            <div class="inline-flex flex-wrap rounded-[10px] border-[1.5px] border-[#E0D8C8] bg-white p-0.5">
                                @foreach ($tipos as $valor => $texto)
                                    <label wire:key="tipo-{{ $valor }}" class="{{ $segmento }}">
                                        <input type="radio" name="solicitud_tipo" value="{{ $valor }}" wire:model.live="tipo" @checked($tipo === $valor) class="sr-only">
                                        {{ $texto }}
                                    </label>
                                @endforeach
                            </div>
                            @error('tipo') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </fieldset>

                        @error('horario')
                            <p role="alert" class="flex items-start gap-2 rounded-xl bg-[var(--err100)] px-3 py-2 text-[13px] font-semibold text-[var(--err700)]"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> {{ $message }}</p>
                        @enderror

                        @if ($tipo === 'reprogramar')
                            @if ($sinSalon)
                                <p class="{{ $notaAviso }}"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> {{ SolicitudCambio::AVISO_SIN_SALON }}</p>
                            @elseif (! $presencial)
                                <p class="{{ $nota }}"><x-icono nombre="info" class="h-4 w-4 shrink-0" /> Clase remota: la nueva fecha no usa salón.</p>
                            @endif

                            <div class="sm:max-w-[260px]">
                                <label for="solicitud-fecha" class="{{ $etiqueta }}">Nueva fecha <span class="text-[var(--err700)]" aria-hidden="true">*</span></label>
                                <input id="solicitud-fecha" type="date" wire:model.live="fechaPropuesta" min="{{ now()->format('Y-m-d') }}" class="{{ $control }}">
                                @error('fechaPropuesta') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                @error('fecha_propuesta') <p class="{{ $error }}">{{ $message }}</p> @enderror
                            </div>

                            @if ($fechaPropuesta !== '')
                                <fieldset>
                                    <legend class="{{ $etiqueta }}">Hora de inicio <span class="font-normal text-[#6B6559]">(dura {{ (int) $clase->duracion_minutos }} min, como la original)</span></legend>
                                    @if ($esDomingo)
                                        <p class="{{ $notaAviso }}"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> Los domingos no se reprograman clases. Elige otra fecha.</p>
                                    @else
                                        <div class="grid grid-cols-4 gap-1.5 sm:grid-cols-6" wire:loading.class="opacity-60" wire:target="fechaPropuesta">
                                            @foreach ($horas as $opcion)
                                                <label wire:key="hora-{{ $fechaPropuesta }}-{{ $opcion['hora'] }}" class="{{ $chipHora }}" data-hora="{{ $opcion['hora'] }}" data-disponible="{{ $opcion['disponible'] ? 'si' : 'no' }}">
                                                    <input type="radio" name="solicitud_hora" value="{{ $opcion['hora'] }}" wire:model.live="horaPropuesta" @checked($horaPropuesta === $opcion['hora']) @disabled(! $opcion['disponible']) class="sr-only">
                                                    {{ $opcion['hora'] }}
                                                    <span class="text-[10.5px] font-semibold">{{ $opcion['disponible'] ? 'Disponible' : 'No disponible' }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                        @if (! $hayHoraDisponible)
                                            <p class="mt-2 {{ $notaAviso }}"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> No hay horas disponibles ese día. Elige otra fecha.</p>
                                        @endif
                                    @endif
                                    @error('horaPropuesta') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                </fieldset>
                            @endif

                            @if ($presencial && ! $sinSalon && $horaPropuesta !== '')
                                <fieldset>
                                    <legend class="{{ $etiqueta }}">Salón <span class="font-normal text-[#6B6559]">({{ $salonDeLaClase ? 'misma sede y tipo, con capacidad suficiente' : 'de la sede del curso, con capacidad suficiente' }})</span></legend>
                                    @if ($salonesLibres->isEmpty())
                                        <p class="{{ $notaAviso }}"><x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> {{ $salonDeLaClase ? SolicitudCambio::SIN_SALONES_LIBRES : SolicitudCambio::SIN_SALONES_LIBRES_SEDE }}</p>
                                    @else
                                        <div class="flex flex-col gap-1.5">
                                            @foreach ($salonesLibres as $libre)
                                                <label wire:key="salon-{{ $libre->id }}" class="{{ $tarjetaSalon }}">
                                                    <span class="flex items-center gap-2.5">
                                                        <input type="radio" name="solicitud_salon" value="{{ $libre->id }}" wire:model="salonId" @checked($salonId === (string) $libre->id) class="h-4 w-4 accent-[#1D1C19]">
                                                        <span class="font-bold text-[var(--p900)]">{{ $libre->codigo }}</span>
                                                        <span class="text-[#57524A]">{{ $libre->tipo->nombre }} · {{ $libre->capacidad }} plazas</span>
                                                    </span>
                                                    <span class="rounded-full bg-[#F5FCD2] px-2 py-0.5 text-[11.5px] font-bold text-[#3B6506]">Libre</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif
                                    @error('salonId') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                    @error('salon_id') <p class="{{ $error }}">{{ $message }}</p> @enderror
                                </fieldset>
                            @endif
                        @else
                            <p class="{{ $nota }}"><x-icono nombre="info" class="h-4 w-4 shrink-0" /> Misma fecha y hora, en modalidad remota (sin salón).</p>
                        @endif

                        <div class="sm:max-w-[260px]">
                            <span class="{{ $etiqueta }}" aria-hidden="true">Motivo <span class="text-[var(--err700)]">*</span></span>
                            <x-docente.desplegable wire:model="motivo" :opciones="$motivos" etiqueta="Motivo" placeholder="Elige el motivo" variante="borde" />
                            @error('motivo') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </div>

                        <div x-data="{ largo: @js(mb_strlen($detalle)) }">
                            <label for="solicitud-detalle" class="{{ $etiqueta }}">Explicación para el supervisor <span class="text-[var(--err700)]" aria-hidden="true">*</span></label>
                            <textarea id="solicitud-detalle" wire:model="detalle" x-on:input="largo = $event.target.value.length" rows="3" minlength="10" maxlength="500" required aria-describedby="ayuda-detalle" class="{{ $control }}"></textarea>
                            <div class="mt-1 flex items-start justify-between gap-3 text-xs text-[#6B6559]">
                                <p id="ayuda-detalle">{{ SolicitudCambio::AYUDA_MOTIVO }} Entre 10 y 500 caracteres.</p>
                                <span class="shrink-0 tabular-nums" x-text="largo + '/500'">{{ mb_strlen($detalle) }}/500</span>
                            </div>
                            @error('detalle') <p class="{{ $error }}">{{ $message }}</p> @enderror
                        </div>

                        <p class="{{ $nota }}">
                            <x-icono nombre="info" class="h-4 w-4 shrink-0" />
                            Si cumple todas las reglas, la clase se reprograma al instante y se avisa a los estudiantes. Si no, pasa a revisión del supervisor y tu clase sigue en su horario.
                        </p>

                        <div class="-mx-5 -mb-4 flex justify-end gap-2 border-t border-[#ECE6DA] px-5 py-3">
                            <button type="button" wire:click="cerrar" class="{{ $botonContorno }}">Cancelar</button>
                            <button type="submit" wire:loading.attr="disabled" wire:target="enviar" class="{{ $botonOscuro }}">Reprogramar clase</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    @endif
</div>
