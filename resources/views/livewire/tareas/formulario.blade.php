<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$tarea ? 'Editar tarea' : 'Nueva tarea'" icono="tarea" :descripcion="$curso->nombre" />

        <form wire:submit="guardar" class="space-y-6">
            <x-gestion.tarjeta titulo="Contenido">
                <div class="space-y-4">
                    <x-gestion.campo etiqueta="Título" nombre="titulo" requerido>
                        <x-gestion.control id="titulo" wire:model="titulo" autofocus />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Semana" nombre="modulo_id" requerido>
                        <x-gestion.control tipo="select" id="modulo_id" wire:model="modulo_id">
                            <option value="">Selecciona la semana (o prácticas de campo)</option>
                            @foreach ($modulos as $modulo)
                                <option value="{{ $modulo->id }}">{{ $modulo->nombreCompleto() }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                        <x-gestion.control tipo="textarea" id="descripcion" rows="3" wire:model="descripcion" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Instrucciones" nombre="instrucciones" ayuda="Pasos, criterios de evaluación o requisitos de la entrega.">
                        <x-gestion.control tipo="textarea" id="instrucciones" rows="4" wire:model="instrucciones" />
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Plazos y calificación" descripcion="La nota de la tarea es de práctica: no se suma sola a la nota final ponderada del curso.">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Fecha de inicio" nombre="fecha_inicio" ayuda="Opcional. Antes de esta fecha no se puede entregar.">
                        <x-gestion.control id="fecha_inicio" type="datetime-local" wire:model="fecha_inicio" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha límite" nombre="fecha_entrega" requerido>
                        <x-gestion.control id="fecha_entrega" type="datetime-local" wire:model="fecha_entrega" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Puntaje / peso de práctica" nombre="peso_nota" requerido>
                        <x-gestion.control id="peso_nota" type="number" step="0.01" min="0" max="100" wire:model="peso_nota" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Intentos de entrega" nombre="intentos_permitidos" requerido>
                        <x-gestion.control id="intentos_permitidos" type="number" min="1" max="10" wire:model="intentos_permitidos" />
                    </x-gestion.campo>
                </div>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Entrega">
                <div class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <x-gestion.campo etiqueta="Tipo de entrega" nombre="tipo_entrega" requerido>
                            <x-gestion.control tipo="select" id="tipo_entrega" wire:model.live="tipo_entrega">
                                <option value="individual">Individual</option>
                                <option value="grupal">Grupal (una entrega por grupo)</option>
                            </x-gestion.control>
                        </x-gestion.campo>
                        @if ($tipo_entrega === 'grupal')
                            <x-gestion.campo etiqueta="Grupo" nombre="grupo_asignado_id" ayuda="Deja 'Todos los grupos' para que la entregue cada grupo.">
                                <x-gestion.control tipo="select" id="grupo_asignado_id" wire:model="grupo_asignado_id">
                                    <option value="">Todos los grupos</option>
                                    @foreach ($grupos as $grupo)
                                        <option value="{{ $grupo->id }}">{{ $grupo->nombre }}</option>
                                    @endforeach
                                </x-gestion.control>
                            </x-gestion.campo>
                        @endif
                    </div>
                    <fieldset>
                        <legend class="text-sm font-medium text-stone-700 dark:text-stone-300">Formatos de archivo permitidos</legend>
                        <p class="text-xs text-stone-500">Si no marcas ninguno, se aceptan todos los formatos seguros. Tamaño máximo: 10 MB.</p>
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

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-gestion.boton variante="secundario" :href="route('cursos.tareas.index', $curso)">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" icono="check">Guardar tarea</x-gestion.boton>
            </div>
        </form>
    </div>
</div>
