<div class="py-8">
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

        <form wire:submit="guardar" class="space-y-6">
            <x-gestion.tarjeta titulo="Datos del quiz">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Tema" nombre="tema" requerido>
                        <x-gestion.control id="tema" wire:model="tema" />
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
                    <div class="space-y-2 pt-6">
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model="aleatorizar" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"> Orden aleatorio
                        </label>
                        <label class="flex items-center gap-2 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model="mostrar_retroalimentacion" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro"> Mostrar retroalimentación
                        </label>
                    </div>
                    <x-gestion.campo etiqueta="Disponible desde" nombre="fecha_inicio">
                        <x-gestion.control id="fecha_inicio" type="datetime-local" wire:model="fecha_inicio" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha límite" nombre="fecha_limite">
                        <x-gestion.control id="fecha_limite" type="datetime-local" wire:model="fecha_limite" />
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

            <div class="space-y-4">
                @foreach ($preguntas as $i => $pregunta)
                    <x-gestion.tarjeta wire:key="pregunta-{{ $i }}" :titulo="'Pregunta '.($i + 1)">
                        @unless ($this->estructuraBloqueada)
                            <x-slot name="acciones">
                                @if (count($preguntas) > 1)
                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="papelera" wire:click="eliminarPregunta({{ $i }})"
                                        wire:confirm="¿Quitar la pregunta {{ $i + 1 }}?">Quitar</x-gestion.boton>
                                @endif
                            </x-slot>
                        @endunless
                        <div class="space-y-4">
                            <x-gestion.campo etiqueta="Enunciado" nombre="preguntas.{{ $i }}.enunciado" requerido>
                                <x-gestion.control tipo="textarea" rows="2" wire:model="preguntas.{{ $i }}.enunciado" />
                            </x-gestion.campo>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <x-gestion.campo etiqueta="Tipo" nombre="preguntas.{{ $i }}.tipo" requerido>
                                    <x-gestion.control tipo="select" wire:model.live="preguntas.{{ $i }}.tipo" :disabled="$this->estructuraBloqueada">
                                        @foreach ($tipos as $valor => $etiqueta)
                                            <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                        @endforeach
                                    </x-gestion.control>
                                </x-gestion.campo>
                                <x-gestion.campo etiqueta="Puntaje" nombre="preguntas.{{ $i }}.puntaje" requerido>
                                    <x-gestion.control type="number" step="0.5" min="0.5" max="100" wire:model="preguntas.{{ $i }}.puntaje" />
                                </x-gestion.campo>
                            </div>

                            @php $multiple = $pregunta['tipo'] === 'opcion_multiple'; @endphp
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
                                <x-gestion.control wire:model="preguntas.{{ $i }}.retroalimentacion" placeholder="Explicación que verá el estudiante al revisar su resultado" />
                            </x-gestion.campo>
                        </div>
                    </x-gestion.tarjeta>
                @endforeach
                @error('preguntas') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

                @unless ($this->estructuraBloqueada)
                    <div class="flex flex-col gap-3 rounded-xl border border-dashed border-celeste p-4 sm:flex-row sm:items-end dark:border-azul-borde-oscuro">
                        <x-gestion.boton variante="secundario" icono="mas" wire:click="agregarPregunta">Agregar pregunta</x-gestion.boton>
                        @if ($temasBanco->isNotEmpty())
                            <div class="flex-1 space-y-2">
                                <x-gestion.campo etiqueta="O agrega desde el banco de preguntas" nombre="temaBanco">
                                    <x-gestion.control tipo="select" id="temaBanco" wire:model.live="temaBanco">
                                        <option value="">Selecciona un tema</option>
                                        @foreach ($temasBanco as $t)
                                            <option value="{{ $t }}">{{ $t }}</option>
                                        @endforeach
                                    </x-gestion.control>
                                </x-gestion.campo>
                                @if ($preguntasBanco->isNotEmpty())
                                    <div class="max-h-48 space-y-1 overflow-y-auto rounded-lg border border-celeste p-2 dark:border-azul-borde-oscuro">
                                        @foreach ($preguntasBanco as $item)
                                            <label class="flex items-start gap-2 text-sm text-stone-700 dark:text-stone-300" wire:key="banco-{{ $item->id }}">
                                                <input type="checkbox" wire:model="seleccionBanco" value="{{ $item->id }}" class="mt-0.5 rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                                {{ $item->pregunta }}
                                            </label>
                                        @endforeach
                                    </div>
                                    <x-gestion.boton variante="secundario" tamano="sm" icono="base-datos" wire:click="agregarDesdeBanco">Agregar seleccionadas</x-gestion.boton>
                                @endif
                            </div>
                        @endif
                    </div>
                @endunless
            </div>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-gestion.boton variante="secundario" :href="route('cursos.quizzes.index', $curso)">Cancelar</x-gestion.boton>
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
