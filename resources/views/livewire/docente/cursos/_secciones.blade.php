{{--
    Secciones fijas de una semana (o de las prácticas de campo) en el detalle
    del curso del Profesor (mockup "CursoDetalle"): Mensaje de inicio,
    Recursos de aprendizaje, Actividades complementarias y Grabación de la
    clase, más el Foro antiguo si la semana tiene alguno. Mismas acciones que
    livewire/cursos/_secciones-leccion; cambia solo la presentación: "Agregar"
    es un botón secundario (contorno) y las acciones tienen 44 px.

    Recibe $modulo (null si la semana aún no tiene contenido; sus lecciones
    vienen precargadas) y $semanaClave (0 = prácticas). Solo una sección
    abierta por semana ($seccionesAbiertas, que Alpine cambia sin pedir al servidor).
--}}
@php
    $lecciones = $modulo ? $modulo->lecciones->groupBy(fn ($leccion) => $leccion->seccion()) : collect();
    $grupos = collect(\App\Models\Leccion::SECCIONES)->map(fn ($datos, $clave) => $lecciones->get($clave, collect()));
    if ($lecciones->has(\App\Models\Leccion::SECCION_HEREDADA)) {
        $grupos->put(\App\Models\Leccion::SECCION_HEREDADA, $lecciones->get(\App\Models\Leccion::SECCION_HEREDADA));
    }
    $estilosEstado = [
        'publicado' => 'bg-exito-base/15 text-exito-oscuro',
        'programado' => 'bg-amber-100 text-amber-800',
        'borrador' => 'bg-celeste text-slate-700',
    ];
    $estilosIcono = [
        'mensaje_inicio' => 'bg-celeste text-azul-oscuro',
        'recursos' => 'bg-cielo-claro text-azul-fondo',
        'actividades' => 'bg-celeste text-imperial',
        'grabacion' => 'bg-celeste text-azul-oscuro',
        \App\Models\Leccion::SECCION_HEREDADA => 'bg-celeste text-slate-700',
    ];
    $abiertaInicial = $seccionesAbiertas[$semanaClave] ?? null;
    $enfoque = 'focus-visible:outline-none focus-visible:ring-[3px] focus-visible:ring-cielo';
    $botonAgregar = "inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 text-[13px] font-semibold text-azul-oscuro transition hover:border-azul-oscuro hover:bg-celeste motion-reduce:transition-none {$enfoque}";
    $claseAccion = "inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-lg px-2 text-xs font-semibold transition motion-reduce:transition-none {$enfoque}";
