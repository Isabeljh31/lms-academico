{{--
    Secciones fijas de una semana (o de las prácticas de campo) para el
    Profesor: Mensaje de inicio, Recursos de aprendizaje, Actividades
    complementarias y Grabación de la clase, más el Foro antiguo si la semana
    tiene alguno. Recibe $modulo (null si la semana aún no tiene contenido;
    sus lecciones vienen precargadas) y $semanaClave (0 = prácticas).

    Nivel 2 (secciones): filas compactas colgadas de una guía vertical, todas
    cerradas al inicio y solo una abierta por semana ($seccionesAbiertas, que
    Alpine cambia sin pedir al servidor). La abierta lleva fondo tenue, acento
    izquierdo y chevron girado. "Agregar"/"Editar" solo abre el formulario.
    Nivel 3 (elementos): filas más pequeñas dentro de la sección abierta.
--}}
@php
    $lecciones = $modulo ? $modulo->lecciones->groupBy(fn ($leccion) => $leccion->seccion()) : collect();
    $grupos = collect(\App\Models\Leccion::SECCIONES)->map(fn ($datos, $clave) => $lecciones->get($clave, collect()));
    if ($lecciones->has(\App\Models\Leccion::SECCION_HEREDADA)) {
        $grupos->put(\App\Models\Leccion::SECCION_HEREDADA, $lecciones->get(\App\Models\Leccion::SECCION_HEREDADA));
    }
    $estilosEstado = [
        'publicado' => 'bg-exito-base/15 text-exito-oscuro',
        'programado' => 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        'borrador' => 'bg-celeste text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200',
    ];
    // Recuadro del ícono de cada sección: fondo claro + ícono oscuro, tonos de la paleta azul.
    $estilosIcono = [
        'mensaje_inicio' => 'bg-azul-oscuro/15 text-azul-oscuro',
        'recursos' => 'bg-cielo-claro text-azul-fondo',
        'actividades' => 'bg-azul-fondo/15 text-imperial',
        'grabacion' => 'bg-imperial/10 text-imperial',
        \App\Models\Leccion::SECCION_HEREDADA => 'bg-celeste text-slate-700',
    ];
    $abiertaInicial = $seccionesAbiertas[$semanaClave] ?? null;
    $claseAccion = 'inline-flex items-center gap-1 rounded-md px-1.5 py-1 text-xs font-medium transition focus:outline-none focus-visible:ring-2';
