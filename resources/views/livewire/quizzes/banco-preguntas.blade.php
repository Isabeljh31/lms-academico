{{--
    Banco de preguntas del curso (Profesor). Página propia o pestaña de
    Actividades ($incrustado: sin encabezado de página ni márgenes).
    Todo el texto de las preguntas se muestra escapado ({{ }}).
--}}
@php
    $etiquetasTipoCorta = ['opcion_unica' => 'Única', 'opcion_multiple' => 'Múltiple', 'verdadero_falso' => 'V / F'];
    $idsPagina = $preguntas->pluck('id')->map(fn ($id) => (string) $id)->values();
@endphp
{{-- relative: los textos sr-only (position: absolute) quedan dentro del banco; sin esto se posicionaban
     respecto de <body>, escapaban de la columna con scroll y estiraban la página (espacio vacío y menú cortado). --}}
<div @class(['relative', 'py-8' => ! $incrustado])>
    <div @class(['mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8' => ! $incrustado, 'space-y-5' => $incrustado])>

        @unless ($incrustado)
            <x-gestion.encabezado titulo="Banco de preguntas" icono="base-datos"
                descripcion="Preguntas reutilizables de {{ $curso->nombre }}, organizadas por unidad y tema. Puedes agregarlas a cualquier quiz del curso.">
                <x-gestion.boton variante="secundario" icono="quiz" :href="route('cursos.actividades', $curso)">Actividades</x-gestion.boton>
                <x-gestion.boton variante="secundario" icono="subir" wire:click="abrirImportacion">Importar desde Excel/CSV</x-gestion.boton>
                <x-gestion.boton icono="mas" wire:click="nueva">Nueva pregunta</x-gestion.boton>
            </x-gestion.encabezado>

            <x-gestion.alertas />
        @else
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    Preguntas reutilizables, organizadas por unidad y tema. Al agregarlas a un quiz se copian: editarlas aquí no cambia los quizzes que ya las usan.
                </p>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <x-gestion.boton variante="secundario" tamano="sm" icono="subir" wire:click="abrirImportacion">Importar desde Excel/CSV</x-gestion.boton>
                    <x-gestion.boton tamano="sm" icono="mas" wire:click="nueva">Nueva pregunta</x-gestion.boton>
                </div>
            </div>

            {{-- En la carga inicial los avisos ya los muestra la pantalla Actividades; aquí solo los de sus propias acciones. --}}
            @if (\Livewire\Livewire::isLivewireRequest() && session()->hasAny(['estado', 'advertencia', 'error']))
                {{-- Tras eliminar u otra acción, el aviso se trae a la vista aunque se esté al final de la lista. --}}
                <div x-data x-init="$el.scrollIntoView({ block: 'nearest' })">
                    <x-gestion.alertas />
                </div>
            @endif
        @endunless

        {{-- Unidad actual (se recuerda por curso en este navegador) y "Ver unidades archivadas" (las anteriores). --}}
        <div class="space-y-2"
            x-data="{
                clave: @js('lms.banco-preguntas.unidad-actual.'.$curso->id),
                validas: @js($unidadesValidas),
                init() {
                    try {
                        const guardada = localStorage.getItem(this.clave);
                        if (this.validas.includes(guardada) && guardada !== $wire.unidadActual) $wire.set('unidadActual', guardada);
                    } catch (e) {}
                    $wire.$watch('unidadActual', unidad => { try { localStorage.setItem(this.clave, unidad); } catch (e) {} });
                },
            }">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <x-gestion.campo etiqueta="Unidad actual" nombre="unidadActual" class="sm:w-56">
                    <x-gestion.desplegable id="unidadActual" etiqueta="Unidad actual" wire:model.live="unidadActual" :seleccionado="$unidadActual" :opciones="$opcionesUnidad" />
                </x-gestion.campo>
                @if ($archivadas !== [])
                    <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                        <input type="checkbox" role="switch" wire:model.live="verArchivadas" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                        Ver unidades archivadas ({{ implode(', ', $archivadas) }})
                    </label>
                @endif
            </div>
            @if ($sinUnidad > 0)
                <p role="note" class="flex items-start gap-2 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
                    <span>{{ $sinUnidad === 1 ? 'Hay 1 pregunta sin unidad' : "Hay {$sinUnidad} preguntas sin unidad" }} (o con una que el curso ya no tiene). Se listan al final, en "Sin unidad": edítalas para asignarles una.</span>
                </p>
            @endif
        </div>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                <x-gestion.control id="busqueda" type="search" icono="lupa" wire:model.live.debounce.300ms="busqueda" placeholder="Texto de la pregunta" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Tema" nombre="filtroTema">
                <x-gestion.control tipo="select" id="filtroTema" wire:model.live="filtroTema">
                    <option value="">Todos los temas</option>
                    @foreach ($temas as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Tipo" nombre="filtroTipo">
                <x-gestion.control tipo="select" id="filtroTipo" wire:model.live="filtroTipo">
                    <option value="">Todos los tipos</option>
                    @foreach ($tipos as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
        </div>

        {{-- Contador y acciones en lote. --}}
        <div class="flex flex-wrap items-center justify-between gap-3 text-sm">
            <p class="text-slate-600 dark:text-slate-300" aria-live="polite">
                @if ($preguntas->total() === $totalVisible)
                    <span class="font-semibold text-texto dark:text-slate-100">{{ $totalVisible }}</span> {{ $totalVisible === 1 ? 'pregunta' : 'preguntas' }} {{ $verArchivadas ? 'en la unidad actual y las archivadas' : 'en la unidad actual' }}
                @else
                    <span class="font-semibold text-texto dark:text-slate-100">{{ $preguntas->total() }}</span> de {{ $totalVisible }} preguntas coinciden con los filtros
                @endif
            </p>
            @if ($preguntas->isNotEmpty())
                {{-- "Seleccionar esta página" lo resuelve el servidor con los filtros actuales; los ids de la página
                     van en data-pagina (se actualiza en cada render) solo para pintar la casilla al marcar filas. --}}
                <div class="flex flex-wrap items-center gap-3" x-data>
                    <label class="inline-flex items-center gap-2 text-slate-700 dark:text-slate-300">
                        <input type="checkbox" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"
                            data-pagina="{{ $idsPagina->toJson() }}" @checked($paginaSeleccionada)
                            x-bind:checked="JSON.parse($el.dataset.pagina).every(id => ($wire.seleccionadas ?? []).map(String).includes(id))"
                            wire:click="alternarSeleccionPagina">
                        Seleccionar esta página ({{ $preguntas->count() }})
                    </label>
                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="confirmarEliminarSeleccionadas"
                        x-bind:disabled="($wire.seleccionadas ?? []).length === 0">
                        Eliminar seleccionadas (<span x-text="($wire.seleccionadas ?? []).length">{{ count($seleccionadas) }}</span>)
                    </x-gestion.boton>
                </div>
            @endif
        </div>

        @if ($preguntas->isEmpty())
            <x-gestion.vacio icono="base-datos" :titulo="$totalBanco === 0 ? 'No hay preguntas en el banco' : ($totalVisible === 0 ? 'No hay preguntas en la unidad '.$unidadActual : 'Ninguna pregunta coincide con los filtros')"
                :descripcion="$totalBanco === 0 ? 'Crea preguntas por tema o impórtalas desde una plantilla de Excel para reutilizarlas en tus quizzes.' : ($totalVisible === 0 ? 'Crea o importa preguntas para esta unidad, o cambia la unidad actual.' : 'Prueba con otro tema, tipo o texto de búsqueda.')">
                @if ($totalBanco === 0)
                    <x-gestion.boton variante="secundario" icono="subir" wire:click="abrirImportacion">Importar desde Excel/CSV</x-gestion.boton>
                    <x-gestion.boton icono="mas" wire:click="nueva">Nueva pregunta</x-gestion.boton>
                @endif
            </x-gestion.vacio>
        @else
            <ul class="space-y-3">
                @php $grupoAnterior = null; @endphp
                @foreach ($preguntas as $item)
                    @php
                        $usos = $usosDe($item->pregunta);
                        $grupo = in_array($item->unidad, $unidadesValidas, true) ? $item->unidad : null;
                        $fallo = $fallos[$item->id] ?? null;
                    @endphp
                    {{-- Encabezado de grupo al cambiar de unidad (actual, archivadas, sin unidad). --}}
                    @if ($loop->first || $grupo !== $grupoAnterior)
                        <li class="pt-2 first:pt-0" wire:key="grupo-{{ $grupo ?? 'sin' }}-{{ $item->id }}">
                            <h3 class="text-sm font-semibold text-texto dark:text-slate-100">
                                @if ($grupo === null) Sin unidad <span class="font-normal text-slate-600 dark:text-slate-300">· edítalas para asignarles una</span>
                                @elseif ($grupo === $unidadActual) Unidad {{ $grupo }} <span class="font-normal text-slate-600 dark:text-slate-300">· actual</span>
                                @else Unidad {{ $grupo }} <span class="font-normal text-slate-600 dark:text-slate-300">· archivada</span>
                                @endif
                            </h3>
                        </li>
                        @php $grupoAnterior = $grupo; @endphp
                    @endif
                    <li wire:key="banco-{{ $item->id }}">
                        <x-gestion.tarjeta>
                            <div class="flex gap-3">
                                <input type="checkbox" wire:model="seleccionadas" value="{{ $item->id }}" id="seleccion-banco-{{ $item->id }}"
                                    class="mt-1 rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro" aria-label="Seleccionar la pregunta {{ \Illuminate\Support\Str::limit($item->pregunta, 60) }}">
                                <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2 text-xs">
                                            <span class="rounded-full border border-azul-oscuro/30 px-2 py-0.5 font-semibold text-azul-oscuro dark:border-azul-fondo/40 dark:text-azul-fondo">
                                                <span class="sr-only">Unidad:</span> {{ $item->unidad ?? 'Sin unidad' }}
                                            </span>
                                            <span class="rounded-full bg-azul-oscuro/10 px-2 py-0.5 font-semibold text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo"><span class="sr-only">Tema:</span> {{ $item->tema }}</span>
                                            <span class="rounded-full bg-celeste px-2 py-0.5 text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-300">{{ $tipos[$item->tipo] ?? $item->tipo }}</span>
                                            @if ($item->puntaje !== null)
                                                <span class="text-slate-600 dark:text-slate-300">{{ rtrim(rtrim((string) $item->puntaje, '0'), '.') }} pts</span>
                                            @endif
                                            @if ($item->dificultad)
                                                <span class="text-slate-600 dark:text-slate-300">Dificultad {{ $item->dificultad }}</span>
                                            @endif
                                            <span @class(['inline-flex items-center gap-1', 'font-medium text-azul-oscuro dark:text-azul-fondo' => $usos > 0, 'text-slate-500 dark:text-slate-400' => $usos === 0])>
                                                <x-icono nombre="quiz" class="h-3.5 w-3.5" />
                                                {{ $usos === 0 ? 'Sin usar en quizzes' : 'Usada en '.$usos.' '.($usos === 1 ? 'quiz' : 'quizzes') }}
                                            </span>
                                            <span class="text-slate-600 dark:text-slate-300" title="Respuestas de intentos enviados en los quizzes donde se copió">
                                                {{ \App\Services\Evaluacion\EstadisticaFallosBanco::texto($fallo) }}
                                            </span>
                                        </div>
                                        <p class="whitespace-pre-line font-medium text-texto dark:text-slate-100">{{ $item->pregunta }}</p>
                                        <ul class="space-y-1 text-sm">
                                            @foreach ($item->opciones as $indice => $opcion)
                                                @php $esCorrecta = in_array($indice, array_map('intval', $item->respuestas_correctas ?? []), true); @endphp
                                                <li class="flex items-center gap-2 {{ $esCorrecta ? 'font-medium text-exito-oscuro dark:text-green-300' : 'text-slate-600 dark:text-slate-300' }}">
                                                    <x-icono :nombre="$esCorrecta ? 'check-circulo' : 'x-circulo'" class="h-4 w-4 shrink-0 {{ $esCorrecta ? '' : 'text-slate-300 dark:text-slate-600' }}" />
                                                    <span class="sr-only">{{ $esCorrecta ? 'Correcta:' : 'Incorrecta:' }}</span>
                                                    {{ $opcion }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="flex shrink-0 gap-1">
                                        <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editar({{ $item->id }})">Editar</x-gestion.boton>
                                        <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminar({{ $item->id }})"
                                            wire:confirm="¿Eliminar esta pregunta del banco? Los quizzes que ya la incluyen no cambian.">Eliminar</x-gestion.boton>
                                    </div>
                                </div>
                            </div>
                        </x-gestion.tarjeta>
                    </li>
                @endforeach
            </ul>
            <div>{{ $preguntas->links() }}</div>
        @endif
    </div>

    @if ($mostrarConfirmacionLote)
        <x-gestion.modal titulo="Eliminar preguntas seleccionadas" icono="papelera" cerrar="$set('mostrarConfirmacionLote', false)">
            <p class="text-sm text-slate-700 dark:text-slate-300">
                Se {{ count($seleccionadas) === 1 ? 'eliminará 1 pregunta' : 'eliminarán '.count($seleccionadas).' preguntas' }} del banco.
                Los quizzes que ya las incluyen conservan su copia y no cambian. Esta acción no se puede deshacer.
            </p>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarConfirmacionLote', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="papelera" wire:click="eliminarSeleccionadas">Eliminar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarImportacion)
        <x-gestion.modal titulo="Importar preguntas desde Excel/CSV" icono="subir" cerrar="cerrarImportacion()" ancho="max-w-3xl"
            descripcion="Descarga la plantilla, llénala y súbela. Antes de guardar verás cuántas filas son válidas.">
            @if (! $importado)
                <ol class="space-y-4 text-sm">
                    <li class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <span class="text-slate-700 dark:text-slate-300"><span class="font-semibold">1.</span> Plantilla con encabezados, ejemplos e instrucciones.</span>
                        <x-gestion.boton variante="secundario" tamano="sm" icono="descargar" wire:click="descargarPlantilla">Descargar plantilla (.xlsx)</x-gestion.boton>
                    </li>
                    <li class="space-y-2">
                        <span class="text-slate-700 dark:text-slate-300"><span class="font-semibold">2.</span> Sube el archivo (.xlsx o .csv UTF-8, separador coma o punto y coma; máximo 2 MB y {{ $maxFilas }} preguntas).</span>
                        <x-gestion.campo etiqueta="Archivo" nombre="archivoImportacion">
                            <input type="file" id="archivoImportacion" wire:model="archivoImportacion" accept=".xlsx,.csv"
                                @error('archivoImportacion') aria-invalid="true" @enderror
                                class="block w-full rounded-lg border border-celeste bg-white text-sm text-slate-700 file:mr-3 file:rounded-l-lg file:border-0 file:bg-celeste file:px-3 file:py-2 file:font-semibold file:text-azul-oscuro focus:outline-none focus:ring-2 focus:ring-azul-oscuro dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura dark:text-slate-200">
                        </x-gestion.campo>
                        <p wire:loading wire:target="archivoImportacion" class="text-xs text-slate-600 dark:text-slate-300">Revisando el archivo…</p>
                    </li>
                </ol>
            @endif

            @if ($analisis)
                @php
                    $totalErrores = count($analisis['errores']);
                    $totalDuplicadas = count($analisis['duplicadas']);
                @endphp
                <section aria-labelledby="titulo-analisis" aria-live="polite" class="space-y-3">
                    <h3 id="titulo-analisis" class="text-sm font-semibold text-texto dark:text-slate-100">
                        {{ $importado ? 'Resultado de la importación' : 'Vista previa (aún no se guardó nada)' }}
                    </h3>
                    <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                        <div class="rounded-lg bg-exito-base/15 px-3 py-2">
                            <p class="text-2xl font-bold text-exito-oscuro">{{ $importado ? $importadas : $analisis['validas'] }}</p>
                            <p class="text-xs font-medium text-exito-oscuro">{{ $importado ? 'importadas' : 'válidas para importar' }}</p>
                        </div>
                        <div class="rounded-lg bg-red-50 px-3 py-2 dark:bg-red-950/30">
                            <p class="text-2xl font-bold text-red-700 dark:text-red-300">{{ $totalErrores }}</p>
                            <p class="text-xs font-medium text-red-700 dark:text-red-300">con errores (no se guardan)</p>
                        </div>
                        <div class="rounded-lg bg-celeste px-3 py-2 dark:bg-azul-borde-oscuro">
                            <p class="text-2xl font-bold text-slate-700 dark:text-slate-200">{{ $totalDuplicadas }}</p>
                            <p class="text-xs font-medium text-slate-700 dark:text-slate-200">duplicadas (se omiten)</p>
                        </div>
                    </div>

                    @if (! $importado && $analisis['muestra'] !== [])
                        <div class="overflow-x-auto rounded-lg border border-celeste dark:border-azul-borde-oscuro">
                            <table class="min-w-full text-left text-xs">
                                <caption class="sr-only">Primeras preguntas válidas</caption>
                                <thead class="bg-celeste/60 text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">
                                    <tr><th scope="col" class="px-3 py-2">Fila</th><th scope="col" class="px-3 py-2">Unidad</th><th scope="col" class="px-3 py-2">Tema</th><th scope="col" class="px-3 py-2">Tipo</th><th scope="col" class="px-3 py-2">Enunciado</th></tr>
                                </thead>
                                <tbody class="divide-y divide-celeste dark:divide-azul-borde-oscuro">
                                    @foreach ($analisis['muestra'] as $fila)
                                        <tr>
                                            <td class="px-3 py-2 text-slate-600 dark:text-slate-300">{{ $fila['fila'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $fila['unidad'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $fila['tema'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ $etiquetasTipoCorta[$fila['tipo']] ?? $fila['tipo'] }}</td>
                                            <td class="px-3 py-2 text-slate-700 dark:text-slate-200">{{ \Illuminate\Support\Str::limit($fila['pregunta'], 90) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @if ($analisis['validas'] > count($analisis['muestra']))
                            <p class="text-xs text-slate-600 dark:text-slate-300">y {{ $analisis['validas'] - count($analisis['muestra']) }} más.</p>
                        @endif
                    @endif

                    @if ($totalErrores + $totalDuplicadas > 0)
                        <details class="rounded-lg border border-celeste dark:border-azul-borde-oscuro" @if (! $importado) open @endif>
                            <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700 dark:text-slate-200">Filas que no se importan ({{ $totalErrores + $totalDuplicadas }})</summary>
                            <ul class="max-h-60 divide-y divide-celeste overflow-y-auto text-xs dark:divide-azul-borde-oscuro">
                                @foreach (collect($analisis['errores'])->map(fn ($e) => [...$e, 'clase' => 'error'])->merge(collect($analisis['duplicadas'])->map(fn ($d) => [...$d, 'clase' => 'duplicada']))->sortBy('fila') as $fila)
                                    <li class="flex gap-3 px-3 py-2">
                                        <span class="w-14 shrink-0 font-semibold text-slate-700 dark:text-slate-200">Fila {{ $fila['fila'] }}</span>
                                        <span class="min-w-0">
                                            <span @class(['font-semibold', 'text-red-700 dark:text-red-300' => $fila['clase'] === 'error', 'text-slate-700 dark:text-slate-200' => $fila['clase'] === 'duplicada'])>{{ $fila['clase'] === 'error' ? 'Error:' : 'Omitida:' }}</span>
                                            <span class="text-slate-700 dark:text-slate-200">{{ $fila['motivo'] }}</span>
                                            @if ($fila['enunciado'] !== '')
                                                <span class="block text-slate-500 dark:text-slate-400">{{ $fila['enunciado'] }}</span>
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    @endif
                </section>
            @endif

            <x-slot name="pie">
                @if ($importado)
                    @if ($analisis && count($analisis['errores']) + count($analisis['duplicadas']) > 0)
                        <x-gestion.boton variante="secundario" icono="descargar" wire:click="descargarReporte">Descargar reporte de errores</x-gestion.boton>
                    @endif
                    <x-gestion.boton variante="secundario" icono="subir" wire:click="reiniciarImportacion">Importar otro archivo</x-gestion.boton>
                    <x-gestion.boton icono="check" wire:click="cerrarImportacion">Listo</x-gestion.boton>
                @else
                    <x-gestion.boton variante="secundario" wire:click="cerrarImportacion">Cancelar</x-gestion.boton>
                    @if ($analisis && $analisis['validas'] > 0)
                        <x-gestion.boton icono="check" wire:click="importar" wire:loading.attr="disabled" wire:target="importar">
                            Importar {{ $analisis['validas'] }} {{ $analisis['validas'] === 1 ? 'pregunta' : 'preguntas' }}
                        </x-gestion.boton>
                    @endif
                @endif
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModal)
        <x-gestion.modal :titulo="$preguntaId ? 'Editar pregunta' : 'Nueva pregunta'" cerrar="$set('mostrarModal', false)" ancho="max-w-2xl">
            @if ($preguntaId && $usosEditada > 0)
                <div role="note" class="flex items-start gap-2 rounded-lg border border-amber-300/60 bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                    <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
                    <p>Esta pregunta se usa en {{ $usosEditada }} {{ $usosEditada === 1 ? 'quiz' : 'quizzes' }}. Cada quiz guarda su propia copia: los cambios que hagas aquí solo se aplican a los quizzes en los que la agregues de ahora en adelante.</p>
                </div>
            @endif
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
                <x-gestion.campo etiqueta="Unidad" nombre="unidad" requerido>
                    <x-gestion.desplegable id="unidad" etiqueta="Unidad" wire:model.live="unidad" :seleccionado="$unidad"
                        placeholder="Elige la unidad" :opciones="array_combine($unidadesValidas, $unidadesValidas)" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Tema" nombre="tema" requerido ayuda="Contenido dentro de la unidad.">
                    <x-gestion.control id="tema" wire:model="tema" list="temas-banco" placeholder="Conjuntos" autocomplete="off" />
                    <datalist id="temas-banco">
                        @foreach ($temasModal as $t) <option value="{{ $t }}"></option> @endforeach
                    </datalist>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Dificultad" nombre="dificultad">
                    <x-gestion.control tipo="select" id="dificultad" wire:model="dificultad">
                        <option value="">Sin indicar</option>
                        <option value="baja">Baja</option>
                        <option value="media">Media</option>
                        <option value="alta">Alta</option>
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Puntaje sugerido (opcional)" nombre="puntaje" ayuda="Vacío = 1. Al agregarla a un quiz podrás cambiarlo.">
                    <x-gestion.control id="puntaje" wire:model="puntaje" inputmode="decimal" placeholder="1" />
                </x-gestion.campo>
            </div>
            <x-gestion.campo etiqueta="Pregunta" nombre="pregunta" requerido>
                <x-gestion.control tipo="textarea" id="pregunta" wire:model="pregunta" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Tipo" nombre="tipo" requerido>
                <x-gestion.control tipo="select" id="tipo" wire:model.live="tipo">
                    @foreach ($tipos as $valor => $etiqueta)
                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-stone-700 dark:text-stone-300">
                    Opciones <span class="font-normal text-stone-500">- marca {{ $tipo === 'opcion_multiple' ? 'todas las correctas' : 'la correcta' }}</span>
                </legend>
                @foreach ($opciones as $indice => $opcion)
                    <div class="flex items-center gap-2" wire:key="opcion-banco-{{ $indice }}">
                        @if ($tipo === 'opcion_multiple')
                            <input type="checkbox" wire:model="correctas" value="{{ $indice }}" aria-label="Opción {{ $indice + 1 }} correcta"
                                class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                        @else
                            <input type="radio" wire:model="correctas.0" value="{{ $indice }}" aria-label="Opción {{ $indice + 1 }} correcta"
                                class="border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                        @endif
                        <x-gestion.control wire:model="opciones.{{ $indice }}" :disabled="$tipo === 'verdadero_falso'" placeholder="Opción {{ $indice + 1 }}" />
                        @if ($tipo !== 'verdadero_falso' && count($opciones) > 2)
                            <button type="button" wire:click="quitarOpcion({{ $indice }})" class="text-stone-400 hover:text-red-600" aria-label="Quitar opción {{ $indice + 1 }}">
                                <x-icono nombre="cerrar" class="h-4 w-4" />
                            </button>
                        @endif
                    </div>
                @endforeach
                @error('opciones.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @error('correctas') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                @if ($tipo !== 'verdadero_falso' && count($opciones) < 8)
                    <x-gestion.boton variante="fantasma" tamano="sm" icono="mas" wire:click="agregarOpcion">Agregar opción</x-gestion.boton>
                @endif
            </fieldset>

            <x-gestion.campo etiqueta="Retroalimentación" nombre="explicacion" ayuda="Se muestra al estudiante al ver su resultado, si el quiz lo permite.">
                <x-gestion.control tipo="textarea" id="explicacion" rows="2" wire:model="explicacion" />
            </x-gestion.campo>

            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModal', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton icono="check" wire:click="guardar">Guardar pregunta</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