@endphp
<div x-data="{
        get abierta() { return (this.$wire.seccionesAbiertas ?? {})[{{ $semanaClave }}] ?? null },
        alternar(clave, boton) {
            const antes = boton.getBoundingClientRect().top;
            const estado = { ...(this.$wire.seccionesAbiertas ?? {}) };
            if (this.abierta === clave) { delete estado[{{ $semanaClave }}] } else { estado[{{ $semanaClave }}] = clave }
            this.$wire.seccionesAbiertas = estado;
            {{-- Si se cierra una sección de arriba, la fila pulsada se queda en el mismo lugar de la pantalla. --}}
            this.$nextTick(() => { const desfase = boton.getBoundingClientRect().top - antes; if (desfase) window.scrollBy(0, desfase) });
        },
    }">
    @foreach ($grupos as $claveSeccion => $elementos)
        @php
            $esHeredada = $claveSeccion === \App\Models\Leccion::SECCION_HEREDADA;
            $datosSeccion = $esHeredada
                ? ['etiqueta' => 'Foro de preguntas (formato anterior)', 'icono' => 'chat', 'descripcion' => 'Creado con el formulario anterior. Se puede editar o eliminar, pero ya no se agrega contenido nuevo aquí.']
                : \App\Models\Leccion::SECCIONES[$claveSeccion];
            $hayMensaje = $claveSeccion === 'mensaje_inicio' && $elementos->isNotEmpty();
            $idSeccion = 'semana-'.$semanaClave.'-'.$claveSeccion;
            $estaAbierta = $abiertaInicial === $claveSeccion;
        @endphp
        <section wire:key="{{ $idSeccion }}" aria-labelledby="{{ $idSeccion }}-titulo" class="border-t border-celeste">
            <div class="flex flex-wrap items-center gap-x-3 gap-y-2 py-2.5 sm:flex-nowrap">
                <button type="button" id="{{ $idSeccion }}-boton" x-on:click="alternar('{{ $claveSeccion }}', $el)"
                    aria-controls="{{ $idSeccion }}-contenido"
                    aria-expanded="{{ $estaAbierta ? 'true' : 'false' }}" x-bind:aria-expanded="(abierta === '{{ $claveSeccion }}').toString()"
                    class="group flex min-h-11 min-w-0 flex-1 basis-full items-center gap-3.5 rounded-lg text-left sm:basis-auto {{ $enfoque }} focus-visible:ring-offset-2">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $estilosIcono[$claveSeccion] }}">
                        <x-icono :nombre="$datosSeccion['icono']" class="h-5 w-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span id="{{ $idSeccion }}-titulo" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-texto group-hover:text-azul-oscuro">
                            {{ $datosSeccion['etiqueta'] }}
                            <span class="rounded-full border border-celeste bg-fondo px-2 text-xs font-bold text-slate-500"
                                aria-label="{{ $elementos->count() }} {{ $elementos->count() === 1 ? 'elemento' : 'elementos' }}">{{ $elementos->count() }}</span>
                        </span>
                        <span class="mt-0.5 block text-[13px] text-slate-500">{{ $datosSeccion['descripcion'] }}</span>
                    </span>
                    <x-icono nombre="flecha-abajo"
                        class="h-4 w-4 shrink-0 text-slate-500 transition-transform duration-200 group-hover:text-azul-oscuro motion-reduce:transition-none {{ $estaAbierta ? 'rotate-180' : '' }}"
                        x-bind:class="{ 'rotate-180': abierta === '{{ $claveSeccion }}' }" />
                </button>
                @if ($editable && ! $esHeredada)
                    {{-- Fuera del botón de la fila: abre el formulario sin abrir ni cerrar la sección. --}}
                    <button type="button" class="{{ $botonAgregar }} ml-12 sm:ml-0"
                        wire:click="agregarLeccion('{{ $claveSeccion }}', {{ $semanaClave }})"
                        aria-label="{{ ($hayMensaje ? 'Editar ' : 'Agregar a ').mb_strtolower($datosSeccion['etiqueta']) }}">
                        <x-icono :nombre="$hayMensaje ? 'lapiz' : 'mas'" class="h-4 w-4" />
                        {{ $hayMensaje ? 'Editar' : 'Agregar' }}
                    </button>
                @endif
            </div>

            <div id="{{ $idSeccion }}-contenido" role="region" aria-labelledby="{{ $idSeccion }}-titulo"
                x-show="abierta === '{{ $claveSeccion }}'" @unless ($estaAbierta) style="display: none" @endunless
                class="pb-2.5 sm:pl-12">
                @if ($elementos->isEmpty())
                    <p class="rounded-lg border border-dashed border-slate-200 px-3 py-2.5 text-[13px] text-slate-600">
                        Aún no hay elementos en esta sección.
                    </p>
                @else
                    @php $ordenable = $editable && $elementos->count() > 1; @endphp
                    {{-- Arrastrar y soltar SOLO dentro de esta sección (grupo propio de SortableJS, sin pull/put):
                         al soltar, el DOM vuelve a su lugar y Livewire guarda y vuelve a pintar el nuevo orden. --}}
                    <div @if ($ordenable) x-data="{
                            guardado: false,
                            montar() {
                                window.cargarSortable().then(() => {
                                    const lista = this.$refs.lista;
                                    if (lista._sortable) return;
                                    lista._sortable = window.Sortable.create(lista, {
                                        group: { name: '{{ $idSeccion }}', pull: false, put: false },
                                        handle: '[data-asa]',
                                        draggable: '[data-leccion]',
                                        animation: 150,
                                        ghostClass: 'leccion-fantasma',
                                        onEnd: (evento) => {
                                            const { item, oldIndex, newIndex } = evento;
                                            if (oldIndex === newIndex) return;
                                            const ids = [...lista.querySelectorAll('[data-leccion]')].map((fila) => Number(fila.dataset.leccion));
                                            lista.insertBefore(item, lista.children[oldIndex + (oldIndex > newIndex ? 1 : 0)] ?? null);
                                            this.$wire.ordenarSeccion(ids).then(() => {
                                                this.guardado = true;
                                                setTimeout(() => this.guardado = false, 2500);
                                            });
                                        },
                                    });
                                });
                            },
                        }" x-init="montar()" @endif>
                    <ul x-ref="lista" class="overflow-hidden rounded-lg border border-celeste bg-white" aria-label="Elementos de {{ mb_strtolower($datosSeccion['etiqueta']) }}">
                        @foreach ($elementos as $leccion)
                            @php $tipo = $leccion->tipoRecurso(); @endphp
                            <li class="flex flex-col gap-2 border-t border-celeste px-3 py-2.5 first:border-t-0 sm:flex-row sm:items-start [&.leccion-fantasma]:bg-celeste [&.leccion-fantasma]:outline-dashed [&.leccion-fantasma]:outline-2 [&.leccion-fantasma]:-outline-offset-2 [&.leccion-fantasma]:outline-azul-oscuro"
                                wire:key="leccion-{{ $leccion->id }}" data-leccion="{{ $leccion->id }}">
                                <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                    @if ($ordenable)
                                        <span data-asa title="Arrastra para cambiar el orden" aria-hidden="true"
                                            class="-ml-1 flex h-8 w-6 shrink-0 cursor-grab touch-none items-center justify-center rounded text-slate-400 hover:bg-celeste hover:text-azul-oscuro active:cursor-grabbing">
                                            <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" /><circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" /><circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" /></svg>
                                        </span>
                                    @endif
                                    <span class="mt-px flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-celeste text-azul-oscuro" title="{{ $tipo['etiqueta'] }}">
                                        <x-icono :nombre="$tipo['icono']" class="h-4 w-4" />
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                            <span class="break-words text-sm font-medium text-texto">{{ $leccion->titulo }}</span>
                                            <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold {{ $estilosEstado[$leccion->estado] ?? '' }}">
                                                {{ $leccion->estado === 'programado' && $leccion->fecha_publicacion ? 'Programada para '.$leccion->fecha_publicacion->formatoFechaHora() : ucfirst($leccion->estado) }}
                                            </span>
                                        </div>
                                        @if ($leccion->descripcion)
                                            <p class="mt-0.5 line-clamp-2 text-xs text-slate-600">{{ $leccion->descripcion }}</p>
                                        @endif
                                        <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600">
                                            @if ($leccion->video_url)
                                                <a href="{{ $leccion->video_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 rounded hover:text-azul-oscuro hover:underline {{ $enfoque }}"><x-icono nombre="video" class="h-3.5 w-3.5" /> {{ $claveSeccion === 'grabacion' ? 'Grabación' : 'Video' }}</a>
                                            @endif
                                            @if ($leccion->material_url)
                                                <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($leccion->material_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 rounded hover:text-azul-oscuro hover:underline {{ $enfoque }}"><x-icono :nombre="$leccion->material_nombre ? 'material' : 'enlace'" class="h-3.5 w-3.5" /> {{ $leccion->material_nombre ?? 'Enlace' }}</a>
                                            @endif
                                            <span class="inline-flex items-center gap-1"><x-icono nombre="check-circulo" class="h-3.5 w-3.5" /> {{ $leccion->completadas_count }}/{{ $totalEstudiantesActivos }} lo completaron</span>
                                        </div>
                                    </div>
                                </div>
                                @if ($editable)
                                    <div class="flex shrink-0 flex-wrap items-center gap-0.5 pl-10 sm:pl-0">
                                        @if ($elementos->count() > 1)
                                            <button type="button" wire:click="moverLeccion({{ $leccion->id }}, -1)" @disabled($loop->first)
                                                class="{{ $claseAccion }} text-slate-600 hover:bg-celeste hover:text-azul-oscuro disabled:opacity-30" aria-label="Subir {{ $leccion->titulo }}">
                                                <x-icono nombre="flecha-arriba" class="h-4 w-4" />
                                            </button>
                                            <button type="button" wire:click="moverLeccion({{ $leccion->id }}, 1)" @disabled($loop->last)
                                                class="{{ $claseAccion }} text-slate-600 hover:bg-celeste hover:text-azul-oscuro disabled:opacity-30" aria-label="Bajar {{ $leccion->titulo }}">
                                                <x-icono nombre="flecha-abajo" class="h-4 w-4" />
                                            </button>
                                        @endif
                                        {{-- El Mensaje de inicio se edita con el botón "Editar" de su fila. --}}
                                        @unless ($claveSeccion === 'mensaje_inicio')
                                            <button type="button" wire:click="editarLeccion({{ $leccion->id }})" aria-label="Editar {{ $leccion->titulo }}"
                                                class="{{ $claseAccion }} text-slate-600 hover:bg-celeste hover:text-azul-oscuro">
                                                <x-icono nombre="lapiz" class="h-4 w-4" /> Editar
                                            </button>
                                        @endunless
                                        <button type="button" wire:click="confirmarEliminarLeccion({{ $leccion->id }})" aria-label="Eliminar {{ $leccion->titulo }}"
                                            class="{{ $claseAccion }} text-red-700 hover:bg-red-50">
                                            <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                        </button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($ordenable)
                        <p class="mt-1.5 flex min-h-5 items-center gap-1.5 text-xs font-semibold text-exito-oscuro" role="status" aria-live="polite">
                            <span x-show="guardado" x-transition.opacity style="display: none" class="inline-flex items-center gap-1.5"><x-icono nombre="check-circulo" class="h-4 w-4" /> Orden guardado</span>
                        </p>
                    @endif
                    </div>
                @endif
            </div>
        </section>
    @endforeach
</div>
