{{--
    Pantalla del Supervisor «Reprogramaciones» (Admin\SolicitudesCambioClase\Index).
    Diseño del Supervisor: layouts.app y componentes x-gestion.*. Sin
    videoconferencia ni enlaces.
--}}
@php
    $clase = fn ($fecha, int $minutos) => $fecha->format('d/m/Y').' · '.$fecha->format('H:i').' – '.$fecha->copy()->addMinutes($minutos)->format('H:i');
    $nombreClase = fn ($sesion) => $sesion->numero_sesion ? 'Sesión '.$sesion->numero_sesion : $sesion->titulo;
    $texto = 'text-sm text-stone-700 dark:text-stone-200';
    $tenue = 'text-xs text-stone-500 dark:text-stone-400';
    $errorTexto = 'text-xs text-red-700 dark:text-red-300';
    $estadoVisible = fn ($solicitud) => match (true) {
        $solicitud->esperaSustento() => 'Sustento solicitado',
        $solicitud->estaPendiente() => 'En revisión',
        $solicitud->estado === 'aprobada' && $solicitud->origen === 'automatica' => 'Aplicada automáticamente',
        default => $estados[$solicitud->estado] ?? $solicitud->estado,
    };
@endphp

<x-gestion.pagina>
    <x-gestion.encabezado titulo="Reprogramaciones" icono="calendario"
        descripcion="Las reprogramaciones que cumplen las reglas se aplican solas; aquí decides las que quedaron en revisión y ves el registro." />

    <x-gestion.alertas />

    <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar reprogramaciones">
        @foreach ($this::PESTANAS as $clave => $etiqueta)
            <x-gestion.boton :variante="$pestana === $clave ? 'primario' : 'secundario'" tamano="sm" wire:click="verPestana('{{ $clave }}')"
                aria-pressed="{{ $pestana === $clave ? 'true' : 'false' }}">
                {{ $etiqueta }}@if ($clave === 'revision') ({{ $totalPendientes }})@endif
            </x-gestion.boton>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-gestion.tarjeta :titulo="$this::PESTANAS[$pestana]" :relleno="false">
            @if ($solicitudes->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="calendario" :titulo="$pestana === 'revision' ? 'No hay reprogramaciones en revisión' : 'Aún no hay reprogramaciones en el registro'" />
                </div>
            @else
                <ul class="divide-y divide-arena-medio dark:divide-stone-700">
                    @foreach ($solicitudes as $solicitud)
                        <li wire:key="solicitud-{{ $solicitud->id }}" @class(['flex items-start justify-between gap-3 px-4 py-3', 'bg-arena-claro dark:bg-stone-700/40' => $seleccion?->id === $solicitud->id])>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-stone-800 dark:text-stone-100">
                                    {{ $solicitud->curso->nombre }} · {{ $nombreClase($solicitud->sesion) }}
                                </p>
                                <p class="{{ $tenue }}">
                                    {{ $solicitud->solicitante?->name ?? 'Profesor eliminado' }} · {{ $solicitud->fecha_original->format('d/m/Y H:i') }}
                                    · Motivo: {{ $motivos[$solicitud->motivo] ?? $solicitud->motivo }} · Pidió: {{ $modalidades[$solicitud->modalidad_solicitada] ?? $solicitud->modalidad_solicitada }}
                                </p>
                            </div>
                            <div class="flex shrink-0 items-center gap-2">
                                <span @class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-arena-medio text-stone-800 dark:bg-stone-700 dark:text-stone-200' => $solicitud->estaPendiente(),
                                    'bg-exito-base/20 text-exito-oscuro dark:bg-exito-oscuro/30 dark:text-exito-base' => $solicitud->estado === 'aprobada',
                                    'bg-stone-200 text-stone-700 dark:bg-stone-700 dark:text-stone-200' => ! $solicitud->estaPendiente() && $solicitud->estado !== 'aprobada'])>{{ $estadoVisible($solicitud) }}</span>
                                <x-gestion.boton variante="fantasma" tamano="sm" wire:click="seleccionar({{ $solicitud->id }})" aria-label="Revisar la reprogramación de {{ $solicitud->curso->nombre }}">
                                    {{ $solicitud->estaPendiente() ? 'Revisar' : 'Ver' }}
                                </x-gestion.boton>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <div class="px-4 py-3">{{ $solicitudes->links() }}</div>
            @endif
        </x-gestion.tarjeta>

        @if ($seleccion)
            @php $sesion = $seleccion->sesion; $aulaActual = \App\Services\Asistencia\SolicitudesCambioClase::aulaDe($sesion); @endphp
            <x-gestion.tarjeta :titulo="$seleccion->curso->nombre.' · '.$nombreClase($sesion)">
                @error('solicitud')
                    <p role="alert" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</p>
                @enderror
                @error('horario')
                    <p role="alert" class="mb-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">{{ $message }}</p>
                @enderror

                <dl class="grid grid-cols-[150px_1fr] gap-x-4 gap-y-2">
                    <dt class="{{ $tenue }}">Profesor</dt>
                    <dd class="{{ $texto }}">{{ $seleccion->solicitante?->name ?? 'Profesor eliminado' }}</dd>
                    <dt class="{{ $tenue }}">Estado</dt>
                    <dd class="{{ $texto }}">{{ $estadoVisible($seleccion) }}@if ($seleccion->decisor) · {{ $seleccion->decisor->name }}, {{ $seleccion->decidido_en?->format('d/m/Y H:i') }}@endif</dd>
                    <dt class="{{ $tenue }}">Clase original</dt>
                    <dd class="{{ $texto }}">{{ $clase($seleccion->fecha_original, $seleccion->duracion_original) }}@if ($seleccion->estaPendiente() && $aulaActual) · Aula {{ $aulaActual }}@endif</dd>
                    <dt class="{{ $tenue }}">Qué pide</dt>
                    <dd class="{{ $texto }}">{{ $tipos[$seleccion->tipo] ?? $seleccion->tipo }} · {{ $modalidades[$seleccion->modalidad_solicitada] ?? $seleccion->modalidad_solicitada }}</dd>
                    @if ($seleccion->fecha_propuesta)
                        <dt class="{{ $tenue }}">Fecha que eligió</dt>
                        <dd class="{{ $texto }}">{{ $seleccion->fecha_propuesta->format('d/m/Y H:i') }}@if ($seleccion->estaPendiente() && $seleccion->salonNuevo) · Salón {{ $seleccion->salonNuevo->codigo }}@endif</dd>
                    @endif
                    <dt class="{{ $tenue }}">Motivo</dt>
                    <dd class="{{ $texto }}">{{ $motivos[$seleccion->motivo] ?? $seleccion->motivo }}</dd>
                    @if ($seleccion->detalle)
                        <dt class="{{ $tenue }}">Explicación</dt>
                        <dd class="{{ $texto }}">{{ $seleccion->detalle }}</dd>
                    @endif
                    @if ($seleccion->razon_revision)
                        <dt class="{{ $tenue }}">Por qué está en revisión</dt>
                        <dd class="{{ $texto }}">{{ $seleccion->razon_revision }}</dd>
                    @endif
                    @if ($seleccion->sustento_pedido_en)
                        <dt class="{{ $tenue }}">Sustento pedido</dt>
                        <dd class="{{ $texto }}">{{ $seleccion->sustento_pedido }} <span class="{{ $tenue }}">({{ $seleccion->sustento_pedido_en->format('d/m/Y H:i') }})</span></dd>
                        <dt class="{{ $tenue }}">Respuesta</dt>
                        <dd class="{{ $texto }}">
                            @if ($seleccion->sustento_respondido_en)
                                {{ $seleccion->sustento_respuesta }} <span class="{{ $tenue }}">({{ $seleccion->sustento_respondido_en->format('d/m/Y H:i') }})</span>
                                @if ($seleccion->sustento_archivo)
                                    <a href="{{ route('reprogramaciones.sustento', $seleccion) }}" target="_blank" rel="noopener"
                                        class="mt-1 inline-flex items-center gap-1 text-sm font-semibold text-menta-oscuro hover:underline dark:text-menta-base">
                                        <x-icono nombre="descargar" class="h-4 w-4" /> Ver y descargar sustento
                                    </a>
                                @endif
                            @else
                                <span class="{{ $tenue }}">El profesor aún no responde.</span>
                            @endif
                        </dd>
                    @endif
                    @unless ($seleccion->estaPendiente())
                        @if (in_array($seleccion->estado, ['aprobada', 'revertida'], true) && $seleccion->fecha_nueva)
                            <dt class="{{ $tenue }}">Nueva clase</dt>
                            <dd class="{{ $texto }}">{{ $clase($seleccion->fecha_nueva, (int) $seleccion->duracion_nueva) }} · {{ $modalidades[$seleccion->modalidad_nueva] ?? $seleccion->modalidad_nueva }}@if ($seleccion->aula_nueva) · Aula {{ $seleccion->aula_nueva }}@endif</dd>
                        @endif
                        @if ($seleccion->origen)
                            <dt class="{{ $tenue }}">Aplicada por</dt>
                            <dd class="{{ $texto }}">{{ $origenes[$seleccion->origen] ?? $seleccion->origen }}</dd>
                        @endif
                        @if ($seleccion->comentario_decision)
                            <dt class="{{ $tenue }}">Comentario</dt>
                            <dd class="{{ $texto }}">{{ $seleccion->comentario_decision }}</dd>
                        @endif
                        @if ($seleccion->estado === 'revertida')
                            <dt class="{{ $tenue }}">Revertida</dt>
                            <dd class="{{ $texto }}">{{ $seleccion->revertidor?->name }}, {{ $seleccion->revertida_en?->format('d/m/Y H:i') }}@if ($seleccion->comentario_reversion) · {{ $seleccion->comentario_reversion }}@endif</dd>
                        @endif
                    @endunless
                </dl>

                @if ($seleccion->estaPendiente())
                    <form wire:submit="aprobar" class="mt-5 space-y-4 border-t border-arena-medio pt-4 dark:border-stone-700">
                        <h3 class="text-sm font-semibold text-stone-800 dark:text-stone-100">Tu decisión</h3>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <x-gestion.campo etiqueta="Fecha" nombre="fecha" requerido>
                                <x-gestion.control id="fecha" type="date" wire:model.live="fecha" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Hora de inicio" nombre="horaInicio" requerido>
                                <x-gestion.control id="horaInicio" type="time" wire:model.live="horaInicio" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Hora de fin" nombre="horaFin" requerido>
                                <x-gestion.control id="horaFin" type="time" wire:model.live="horaFin" />
                            </x-gestion.campo>
                        </div>
                        @foreach (['fecha', 'hora_inicio', 'hora_fin'] as $campo)
                            @error($campo) <p class="{{ $errorTexto }}">{{ $message }}</p> @enderror
                        @endforeach
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-gestion.desplegable id="modalidad" etiqueta="Modalidad" wire:model.live="modalidad" :seleccionado="$modalidad" :opciones="$modalidades" />
                                @error('modalidad') <p class="mt-1 {{ $errorTexto }}">{{ $message }}</p> @enderror
                            </div>
                            @if ($modalidad === 'presencial')
                                <div>
                                    <x-gestion.desplegable id="salonElegido" etiqueta="Salón" wire:model.live="salonElegido" :seleccionado="$salonElegido"
                                        :opciones="$opcionesSalon" placeholder="Elige un salón libre" />
                                    @error('salonElegido') <p class="mt-1 {{ $errorTexto }}">{{ $message }}</p> @enderror
                                    @error('salon_id') <p class="mt-1 {{ $errorTexto }}">{{ $message }}</p> @enderror
                                    @if (! $horarioValido)
                                        <p class="mt-1 {{ $tenue }}">Indica fecha y horas válidas para ver los salones libres.</p>
                                    @elseif ($salonNoLibre)
                                        <p class="mt-1 {{ $errorTexto }}">El salón {{ $salonNoLibre->codigo }} no está libre en ese horario: elige otro.</p>
                                    @endif
                                </div>
                            @else
                                <p class="{{ $tenue }} sm:pt-7">En remota no se usa salón; la descripción de la clase dirá «Modalidad: Remota».</p>
                            @endif
                        </div>

                        @if ($modalidad === 'presencial' && $salonElegido === $this::SIN_SALON)
                            <div class="rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                                <p class="mb-3 text-sm text-amber-800 dark:text-amber-200">
                                    Sin salón asignado: la clase no queda en ningún salón del catálogo y no se comprueba la disponibilidad. Solo se escribe el aula en su descripción.
                                </p>
                                <x-gestion.campo etiqueta="Aula (texto)" nombre="aula" requerido>
                                    <x-gestion.control id="aula" wire:model.live.debounce.500ms="aula" maxlength="50" placeholder="C-302" />
                                </x-gestion.campo>
                            </div>
                        @endif

                        @if ($avisos !== [])
                            <ul class="space-y-1 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200" aria-label="Avisos">
                                @foreach ($avisos as $aviso)
                                    <li class="flex items-start gap-2"><x-icono nombre="alerta" class="mt-0.5 h-4 w-4 shrink-0" /> {{ $aviso }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <x-gestion.campo etiqueta="Comentario para el profesor" nombre="comentario" ayuda="Obligatorio para rechazar.">
                            <x-gestion.control tipo="textarea" id="comentario" rows="2" wire:model="comentario" maxlength="1000" />
                        </x-gestion.campo>

                        <div class="flex flex-wrap justify-end gap-2">
                            <x-gestion.boton variante="secundario" wire:click="cerrarDetalle">Cerrar</x-gestion.boton>
                            <x-gestion.boton variante="peligro" wire:click="rechazar" wire:loading.attr="disabled">Rechazar</x-gestion.boton>
                            <x-gestion.boton type="submit" icono="check" wire:loading.attr="disabled">Aprobar y reprogramar</x-gestion.boton>
                        </div>
                    </form>

                    @unless ($seleccion->sustento_pedido_en)
                        <form wire:submit="pedirSustento" class="mt-5 space-y-3 border-t border-arena-medio pt-4 dark:border-stone-700">
                            <h3 class="text-sm font-semibold text-stone-800 dark:text-stone-100">Pedir sustento</h3>
                            <p class="{{ $tenue }}">La reprogramación sigue en revisión; el profesor responde con un texto y, si quiere, un PDF o una imagen.</p>
                            <x-gestion.campo etiqueta="Qué necesitas" nombre="pedido">
                                <x-gestion.control tipo="textarea" id="pedidoSustento" rows="2" wire:model="pedidoSustento" maxlength="1000" />
                            </x-gestion.campo>
                            <div class="flex justify-end">
                                <x-gestion.boton type="submit" variante="secundario" icono="documento" wire:loading.attr="disabled">Pedir sustento</x-gestion.boton>
                            </div>
                        </form>
                    @endunless
                @elseif ($seleccion->estado === 'aprobada' && $seleccion->origen)
                    <form wire:submit="revertir" class="mt-5 space-y-3 border-t border-arena-medio pt-4 dark:border-stone-700">
                        <h3 class="text-sm font-semibold text-stone-800 dark:text-stone-100">Revertir</h3>
                        <p class="{{ $tenue }}">La clase vuelve a su fecha, hora, duración y salón originales solo si ese horario sigue libre.</p>
                        <x-gestion.campo etiqueta="Comentario (opcional)" nombre="comentario">
                            <x-gestion.control tipo="textarea" id="comentarioReversion" rows="2" wire:model="comentarioReversion" maxlength="1000" />
                        </x-gestion.campo>
                        <div class="flex flex-wrap justify-end gap-2">
                            <x-gestion.boton variante="secundario" wire:click="cerrarDetalle">Cerrar</x-gestion.boton>
                            <x-gestion.boton type="submit" variante="peligro" icono="refrescar" wire:loading.attr="disabled"
                                wire:confirm="La clase volverá a su horario original y se avisará al profesor y a los estudiantes. ¿Revertir?">Revertir</x-gestion.boton>
                        </div>
                    </form>
                @else
                    <div class="mt-4 flex justify-end">
                        <x-gestion.boton variante="secundario" wire:click="cerrarDetalle">Cerrar</x-gestion.boton>
                    </div>
                @endif
            </x-gestion.tarjeta>
        @endif
    </div>
</x-gestion.pagina>