@endphp
<div class="ml-[1.375rem] border-l-2 border-azul-oscuro/20 pl-3 dark:border-azul-borde-oscuro sm:pl-4"
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
    <div class="divide-y divide-azul-oscuro/10 dark:divide-azul-borde-oscuro">
        @foreach ($grupos as $claveSeccion => $elementos)
            @php
                $esHeredada = $claveSeccion === \App\Models\Leccion::SECCION_HEREDADA;
                $datosSeccion = $esHeredada
                    ? ['etiqueta' => 'Foro de preguntas (formato anterior)', 'icono' => 'chat', 'descripcion' => 'Creado con el formulario anterior. Se puede editar o eliminar, pero ya no se agrega contenido nuevo aquí.']
                    : \App\Models\Leccion::SECCIONES[$claveSeccion];
                $hayMensaje = $claveSeccion === 'mensaje_inicio' && $elementos->isNotEmpty();
                $idSeccion = 'semana-'.$semanaClave.'-'.$claveSeccion;
                $estaAbierta = $abiertaInicial === $claveSeccion;
                $claseAbierta = 'bg-azul-oscuro/[0.06] dark:bg-azul-oscuro/15';
            @endphp
            <section wire:key="{{ $idSeccion }}" aria-labelledby="{{ $idSeccion }}-titulo"
                @class(['relative rounded-lg transition-colors motion-reduce:transition-none', $claseAbierta => $estaAbierta])
                x-bind:class="{ '{{ $claseAbierta }}': abierta === '{{ $claveSeccion }}' }">
                {{-- Acento izquierdo de la sección abierta, encima de la guía vertical. --}}
                <span aria-hidden="true" class="absolute -left-[15px] bottom-2 top-2 w-1 rounded-full bg-azul-oscuro sm:-left-[19px] dark:bg-azul-fondo"
                    x-show="abierta === '{{ $claveSeccion }}'" @unless ($estaAbierta) style="display: none" @endunless></span>

                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 px-2 py-3 sm:flex-nowrap sm:px-3">
                    <button type="button" id="{{ $idSeccion }}-boton" x-on:click="alternar('{{ $claveSeccion }}', $el)"
                        aria-controls="{{ $idSeccion }}-contenido"
                        aria-expanded="{{ $estaAbierta ? 'true' : 'false' }}" x-bind:aria-expanded="(abierta === '{{ $claveSeccion }}').toString()"
                        class="group flex min-w-0 flex-1 basis-full items-center gap-3 rounded-lg text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro focus-visible:ring-offset-2 sm:basis-auto">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl {{ $estilosIcono[$claveSeccion] }} dark:bg-azul-borde-oscuro dark:text-azul-fondo">
                            <x-icono :nombre="$datosSeccion['icono']" stroke-width="2" class="h-6 w-6" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span id="{{ $idSeccion }}-titulo" class="flex flex-wrap items-center gap-2 text-sm font-semibold text-texto dark:text-slate-100">
                                {{ $datosSeccion['etiqueta'] }}
                                <span class="rounded-full bg-white px-2 py-0.5 text-xs font-semibold text-slate-700 ring-1 ring-azul-oscuro/15 dark:bg-azul-borde-oscuro dark:text-slate-200 dark:ring-0"
                                    aria-label="{{ $elementos->count() }} {{ $elementos->count() === 1 ? 'elemento' : 'elementos' }}">{{ $elementos->count() }}</span>
                            </span>
                            <span class="mt-0.5 block text-xs text-slate-600 dark:text-slate-300">{{ $datosSeccion['descripcion'] }}</span>
                        </span>
                        <x-icono nombre="flecha-abajo" stroke-width="2"
                            class="h-4 w-4 shrink-0 text-slate-600 transition-transform duration-200 group-hover:text-azul-oscuro motion-reduce:transition-none dark:text-slate-300 {{ $estaAbierta ? 'rotate-180' : '' }}"
                            x-bind:class="{ 'rotate-180': abierta === '{{ $claveSeccion }}' }" />
                    </button>
                    @if ($editable && ! $esHeredada)
                        {{-- Fuera del botón de la fila: abre el formulario sin abrir ni cerrar la sección. --}}
                        <x-gestion.boton tamano="sm" :variante="$hayMensaje ? 'secundario' : 'primario'" :icono="$hayMensaje ? 'lapiz' : 'mas'"
                            class="ml-14 w-28 shrink-0 sm:ml-0"
                            wire:click="agregarLeccion('{{ $claveSeccion }}', {{ $semanaClave }})"
                            aria-label="{{ ($hayMensaje ? 'Editar ' : 'Agregar a ').mb_strtolower($datosSeccion['etiqueta']) }}">
                            {{ $hayMensaje ? 'Editar' : 'Agregar' }}
                        </x-gestion.boton>
                    @endif
                </div>

                <div id="{{ $idSeccion }}-contenido" role="region" aria-labelledby="{{ $idSeccion }}-titulo"
                    x-show="abierta === '{{ $claveSeccion }}'" @unless ($estaAbierta) style="display: none" @endunless
                    x-transition:enter="transition ease-out duration-150 motion-reduce:transition-none"
                    x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
                    class="pb-2 pl-2 pr-2 sm:pl-[4.25rem] sm:pr-3">
                    @if ($elementos->isEmpty())
                        <p class="border-t border-azul-oscuro/10 py-3 text-xs text-slate-600 dark:border-azul-borde-oscuro dark:text-slate-300">
                            Aún no hay elementos en esta sección.
                        </p>
                    @else
                        <ul>
                            @foreach ($elementos as $leccion)
                                @php $tipo = $leccion->tipoRecurso(); @endphp
                                <li class="flex flex-col gap-2 border-t border-azul-oscuro/10 py-2.5 dark:border-azul-borde-oscuro sm:flex-row sm:items-start" wire:key="leccion-{{ $leccion->id }}">
                                    <div class="flex min-w-0 flex-1 items-start gap-2.5">
                                        <span class="mt-px flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-white text-azul-oscuro ring-1 ring-azul-oscuro/15 dark:bg-azul-borde-oscuro dark:text-azul-fondo dark:ring-0" title="{{ $tipo['etiqueta'] }}">
                                            <x-icono :nombre="$tipo['icono']" stroke-width="1.75" class="h-4 w-4" />
                                        </span>
                                        <div class="min-w-0 flex-1">
                                            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <span class="text-[13px] font-medium text-texto dark:text-slate-100">{{ $leccion->titulo }}</span>
                                                <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $estilosEstado[$leccion->estado] ?? '' }}">
                                                    {{ $leccion->estado === 'programado' && $leccion->fecha_publicacion ? 'Programada para '.$leccion->fecha_publicacion->formatoFechaHora() : ucfirst($leccion->estado) }}
                                                </span>
                                            </div>
                                            @if ($leccion->descripcion)
                                                <p class="mt-0.5 line-clamp-2 text-xs text-slate-600 dark:text-slate-300">{{ $leccion->descripcion }}</p>
                                            @endif
                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-slate-600 dark:text-slate-300">
                                                @if ($leccion->video_url)
                                                    <a href="{{ $leccion->video_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 hover:text-azul-oscuro hover:underline"><x-icono nombre="video" class="h-3.5 w-3.5" /> {{ $claveSeccion === 'grabacion' ? 'Grabación' : 'Video' }}</a>
                                                @endif
                                                @if ($leccion->material_url)
                                                    <a href="{{ \App\Services\Multimedia\SubidorArchivos::enlace($leccion->material_url) }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 hover:text-azul-oscuro hover:underline"><x-icono :nombre="$leccion->material_nombre ? 'material' : 'enlace'" class="h-3.5 w-3.5" /> {{ $leccion->material_nombre ?? 'Enlace' }}</a>
                                                @endif
                                                <span class="inline-flex items-center gap-1"><x-icono nombre="check-circulo" class="h-3.5 w-3.5" /> {{ $leccion->completadas_count }}/{{ $totalEstudiantesActivos }} lo completaron</span>
                                            </div>
                                        </div>
                                    </div>
                                    @if ($editable)
                                        <div class="flex shrink-0 items-center gap-0.5 pl-9 sm:pl-0">
                                            @if ($elementos->count() > 1)
                                                <button type="button" wire:click="moverLeccion({{ $leccion->id }}, -1)" @disabled($loop->first)
                                                    class="{{ $claseAccion }} text-slate-600 hover:bg-white hover:text-azul-oscuro focus-visible:ring-azul-oscuro disabled:opacity-30 dark:text-slate-300 dark:hover:bg-azul-borde-oscuro" aria-label="Subir {{ $leccion->titulo }}">
                                                    <x-icono nombre="flecha-arriba" class="h-3.5 w-3.5" />
                                                </button>
                                                <button type="button" wire:click="moverLeccion({{ $leccion->id }}, 1)" @disabled($loop->last)
                                                    class="{{ $claseAccion }} text-slate-600 hover:bg-white hover:text-azul-oscuro focus-visible:ring-azul-oscuro disabled:opacity-30 dark:text-slate-300 dark:hover:bg-azul-borde-oscuro" aria-label="Bajar {{ $leccion->titulo }}">
                                                    <x-icono nombre="flecha-abajo" class="h-3.5 w-3.5" />
                                                </button>
                                            @endif
                                            {{-- El Mensaje de inicio se edita con el botón "Editar" de su fila. --}}
                                            @unless ($claveSeccion === 'mensaje_inicio')
                                                <button type="button" wire:click="editarLeccion({{ $leccion->id }})" aria-label="Editar {{ $leccion->titulo }}"
                                                    class="{{ $claseAccion }} text-slate-600 hover:bg-white hover:text-azul-oscuro focus-visible:ring-azul-oscuro dark:text-slate-300 dark:hover:bg-azul-borde-oscuro">
                                                    <x-icono nombre="lapiz" class="h-3.5 w-3.5" /> Editar
                                                </button>
                                            @endunless
                                            <button type="button" wire:click="confirmarEliminarLeccion({{ $leccion->id }})" aria-label="Eliminar {{ $leccion->titulo }}"
                                                class="{{ $claseAccion }} text-red-700 hover:bg-red-50 focus-visible:ring-red-500 dark:text-red-400 dark:hover:bg-azul-borde-oscuro">
                                                <x-icono nombre="papelera" class="h-3.5 w-3.5" /> Eliminar
                                            </button>
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </section>
        @endforeach
    </div>
</div>
