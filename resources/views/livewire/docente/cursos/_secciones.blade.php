{{--
    Secciones fijas de una semana en el detalle del curso del Profesor
    (Leccion::SECCIONES: Mensaje de inicio, Recursos de aprendizaje,
    Actividades complementarias y Grabación de la clase; más el Foro antiguo
    si la semana tiene alguno), en UNA tarjeta con vista previa de lo que hay.
    Mismas acciones que livewire/cursos/_secciones-leccion: "Editar"/"Agregar"
    (agregarLeccion) y, al desplegar, la gestión de cada elemento (ordenar
    arrastrando, visibilidad y "Editar contenido" en el menú de
    x-docente.visibilidad, eliminar).

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
    $abiertaInicial = $seccionesAbiertas[$semanaClave] ?? null;
    $enfoque = 'focus-visible:outline focus-visible:outline-[3px] focus-visible:outline-offset-2 focus-visible:outline-[color:var(--p700)]';
    $claseAccion = "inline-flex min-h-11 min-w-11 items-center justify-center gap-1 rounded-lg px-2 text-xs font-semibold transition-colors motion-reduce:transition-none {$enfoque}";
    // Tipo corto del elemento para la vista previa: extensión del archivo, "Enlace" o "Video".
    $tipoCorto = function ($leccion): string {
        if ($leccion->material_url) {
            if (\App\Services\Multimedia\SubidorArchivos::esEnlaceExterno($leccion->material_url)) {
                return 'Enlace';
            }
            $extension = pathinfo((string) ($leccion->material_nombre ?: $leccion->material_url), PATHINFO_EXTENSION);

            return $extension !== '' ? mb_strtoupper($extension) : 'Archivo';
        }

        return $leccion->video_url ? 'Enlace' : $leccion->tipoRecurso()['etiqueta'];
    };
@endphp
<div class="overflow-hidden rounded-[14px] border-[1.5px] border-[#d9e8e4] bg-white"
    x-data="{
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
            $vacia = $elementos->isEmpty();
            $hayMensaje = $claveSeccion === 'mensaje_inicio' && ! $vacia;
            $idSeccion = 'semana-'.$semanaClave.'-'.$claveSeccion;
            $estaAbierta = $abiertaInicial === $claveSeccion;
            $primero = $elementos->first();
        @endphp
        <section wire:key="{{ $idSeccion }}" aria-labelledby="{{ $idSeccion }}-titulo" data-seccion="{{ $claveSeccion }}" data-vacia="{{ $vacia ? 'si' : 'no' }}"
            @class(['border-t border-[#e6efec] first:border-t-0', 'bg-[#FAFCFB]' => $vacia])>
            {{-- Encabezado clicable: el botón de abrir/cerrar cubre solo esta fila (absolute inset-0); "Agregar"/"Editar" queda encima (z-10). --}}
            <div class="relative grid cursor-pointer grid-cols-[44px_1fr_auto] items-center gap-3.5 px-4 py-3.5 hover:bg-[#F3FAF8]">
                <span @class(['grid h-11 w-11 place-items-center rounded-[13px] border-[1.5px] bg-white', 'border-dashed border-[#d4e5e0] text-[#7d918d]' => $vacia, 'border-[#d4e5e0] text-[var(--p700)]' => ! $vacia]) aria-hidden="true">
                    <x-icono :nombre="$datosSeccion['icono']" class="h-5 w-5" />
                </span>
                <div class="min-w-0">
                    <h5 id="{{ $idSeccion }}-titulo" class="flex flex-wrap items-center gap-2 text-[14.5px] font-bold text-[var(--p900)]">
                        {{ $datosSeccion['etiqueta'] }}
                        <span @class(['min-w-[22px] rounded-full px-[7px] py-px text-center text-[11.5px] font-bold', 'bg-[#eef2f1] text-[#7d918d]' => $vacia, 'bg-[#E4EFEC] text-[var(--p700)]' => ! $vacia])
                            aria-label="{{ $elementos->count() }} {{ $elementos->count() === 1 ? 'elemento' : 'elementos' }}">{{ $elementos->count() }}</span>
                    </h5>
                    <p class="mt-[3px] text-[12.5px] text-[#5b706c]">{{ $datosSeccion['descripcion'] }}@if ($vacia) Aún no hay ninguna.@endif</p>

                    {{-- Vista previa sin abrir nada. --}}
                    @if ($claveSeccion === 'mensaje_inicio' && $primero)
                        <blockquote class="mt-[9px] max-w-[640px] border-l-[3px] border-[var(--p700)] pl-2.5 text-[12.5px] leading-[1.45] text-[#2d4541]">“{{ \Illuminate\Support\Str::limit(trim((string) ($primero->descripcion ?: $primero->titulo)), 160) }}”</blockquote>
                    @elseif (! $vacia)
                        <ul class="mt-[9px] flex flex-wrap gap-2" aria-label="Vista previa de {{ mb_strtolower($datosSeccion['etiqueta']) }}">
                            @foreach ($elementos->take(4) as $leccion)
                                <li class="inline-flex max-w-[330px] items-center gap-[7px] rounded-[9px] bg-[#F1F7F5] px-2.5 py-[5px] text-[12.5px] text-[#2d4541]" wire:key="previa-{{ $leccion->id }}">
                                    <x-icono :nombre="$leccion->tipoRecurso()['icono']" class="h-3.5 w-3.5 shrink-0 text-[var(--p700)]" />
                                    <span class="truncate font-semibold" title="{{ $leccion->titulo }}">{{ $leccion->titulo }}</span>
                                    <em class="whitespace-nowrap text-[11.5px] not-italic text-[#5b706c]">{{ $tipoCorto($leccion) }}</em>
                                </li>
                            @endforeach
                            @if ($elementos->count() > 4)
                                <li class="inline-flex items-center px-1 text-[12.5px] font-semibold text-[var(--p700)]">+{{ $elementos->count() - 4 }} más</li>
                            @endif
                        </ul>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if ($editable && ! $esHeredada)
                        <button type="button" wire:click="agregarLeccion('{{ $claveSeccion }}', {{ $semanaClave }})"
                            @class(['relative z-10 inline-flex h-[34px] items-center gap-[7px] whitespace-nowrap rounded-[10px] border-[1.5px] px-3.5 text-[12.5px] font-bold text-[var(--p700)] transition-colors motion-reduce:transition-none', $enfoque, 'border-[var(--p700)] bg-[var(--mint)]' => $vacia, 'border-[#c9ddd8] bg-white hover:bg-[var(--mint)]' => ! $vacia])
                            aria-label="{{ ($hayMensaje ? 'Editar ' : 'Agregar a ').mb_strtolower($datosSeccion['etiqueta']) }}">
                            <x-icono :nombre="$hayMensaje ? 'lapiz' : 'mas'" class="h-3.5 w-3.5" />
                            {{ $hayMensaje ? 'Editar' : 'Agregar' }}
                        </button>
                    @endif
                    <button type="button" id="{{ $idSeccion }}-boton" x-on:click="alternar('{{ $claveSeccion }}', $el)"
                        aria-controls="{{ $idSeccion }}-contenido" aria-label="Ver el detalle de {{ mb_strtolower($datosSeccion['etiqueta']) }}"
                        aria-expanded="{{ $estaAbierta ? 'true' : 'false' }}" x-bind:aria-expanded="(abierta === '{{ $claveSeccion }}').toString()"
                        class="absolute inset-0 cursor-pointer focus-visible:outline focus-visible:outline-[3px] focus-visible:-outline-offset-[3px] focus-visible:outline-[color:var(--p700)]"></button>
                </div>
            </div>

            <div id="{{ $idSeccion }}-contenido" role="region" aria-labelledby="{{ $idSeccion }}-titulo"
                x-show="abierta === '{{ $claveSeccion }}'" @unless ($estaAbierta) style="display: none" @endunless
                class="pb-3.5 pl-[74px] pr-4">
                @if ($vacia)
                    <p class="rounded-lg border-[1.5px] border-dashed border-[#d4e5e0] px-3 py-2.5 text-[13px] text-[#5b706c]">Aún no hay elementos en esta sección.</p>
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
                    <ul x-ref="lista" class="border-l-2 border-[#d4e5e0] pl-3.5" aria-label="Elementos de {{ mb_strtolower($datosSeccion['etiqueta']) }}">
                        @foreach ($elementos as $leccion)
                            @php $tipo = $leccion->tipoRecurso(); @endphp
                            <li class="flex items-start gap-3 border-t border-dashed border-[#d4e5e0] py-2 first:border-t-0 [&.leccion-fantasma]:bg-[var(--mint)] [&.leccion-fantasma]:outline-dashed [&.leccion-fantasma]:outline-2 [&.leccion-fantasma]:-outline-offset-2 [&.leccion-fantasma]:outline-[var(--p700)]"
                                wire:key="leccion-{{ $leccion->id }}" data-leccion="{{ $leccion->id }}">
                                @if ($ordenable)
                                    <span data-asa title="Arrastra para cambiar el orden" aria-hidden="true"
                                        class="-ml-1 mt-1 flex h-8 w-6 shrink-0 cursor-grab touch-none items-center justify-center rounded text-[#7d918d] hover:bg-[var(--mint)] hover:text-[var(--p700)] active:cursor-grabbing">
                                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="currentColor"><circle cx="5.5" cy="3.5" r="1.25" /><circle cx="10.5" cy="3.5" r="1.25" /><circle cx="5.5" cy="8" r="1.25" /><circle cx="10.5" cy="8" r="1.25" /><circle cx="5.5" cy="12.5" r="1.25" /><circle cx="10.5" cy="12.5" r="1.25" /></svg>
                                    </span>
                                @endif
                                <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-[9px] border-[1.5px] border-[#d4e5e0] bg-white text-[var(--p700)]" title="{{ $tipo['etiqueta'] }}">
                                    <x-icono :nombre="$tipo['icono']" class="h-4 w-4" />
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <span class="break-words text-[13px] font-semibold text-[var(--p900)]">{{ $leccion->titulo }}</span>
                                    </div>
                                    @if ($leccion->descripcion)
                                        <p class="mt-0.5 line-clamp-2 text-xs text-[#5b706c]">{{ $leccion->descripcion }}</p>
                                    @endif
                                    <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-[#4b625e]">
                                        @if ($leccion->video_url)
                                            <a href="{{ $leccion->video_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 rounded hover:text-[var(--p700)] hover:underline {{ $enfoque }}"><x-icono nombre="video" class="h-3.5 w-3.5" /> {{ $claveSeccion === 'grabacion' ? 'Grabación' : 'Video' }}</a>
                                        @endif
                                        @if ($leccion->material_url)
                                            <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($leccion->material_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-6 items-center gap-1 rounded hover:text-[var(--p700)] hover:underline {{ $enfoque }}"><x-icono :nombre="$leccion->material_nombre ? 'material' : 'enlace'" class="h-3.5 w-3.5" /> {{ $leccion->material_nombre ?? 'Enlace' }}</a>
                                        @endif
                                        <span class="inline-flex items-center gap-1"><x-icono nombre="check-circulo" class="h-3.5 w-3.5" /> {{ $leccion->completadas_count }}/{{ $totalEstudiantesActivos }} lo completaron</span>
                                    </div>
                                </div>
                                @if ($editable)
                                    <div class="flex shrink-0 flex-wrap items-center gap-0.5">
                                        {{-- Visibilidad y "Editar contenido" en el mismo menú; la clave cambia con el valor para que Alpine empiece de nuevo tras guardar. --}}
                                        @php $visibilidad = \App\Livewire\Cursos\Show::visibilidadDe($leccion); @endphp
                                        <x-docente.visibilidad wire:key="visibilidad-{{ $leccion->id }}-{{ $visibilidad }}" :leccion="$leccion->id" :valor="$visibilidad" :titulo="$leccion->titulo" editar />
                                        <button type="button" wire:click="confirmarEliminarLeccion({{ $leccion->id }})" aria-label="Eliminar {{ $leccion->titulo }}"
                                            class="{{ $claseAccion }} text-[var(--err700)] hover:bg-[var(--err100)]">
                                            <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                        </button>
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($ordenable)
                        <p class="mt-1.5 flex min-h-5 items-center gap-1.5 text-xs font-semibold text-[var(--ok900)]" role="status" aria-live="polite">
                            <span x-show="guardado" x-transition.opacity style="display: none" class="inline-flex items-center gap-1.5"><x-icono nombre="check-circulo" class="h-4 w-4" /> Orden guardado</span>
                        </p>
                    @endif
                    </div>
                @endif
            </div>
        </section>
    @endforeach
</div>
