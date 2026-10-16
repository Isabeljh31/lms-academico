<div class="py-8">
    <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Banco de preguntas" icono="base-datos"
            descripcion="Preguntas reutilizables de {{ $curso->nombre }}, organizadas por tema. Puedes agregarlas a cualquier quiz del curso.">
            <x-gestion.boton variante="secundario" icono="quiz" :href="route('cursos.quizzes.index', $curso)">Quizzes</x-gestion.boton>
            <x-gestion.boton icono="mas" wire:click="nueva">Nueva pregunta</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Tema" nombre="filtroTema">
                <x-gestion.control tipo="select" id="filtroTema" wire:model.live="filtroTema">
                    <option value="">Todos los temas</option>
                    @foreach ($temas as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                <x-gestion.control id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Texto de la pregunta" />
            </x-gestion.campo>
        </div>

        @if ($preguntas->isEmpty())
            <x-gestion.vacio icono="base-datos" titulo="No hay preguntas en el banco"
                descripcion="Crea preguntas por tema para reutilizarlas en tus quizzes.">
                <x-gestion.boton icono="mas" wire:click="nueva">Nueva pregunta</x-gestion.boton>
            </x-gestion.vacio>
        @else
            <div class="space-y-3">
                @foreach ($preguntas as $item)
                    <x-gestion.tarjeta wire:key="banco-{{ $item->id }}">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="rounded-full bg-azul-oscuro/10 px-2 py-0.5 font-semibold text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo">{{ $item->tema }}</span>
                                    <span class="rounded-full bg-celeste px-2 py-0.5 text-slate-600 dark:bg-azul-borde-oscuro dark:text-slate-300">{{ $tipos[$item->tipo] ?? $item->tipo }}</span>
                                    @if ($item->dificultad)
                                        <span class="text-stone-500">Dificultad {{ $item->dificultad }}</span>
                                    @endif
                                </div>
                                <p class="font-medium text-stone-800 dark:text-stone-100">{{ $item->pregunta }}</p>
                                <ul class="space-y-1 text-sm">
                                    @foreach ($item->opciones as $indice => $opcion)
                                        @php $esCorrecta = in_array($indice, array_map('intval', $item->respuestas_correctas ?? []), true); @endphp
                                        <li class="flex items-center gap-2 {{ $esCorrecta ? 'font-medium text-exito-oscuro dark:text-green-300' : 'text-stone-600 dark:text-stone-300' }}">
                                            <x-icono :nombre="$esCorrecta ? 'check-circulo' : 'x-circulo'" class="h-4 w-4 shrink-0 {{ $esCorrecta ? '' : 'text-stone-300 dark:text-stone-600' }}" />
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
                    </x-gestion.tarjeta>
                @endforeach
            </div>
            <div>{{ $preguntas->links() }}</div>
        @endif
    </div>

    @if ($mostrarModal)
        <x-gestion.modal :titulo="$preguntaId ? 'Editar pregunta' : 'Nueva pregunta'" cerrar="$set('mostrarModal', false)" ancho="max-w-2xl">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <x-gestion.campo etiqueta="Tema" nombre="tema" requerido class="sm:col-span-2">
                    <x-gestion.control id="tema" wire:model="tema" list="temas-banco" placeholder="Unidad 1: conjuntos" />
                    <datalist id="temas-banco">
                        @foreach ($temas as $t) <option value="{{ $t }}"></option> @endforeach
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
