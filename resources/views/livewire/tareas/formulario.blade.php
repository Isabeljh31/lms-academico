<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$tarea ? 'Editar tarea' : 'Nueva '.($tipo['titulo'] ?? 'tarea')" icono="tarea" :descripcion="$curso->nombre" />

        @if ($tipoActividad === 'examen')
            <p class="flex items-start gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Los alumnos suben un archivo como examen. ¿Prefieres preguntas que se califican solas?
                    <a href="{{ route('cursos.quizzes.crear', [$curso, 'desde' => 'actividades', 'tipo' => 'examen']) }}" wire:navigate class="font-semibold text-azul-oscuro underline-offset-2 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-azul-oscuro dark:text-azul-fondo">Crea un quiz calificado</a>.</span>
            </p>
        @elseif ($tipoActividad === 'entrega')
            <p class="flex items-start gap-1.5 text-sm text-slate-600 dark:text-slate-300">
                <x-icono nombre="info" class="mt-0.5 h-4 w-4 shrink-0" />
                <span>Los alumnos solo suben su material (ppt, informes…). No lleva nota ni cuenta para ninguna evaluación.</span>
            </p>
        @endif

        <form wire:submit="guardar" class="space-y-6">
            <x-gestion.tarjeta titulo="Contenido">
                <div class="space-y-4">
                    <x-gestion.campo etiqueta="Título" nombre="titulo" requerido>
                        <x-gestion.control id="titulo" wire:model="titulo" autofocus />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Semana" nombre="modulo_id" requerido>
                        {{-- En las calificadas la semana se envía al momento para sugerir la evaluación. --}}
                        @if ($evaluacion['mostrar'])
                            <x-gestion.desplegable id="modulo_id" etiqueta="Semana" wire:model.live="modulo_id" :seleccionado="$modulo_id"
                                placeholder="Selecciona la semana (o prácticas de campo)"
                                :opciones="$modulos->mapWithKeys(fn ($modulo) => [$modulo->id => $modulo->nombreCompleto()])->all()" />
                        @else
                            <x-gestion.desplegable id="modulo_id" etiqueta="Semana" wire:model="modulo_id" :seleccionado="$modulo_id"
                                placeholder="Selecciona la semana (o prácticas de campo)"
                                :opciones="$modulos->mapWithKeys(fn ($modulo) => [$modulo->id => $modulo->nombreCompleto()])->all()" />
                        @endif
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                        <x-gestion.control tipo="textarea" id="descripcion" rows="3" wire:model="descripcion" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Instrucciones" nombre="instrucciones" ayuda="Pasos, criterios de evaluación o requisitos de la entrega.">
                        <x-gestion.control tipo="textarea" id="instrucciones" rows="4" wire:model="instrucciones" />
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            @if ($evaluacion['mostrar'])
                @include('livewire.cursos._asignar-evaluacion')
            @endif

            @php
                $descripcionPlazos = match (true) {
                    $tipo === null => 'Si esta tarea está asignada a una evaluación (en Actividades), su nota cuenta para esa evaluación. Si no, no cuenta para la nota final.',
                    $tipo['calificada'] => 'La nota (de 0 a 20) la pones al revisar cada entrega.',
                    default => 'No lleva nota: solo fija cuándo pueden subir su material.',
                };
            @endphp
            <x-gestion.tarjeta :titulo="$tipo === null ? 'Plazos y calificación' : 'Plazos'" :descripcion="$descripcionPlazos">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Fecha de inicio" nombre="fecha_inicio" ayuda="Opcional. Antes de esta fecha no se puede entregar.">
                        <x-gestion.fecha-hora id="fecha_inicio" wire:model="fecha_inicio" />
                        @include('livewire.cursos._lectura-fecha', ['campo' => 'fecha_inicio', 'prefijo' => 'Empieza', 'vacio' => 'Sin fecha de inicio: se puede entregar apenas se publique.'])
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha límite" nombre="fecha_entrega" requerido>
                        <x-gestion.fecha-hora id="fecha_entrega" wire:model="fecha_entrega" />
                        @include('livewire.cursos._lectura-fecha', ['campo' => 'fecha_entrega', 'prefijo' => 'Vence', 'vacio' => 'Elige la fecha límite.'])
                    </x-gestion.campo>
                    {{-- Oculto en prácticas y exámenes (y en tareas que ya cuentan para una evaluación): se conserva su valor (1 por defecto). --}}
                    @if ($mostrarPeso)
                        <x-gestion.campo etiqueta="Peso en el resumen del alumno" nombre="peso_nota" requerido
                            ayuda="Solo pesa en el promedio de tareas que el alumno ve en su panel. No cambia la nota de ninguna evaluación.">
                            <x-gestion.control id="peso_nota" type="number" step="0.01" min="0" max="100" wire:model="peso_nota" />
                        </x-gestion.campo>
                    @endif
                    <x-gestion.campo etiqueta="Intentos de entrega" nombre="intentos_permitidos" requerido>
                        <x-gestion.control id="intentos_permitidos" type="number" min="1" max="10" wire:model="intentos_permitidos" />
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Entrega">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-gestion.campo etiqueta="Tipo de entrega" nombre="tipo_entrega" requerido
                            :ayuda="$tipo_entrega === 'grupal' ? 'Sube un integrante por grupo; la nota es para todo el grupo.' : null">
                            <x-gestion.desplegable id="tipo_entrega" etiqueta="Tipo de entrega" wire:model.live="tipo_entrega" :seleccionado="$tipo_entrega"
                                :opciones="['individual' => 'Individual', 'grupal' => 'Grupal (una entrega por grupo)']" />
                        </x-gestion.campo>
                        @if ($tipo_entrega === 'grupal')
                            <x-gestion.campo etiqueta="Grupo" nombre="grupo_asignado_id" ayuda="Deja 'Todos los grupos' para que la entregue cada grupo.">
                                <x-gestion.desplegable id="grupo_asignado_id" etiqueta="Grupo" wire:model="grupo_asignado_id" :seleccionado="$grupo_asignado_id"
                                    placeholder="Todos los grupos"
                                    :opciones="['' => 'Todos los grupos'] + $grupos->pluck('nombre', 'id')->all()" />
                            </x-gestion.campo>
                        @endif
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-stone-700 dark:text-stone-300">Formatos de archivo permitidos</legend>
                        <div class="mt-1 flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs text-stone-500">Sin marcar ninguno se aceptan todos los formatos seguros. Tamaño máximo: 10 MB.</p>
                            {{-- Sin ida al servidor: cambian la selección local, que se envía al guardar. --}}
                            <div class="flex gap-2">
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="check" x-on:click="$wire.formatos_permitidos = @js($extensiones)">Seleccionar todos</x-gestion.boton>
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="cerrar" x-on:click="$wire.formatos_permitidos = []">Quitar todos</x-gestion.boton>
                            </div>
                        </div>
                        <div class="mt-2 grid grid-cols-3 gap-2 sm:grid-cols-6">
                            @foreach ($extensiones as $extension)
                                <label class="flex items-center gap-1.5 text-sm text-stone-700 dark:text-stone-300">
                                    <input type="checkbox" wire:model="formatos_permitidos" value="{{ $extension }}" class="rounded border-celeste text-azul-oscuro focus:ring-azul-oscuro">
                                    .{{ $extension }}
                                </label>
                            @endforeach
                        </div>
                        @error('formatos_permitidos.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Publicación">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Estado" nombre="estado_contenido" requerido ayuda="Borrador y Programado no son visibles para los estudiantes.">
                        <x-gestion.desplegable id="estado_contenido" etiqueta="Estado" wire:model.live="estado_contenido" :seleccionado="$estado_contenido"
                            :opciones="['borrador' => 'Borrador', 'publicado' => 'Publicado', 'programado' => 'Programado']" />
                    </x-gestion.campo>
                    @if ($estado_contenido === 'programado')
                        <x-gestion.campo etiqueta="Publicar automáticamente el" nombre="publicar_en" requerido>
                            <x-gestion.fecha-hora id="publicar_en" wire:model="publicar_en" />
                            @include('livewire.cursos._lectura-fecha', ['campo' => 'publicar_en', 'prefijo' => 'Se publica', 'vacio' => 'Elige cuándo se publicará.'])
                        </x-gestion.campo>
                    @endif
                </div>
            </x-gestion.tarjeta>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-gestion.boton variante="secundario" :href="$this->rutaVolver()">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" icono="check" wire:loading.attr="disabled" wire:target="guardar">{{ $tipo === null ? 'Guardar tarea' : 'Guardar '.$tipo['titulo'] }}</x-gestion.boton>
            </div>
        </form>
    </div>
</div>
