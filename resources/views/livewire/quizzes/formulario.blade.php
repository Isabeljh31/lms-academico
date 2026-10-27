{{-- relative: contiene los sr-only (position: absolute) de las opciones; sin esto se posicionaban respecto de <body> y estiraban la página. --}}
<div class="relative py-8">
    <div class="mx-auto max-w-4xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$quiz ? 'Editar quiz' : 'Nuevo quiz'" icono="quiz" :descripcion="$curso->nombre" />

        <x-gestion.alertas />

        @if ($this->estructuraBloqueada)
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-amber-300/60 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <x-icono nombre="alerta" class="h-5 w-5 shrink-0" />
                <p>
                    {{ $this->estudiantesAfectados }} estudiante(s) ya rindieron este quiz. Puedes corregir enunciados, opciones, respuestas correctas y puntajes,
                    pero no agregar ni quitar preguntas u opciones. Si cambias la corrección, se te preguntará si recalcular sus notas.
                </p>
            </div>
        @endif

        {{-- campos-sin-flechas (app.css): sin flechas de spinner; la rueda del mouse no cambia los números (se quita el foco y la página se desplaza).
             unidadGuardada(): unidad actual elegida en el Banco (localStorage, misma clave por curso). --}}
        <form wire:submit="guardar" class="campos-sin-flechas space-y-6"
            x-data="{
                unidadGuardada() {
                    try { return localStorage.getItem(@js('lms.banco-preguntas.unidad-actual.'.$curso->id)); } catch (e) { return null; }
                },
            }"
            x-on:wheel.passive="if ($event.target.matches('input[type=number]') && document.activeElement === $event.target) $event.target.blur()">
            <x-gestion.tarjeta titulo="Datos del quiz">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Tema" nombre="tema" requerido>
                        <x-gestion.control id="tema" wire:model="tema" autocomplete="off" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Semana" nombre="modulo_id" requerido>
                        <x-gestion.control tipo="select" id="modulo_id" wire:model="modulo_id">
                            <option value="">Selecciona la semana (o prácticas de campo)</option>
                            @foreach ($modulos as $modulo)
                                <option value="{{ $modulo->id }}">{{ $modulo->nombreCompleto() }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Configuración" descripcion="Cuenta el mejor intento del estudiante. La nota es de práctica y no entra en la nota final ponderada.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <x-gestion.campo etiqueta="Intentos permitidos" nombre="intentos" requerido>
                        <x-gestion.control id="intentos" type="number" min="1" max="10" wire:model="intentos" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Tiempo límite (minutos)" nombre="tiempo_limite_min" ayuda="Vacío = sin límite.">
                        <x-gestion.control id="tiempo_limite_min" type="number" min="1" max="600" wire:model="tiempo_limite_min" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Puntaje total del quiz" nombre="puntaje_total" requerido ayuda="Escala de 0 a 20. Se reparte entre las preguntas.">
                        <x-gestion.control id="puntaje_total" type="number" step="0.01" min="0.01" max="20" wire:model.live.debounce.400ms="puntaje_total" />
                    </x-gestion.campo>
                    <div class="space-y-2 pt-6">
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model="aleatorizar" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"> Orden aleatorio
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model="mostrar_retroalimentacion" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"> Mostrar retroalimentación
                        </label>
                    </div>
                    <x-gestion.campo etiqueta="Disponible desde" nombre="fecha_inicio">
                        <x-gestion.fecha-hora id="fecha_inicio" wire:model="fecha_inicio" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha límite" nombre="fecha_limite">
                        <x-gestion.fecha-hora id="fecha_limite" wire:model="fecha_limite" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Estado" nombre="estado_contenido" requerido>
                        <x-gestion.control tipo="select" id="estado_contenido" wire:model.live="estado_contenido">
                            <option value="borrador">Borrador</option>
                            <option value="publicado">Publicado</option>
                            <option value="programado">Programado</option>
                        </x-gestion.control>
                    </x-gestion.campo>
                    @if ($estado_contenido === 'programado')
                        <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="publicar_en" requerido>
                            <x-gestion.control id="publicar_en" type="datetime-local" wire:model="publicar_en" />
                        </x-gestion.campo>
                    @endif
                </div>
            </x-gestion.tarjeta>

            {{-- Preguntas: las copiadas del banco se ven compactas y de solo lectura hasta pulsar "Editar". --}}
            @php
                $bancoVacio = ! $hayBanco;
                $hayPreguntas = count($preguntas) > 0;
                $puedeAgregar = ! $this->estructuraBloqueada;
                $formato = fn (float $n) => rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
                $mensajeBancoVacio = 'Tu banco está vacío. Crea o importa preguntas en la pestaña Banco de preguntas.';
            @endphp
            <section aria-labelledby="titulo-preguntas" class="space-y-4">
                <h2 id="titulo-preguntas" class="text-base font-semibold text-texto dark:text-slate-100">
                    Preguntas <span class="ml-1 rounded-full bg-celeste px-2 py-0.5 text-xs font-semibold text-slate-700 dark:bg-azul-borde-oscuro dark:text-slate-200">{{ count($preguntas) }}</span>
                </h2>

                {{-- Estado vacío: las dos formas de empezar. --}}
                @if (! $hayPreguntas && ! $mostrarBanco && $puedeAgregar)
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div @class(['flex flex-col gap-3 rounded-xl border p-5',
                                'border-azul-oscuro bg-azul-oscuro/5 ring-1 ring-azul-oscuro/20 dark:border-azul-fondo dark:bg-azul-oscuro/15' => ! $bancoVacio,
                                'border-dashed border-celeste bg-white dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura' => $bancoVacio])>
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-azul-oscuro/15 text-azul-oscuro dark:bg-azul-borde-oscuro dark:text-azul-fondo">
                                <x-icono nombre="base-datos" stroke-width="2" class="h-6 w-6" />
                            </span>
                            <div>
                                <p class="font-semibold text-texto dark:text-slate-100">Agregar desde el banco de preguntas</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                    {{ $bancoVacio ? $mensajeBancoVacio : 'Elige una unidad y un tema, y marca las preguntas que quieras. Se copian al quiz.' }}
                                </p>
                            </div>
                            @if ($bancoVacio)
                                <x-gestion.boton variante="secundario" tamano="sm" icono="base-datos" class="self-start" :href="route('cursos.actividades', [$curso, 'pestana' => 'banco'])">Ir al Banco de preguntas</x-gestion.boton>
                            @else
                                <x-gestion.boton icono="base-datos" class="self-start" x-on:click="$wire.abrirBanco(unidadGuardada())">Elegir del banco</x-gestion.boton>
                            @endif
                        </div>
                        <div class="flex flex-col gap-3 rounded-xl border border-celeste bg-white p-5 dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-cielo-claro text-azul-fondo dark:bg-azul-borde-oscuro dark:text-cielo">
                                <x-icono nombre="lapiz" stroke-width="2" class="h-6 w-6" />
                            </span>
                            <div>
                                <p class="font-semibold text-texto dark:text-slate-100">Escribir una pregunta nueva</p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Enunciado, tipo, opciones y la respuesta correcta.</p>
                            </div>
                            <x-gestion.boton :variante="$bancoVacio ? 'primario' : 'secundario'" icono="mas" class="self-start" wire:click="agregarPregunta">Escribir una pregunta</x-gestion.boton>
                        </div>
                    </div>
                @endif

                @foreach ($preguntas as $i => $pregunta)
                    @php
                        $delBanco = ($pregunta['banco_tema'] ?? null) !== null;
                        $compacta = $delBanco && ! ($pregunta['editando'] ?? false);
                        $editada = $estaEditada($pregunta);
                        $multiple = $pregunta['tipo'] === 'opcion_multiple';
                        $correctas = $multiple ? array_map('intval', (array) $pregunta['respuestas_correctas']) : [(int) $pregunta['respuesta_correcta']];
                    @endphp
                    <x-gestion.tarjeta wire:key="pregunta-{{ $i }}" :titulo="'Pregunta '.($i + 1)">
                        <x-slot name="acciones">
                            <div class="flex flex-wrap gap-1">
                                @if ($compacta && ! $this->estructuraBloqueada)
                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="editarPreguntaDelBanco({{ $i }})" aria-label="Editar la pregunta {{ $i + 1 }}">Editar</x-gestion.boton>
                                @endif
                                @if ($puedeAgregar)
                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarPregunta({{ $i }})"
                                        wire:confirm="¿Quitar la pregunta {{ $i + 1 }}?" aria-label="Quitar la pregunta {{ $i + 1 }}">Quitar</x-gestion.boton>
                                @endif
                            </div>
                        </x-slot>

                        @if ($delBanco)
                            <div class="mb-3 flex flex-wrap items-center gap-2 text-xs">
                                <span class="inline-flex items-center gap-1 rounded-full bg-azul-oscuro/10 px-2 py-0.5 font-semibold text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo">
                                    <x-icono nombre="base-datos" class="h-3.5 w-3.5" /> Del banco · {{ filled($pregunta['banco_unidad'] ?? null) ? $pregunta['banco_unidad'].' · ' : '' }}{{ $pregunta['banco_tema'] }}
                                </span>
                                @if ($editada)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 font-semibold text-amber-800 dark:bg-amber-950/40 dark:text-amber-300">Editada</span>
                                @endif
                            </div>
                        @endif

                        @if ($compacta)
                            {{-- Solo lectura: el puntaje sí se decide aquí, en el quiz. --}}
                            <div class="space-y-3">
                                <p class="whitespace-pre-line font-medium text-texto dark:text-slate-100">{{ $pregunta['enunciado'] }}</p>
                                <ul class="space-y-1 text-sm">
                                    @foreach ($pregunta['opciones'] as $j => $opcion)
                                        @php $esCorrecta = in_array($j, $correctas, true); @endphp
                                        <li class="flex items-center gap-2 {{ $esCorrecta ? 'font-medium text-exito-oscuro dark:text-green-300' : 'text-slate-600 dark:text-slate-300' }}">
                                            <x-icono :nombre="$esCorrecta ? 'check-circulo' : 'x-circulo'" class="h-4 w-4 shrink-0 {{ $esCorrecta ? '' : 'text-slate-300 dark:text-slate-600' }}" />
                                            <span class="sr-only">{{ $esCorrecta ? 'Correcta:' : 'Incorrecta:' }}</span>
                                            {{ $opcion }}
                                        </li>
                                    @endforeach
                                </ul>
                                @if (($pregunta['retroalimentacion'] ?? '') !== '')
                                    <p class="text-xs text-slate-600 dark:text-slate-300"><span class="font-semibold">Retroalimentación:</span> {{ $pregunta['retroalimentacion'] }}</p>
                                @endif
                                <div class="flex flex-wrap items-end gap-4 border-t border-celeste pt-3 dark:border-azul-borde-oscuro">
                                    <p class="text-sm text-slate-600 dark:text-slate-300"><span class="font-medium text-slate-700 dark:text-slate-200">Tipo:</span> {{ $tipos[$pregunta['tipo']] ?? $pregunta['tipo'] }}</p>
                                    <x-gestion.campo etiqueta="Puntaje" nombre="preguntas.{{ $i }}.puntaje" class="w-32">
                                        <x-gestion.control id="preguntas.{{ $i }}.puntaje" type="number" step="0.01" min="0.5" max="100" wire:model.live.debounce.400ms="preguntas.{{ $i }}.puntaje" />
                                    </x-gestion.campo>
                                </div>
                                @if ($errors->has('preguntas.'.$i.'.*'))
                                    <p class="text-xs font-medium text-red-600 dark:text-red-400">
                                        {{ collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'preguntas.'.$i.'.') && $k !== 'preguntas.'.$i.'.puntaje')->flatten()->first() }}
                                    </p>
                                @endif
                            </div>
                        @else
                            <div class="space-y-4">
                                <x-gestion.campo etiqueta="Enunciado" nombre="preguntas.{{ $i }}.enunciado" requerido>
                                    <x-gestion.control tipo="textarea" rows="2" id="preguntas.{{ $i }}.enunciado" autocomplete="off" wire:model="preguntas.{{ $i }}.enunciado" />
                                </x-gestion.campo>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <x-gestion.campo etiqueta="Tipo" nombre="preguntas.{{ $i }}.tipo" requerido>
                                        <x-gestion.control tipo="select" id="preguntas.{{ $i }}.tipo" wire:model.live="preguntas.{{ $i }}.tipo" :disabled="$this->estructuraBloqueada">
                                            @foreach ($tipos as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </x-gestion.control>
                                    </x-gestion.campo>
                                    <x-gestion.campo etiqueta="Puntaje" nombre="preguntas.{{ $i }}.puntaje" requerido>
                                        <x-gestion.control id="preguntas.{{ $i }}.puntaje" type="number" step="0.01" min="0.5" max="100" wire:model.live.debounce.400ms="preguntas.{{ $i }}.puntaje" />
                                    </x-gestion.campo>
                                </div>

                                <fieldset class="space-y-2">
                                    <legend class="text-sm font-medium text-stone-700 dark:text-stone-300">
                                        Opciones <span class="font-normal text-stone-500">- marca {{ $multiple ? 'todas las correctas' : 'la correcta' }}</span>
                                    </legend>
                                    @foreach ($pregunta['opciones'] as $j => $opcion)
                                        <div class="flex items-center gap-2" wire:key="pregunta-{{ $i }}-opcion-{{ $j }}">
                                            @if ($multiple)
                                                <input type="checkbox" wire:model="preguntas.{{ $i }}.respuestas_correctas" value="{{ $j }}" aria-label="Opción {{ $j + 1 }} correcta"
                                                    class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                            @else
                                                <input type="radio" wire:model="preguntas.{{ $i }}.respuesta_correcta" value="{{ $j }}" aria-label="Opción {{ $j + 1 }} correcta"
                                                    class="border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                            @endif
                                            <x-gestion.control wire:model="preguntas.{{ $i }}.opciones.{{ $j }}" placeholder="Opción {{ $j + 1 }}" :disabled="$pregunta['tipo'] === 'verdadero_falso'" />
                                            @if (! $this->estructuraBloqueada && $pregunta['tipo'] !== 'verdadero_falso' && count($pregunta['opciones']) > 2)
                                                <button type="button" wire:click="eliminarOpcion({{ $i }}, {{ $j }})" class="text-stone-400 hover:text-red-600" aria-label="Quitar opción {{ $j + 1 }}">
                                                    <x-icono nombre="cerrar" class="h-4 w-4" />
                                                </button>
                                            @endif
                                        </div>
                                    @endforeach
                                    @error('preguntas.'.$i.'.opciones.*') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @error('preguntas.'.$i.'.respuestas_correctas') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @error('preguntas.'.$i.'.respuesta_correcta') <p class="text-xs text-red-600">{{ $message }}</p> @enderror
                                    @if (! $this->estructuraBloqueada && $pregunta['tipo'] !== 'verdadero_falso' && count($pregunta['opciones']) < 8)
                                        <x-gestion.boton variante="fantasma" tamano="sm" icono="mas" wire:click="agregarOpcion({{ $i }})">Agregar opción</x-gestion.boton>
                                    @endif
                                </fieldset>

                                <x-gestion.campo etiqueta="Retroalimentación" nombre="preguntas.{{ $i }}.retroalimentacion">
                                    <x-gestion.control id="preguntas.{{ $i }}.retroalimentacion" wire:model="preguntas.{{ $i }}.retroalimentacion" placeholder="Explicación que verá el estudiante al revisar su resultado" />
                                </x-gestion.campo>
                                @if ($delBanco)
                                    <p class="text-xs text-slate-600 dark:text-slate-300">Los cambios solo se aplican a este quiz: la pregunta del banco no cambia.</p>
                                @endif
                            </div>
                        @endif
                    </x-gestion.tarjeta>
                @endforeach
                @error('preguntas')
                    <p role="alert" class="flex items-center gap-2 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm font-medium text-red-700 dark:border-red-800 dark:bg-red-950/40 dark:text-red-300">
                        <x-icono nombre="alerta" class="h-4 w-4 shrink-0" /> {{ $message }}
                    </p>
                @enderror

                {{-- Panel del banco: tema, buscador, seleccionar todas y agregar. --}}
                @if ($mostrarBanco)
                    @php $idsVisibles = $preguntasBanco->pluck('id')->map(fn ($id) => (string) $id)->values(); @endphp
                    <x-gestion.tarjeta titulo="Agregar desde el banco de preguntas" descripcion="Las preguntas se copian al quiz: editarlas aquí no cambia el banco.">
                        <x-slot name="acciones">
                            <x-gestion.boton variante="fantasma" tamano="sm" icono="cerrar" wire:click="cerrarBanco">Cerrar</x-gestion.boton>
                        </x-slot>
                        @if ($bancoVacio)
                            <p class="text-sm text-slate-600 dark:text-slate-300">{{ $mensajeBancoVacio }}</p>
                        @else
                            <div class="space-y-3">
                                {{-- Dos pasos: primero la unidad (por defecto la actual) y luego un tema de esa unidad. --}}
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                                    <x-gestion.campo etiqueta="1. Unidad" nombre="unidadBanco">
                                        <x-gestion.control tipo="select" id="unidadBanco" wire:model.live="unidadBanco">
                                            <option value="">Selecciona una unidad</option>
                                            @foreach ($unidadesBanco as $valor => $etiqueta)
                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                            @endforeach
                                        </x-gestion.control>
                                    </x-gestion.campo>
                                    @if ($unidadBanco !== '')
                                        <x-gestion.campo etiqueta="2. Tema" nombre="temaBanco">
                                            <x-gestion.control tipo="select" id="temaBanco" wire:model.live="temaBanco">
                                                <option value="">{{ $temasBanco->isEmpty() ? 'Esta unidad no tiene preguntas' : 'Selecciona un tema' }}</option>
                                                @foreach ($temasBanco as $t)
                                                    <option value="{{ $t }}">{{ $t }}</option>
                                                @endforeach
                                            </x-gestion.control>
                                        </x-gestion.campo>
                                    @endif
                                    @if (($unidadBanco !== '' && $temaBanco !== '') || $incluirAnteriores)
                                        <x-gestion.campo etiqueta="Buscar" nombre="busquedaBanco">
                                            <x-gestion.control id="busquedaBanco" type="search" icono="lupa" wire:model.live.debounce.300ms="busquedaBanco" placeholder="Texto de la pregunta" autocomplete="off" />
                                        </x-gestion.campo>
                                    @endif
                                </div>

                                @if ($temaBanco !== '')
                                    @if ($preguntasBanco->isEmpty())
                                        <p class="text-sm text-slate-600 dark:text-slate-300">Ninguna pregunta de este tema coincide con la búsqueda.</p>
                                    @else
                                        {{-- Los ids visibles van en data-visibles (se actualiza en cada render): un valor en x-data
                                             quedaría congelado con el primer tema y "Seleccionar todas" marcaría preguntas que ya no se ven. --}}
                                        <div x-data class="space-y-2" data-visibles="{{ $idsVisibles->toJson() }}">
                                            <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                                <input type="checkbox" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"
                                                    x-bind:checked="(v => v.length > 0 && v.every(id => ($wire.seleccionBanco ?? []).map(String).includes(id)))(JSON.parse($root.dataset.visibles))"
                                                    x-on:change="(v => $wire.seleccionBanco = $event.target.checked
                                                        ? [...new Set([...($wire.seleccionBanco ?? []).map(String), ...v])]
                                                        : ($wire.seleccionBanco ?? []).map(String).filter(id => ! v.includes(id)))(JSON.parse($root.dataset.visibles))">
                                                Seleccionar todas ({{ $preguntasBanco->count() }})
                                            </label>
                                            <ul class="max-h-72 divide-y divide-celeste overflow-y-auto rounded-lg border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                                @foreach ($preguntasBanco as $item)
                                                    <li wire:key="banco-{{ $item->id }}">
                                                        <label class="flex cursor-pointer items-start gap-3 px-3 py-2 text-sm hover:bg-celeste/50 dark:hover:bg-azul-borde-oscuro/50">
                                                            <input type="checkbox" wire:model="seleccionBanco" value="{{ $item->id }}" class="mt-0.5 rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                                            <span class="min-w-0">
                                                                <span class="block text-texto dark:text-slate-100">{{ $item->pregunta }}</span>
                                                                <span class="text-xs text-slate-600 dark:text-slate-300">{{ $tipos[$item->tipo] ?? $item->tipo }} · {{ count($item->opciones ?? []) }} opciones</span>
                                                            </span>
                                                        </label>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endif

                                {{-- Unidades archivadas (anteriores a la actual): repaso priorizando las preguntas más falladas. --}}
                                @if ($archivadasBanco !== [])
                                    <div class="space-y-3 border-t border-celeste pt-3 dark:border-azul-borde-oscuro">
                                        <label class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                            <input type="checkbox" role="switch" wire:model.live="incluirAnteriores" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                            Incluir unidades anteriores ({{ implode(', ', $archivadasBanco) }})
                                        </label>

                                        @if ($incluirAnteriores)
                                            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                                                <x-gestion.campo etiqueta="Ordenar" nombre="ordenAnteriores" class="sm:w-56">
                                                    <x-gestion.control tipo="select" id="ordenAnteriores" wire:model.live="ordenAnteriores">
                                                        <option value="fallos">Más falladas primero</option>
                                                        <option value="tema">Por unidad y tema</option>
                                                    </x-gestion.control>
                                                </x-gestion.campo>
                                                <div class="flex items-end gap-2">
                                                    <x-gestion.campo etiqueta="Cantidad" nombre="cantidadMasFalladas" class="w-20">
                                                        <x-gestion.control id="cantidadMasFalladas" type="number" min="1" max="50" wire:model.live.debounce.400ms="cantidadMasFalladas" />
                                                    </x-gestion.campo>
                                                    <x-gestion.boton variante="secundario" icono="check" wire:click="seleccionarMasFalladas">
                                                        Seleccionar las {{ max(1, (int) $cantidadMasFalladas) }} más falladas
                                                    </x-gestion.boton>
                                                </div>
                                            </div>

                                            @if ($preguntasArchivadas->isEmpty())
                                                <p class="text-sm text-slate-600 dark:text-slate-300">No hay preguntas de unidades anteriores{{ trim($busquedaBanco) !== '' ? ' que coincidan con la búsqueda' : '' }}.</p>
                                            @else
                                                <ul class="max-h-80 divide-y divide-celeste overflow-y-auto rounded-lg border border-celeste dark:divide-azul-borde-oscuro dark:border-azul-borde-oscuro">
                                                    @foreach ($preguntasArchivadas as $item)
                                                        <li wire:key="banco-archivada-{{ $item->id }}">
                                                            <label class="flex cursor-pointer items-start gap-3 px-3 py-2 text-sm hover:bg-celeste/50 dark:hover:bg-azul-borde-oscuro/50">
                                                                <input type="checkbox" wire:model="seleccionBanco" value="{{ $item->id }}" class="mt-0.5 rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                                                <span class="min-w-0">
                                                                    <span class="block text-texto dark:text-slate-100">{{ $item->pregunta }}</span>
                                                                    <span class="text-xs text-slate-600 dark:text-slate-300">
                                                                        <span class="font-semibold text-azul-oscuro dark:text-azul-fondo">{{ $item->unidad }}</span> · {{ $item->tema }} ·
                                                                        {{ \App\Services\Evaluacion\EstadisticaFallosBanco::texto($item->fallos) }}
                                                                    </span>
                                                                </span>
                                                            </label>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        @endif
                                    </div>
                                @endif

                                <x-gestion.boton icono="mas" wire:click="agregarDesdeBanco" x-bind:disabled="($wire.seleccionBanco ?? []).length === 0">
                                    Agregar <span x-text="($wire.seleccionBanco ?? []).length">{{ count($seleccionBanco) }}</span> seleccionadas
                                </x-gestion.boton>
                            </div>
                        @endif
                    </x-gestion.tarjeta>
                @endif

                @if ($hayPreguntas)
                    {{-- Suma de puntajes frente al total: solo avisa, no bloquea el guardado. --}}
                    @php
                        $totalQuiz = is_numeric($puntaje_total) ? round((float) $puntaje_total, 2) : null;
                        $sumaCuadra = $totalQuiz !== null && abs($this->sumaPuntajes - $totalQuiz) < 0.005;
                    @endphp
                    <div @class(['flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between',
                            'border-celeste bg-white dark:border-azul-borde-oscuro dark:bg-azul-superficie-oscura' => $sumaCuadra,
                            'border-amber-300/60 bg-amber-50 dark:border-amber-800 dark:bg-amber-950/30' => ! $sumaCuadra])>
                        <p id="suma-puntajes" aria-live="polite" @class(['text-sm font-medium',
                                'text-slate-700 dark:text-slate-200' => $sumaCuadra,
                                'text-amber-800 dark:text-amber-200' => ! $sumaCuadra])>
                            Suma actual: {{ $formato($this->sumaPuntajes) }} / Total: {{ $totalQuiz !== null ? $formato($totalQuiz) : '—' }}
                            @unless ($sumaCuadra)
                                <span class="block text-xs font-normal">Los puntajes no suman el total. Puedes guardar igual o repartirlos en partes iguales.</span>
                            @endunless
                            @if ($sumaCuadra && ! $this->hayPuntajeManual && ! $this->estructuraBloqueada)
                                <span class="block text-xs font-normal text-slate-600 dark:text-slate-300">Se reparte solo al agregar o quitar preguntas. Si cambias un puntaje a mano, se respeta.</span>
                            @endif
                        </p>
                        @if ($this->estructuraBloqueada)
                            <p class="text-xs text-slate-600 dark:text-slate-300">Con intentos registrados no se puede repartir el puntaje automáticamente.</p>
                        @else
                            <x-gestion.boton variante="secundario" tamano="sm" icono="grafico" wire:click="repartirEnPartesIguales" aria-describedby="suma-puntajes">Repartir en partes iguales</x-gestion.boton>
                        @endif
                    </div>

                    {{-- Seguir agregando: las dos acciones siempre a mano. --}}
                    @if ($puedeAgregar && ! $mostrarBanco)
                        <div class="flex flex-col gap-2 rounded-xl border border-dashed border-celeste p-4 sm:flex-row sm:items-center dark:border-azul-borde-oscuro">
                            @if ($bancoVacio)
                                <p class="flex-1 text-sm text-slate-600 dark:text-slate-300">{{ $mensajeBancoVacio }}</p>
                            @else
                                <x-gestion.boton variante="secundario" icono="base-datos" x-on:click="$wire.abrirBanco(unidadGuardada())">Agregar desde el banco de preguntas</x-gestion.boton>
                            @endif
                            <x-gestion.boton variante="secundario" icono="mas" wire:click="agregarPregunta">Escribir una pregunta nueva</x-gestion.boton>
                        </div>
                    @endif
                @endif
            </section>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-gestion.boton variante="secundario" :href="$this->rutaVolver()">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" icono="check">Guardar quiz</x-gestion.boton>
            </div>
        </form>
    </div>

    @if ($mostrarModalRecalculo)
        <x-gestion.modal titulo="¿Recalcular las notas?" cerrar="$set('mostrarModalRecalculo', false)" ancho="max-w-md">
            <div class="flex items-start gap-3 rounded-lg bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-200">
                <x-icono nombre="alerta" class="h-5 w-5 shrink-0 text-amber-600" />
                <p>
                    Cambiaste la corrección de una o más preguntas y {{ $this->estudiantesAfectados }} estudiante(s) ya rindieron este quiz.
                    Si recalculas, sus notas se actualizarán con la nueva corrección, el cambio quedará en la bitácora de auditoría y se les notificará.
                </p>
            </div>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalRecalculo', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="secundario" wire:click="confirmarGuardado(false)">Guardar sin recalcular</x-gestion.boton>
                <x-gestion.boton icono="refrescar" wire:click="confirmarGuardado(true)">Guardar y recalcular</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
