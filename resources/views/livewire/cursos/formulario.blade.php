<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$curso ? 'Editar curso' : 'Nuevo curso'" icono="libro"
            :volver="$curso ? route('cursos.show', $curso) : route('cursos.index')"
            :volver-texto="$curso ? 'Volver al curso' : 'Volver a cursos'"
            descripcion="Los campos con * son obligatorios. Un curso nuevo empieza como borrador hasta que tenga módulos." />

        @if ($this->esArchivado)
            <div role="alert" class="flex items-start gap-3 rounded-xl border border-terracota-base/50 bg-arena-claro px-4 py-3 text-sm text-terracota-oscuro dark:bg-stone-800 dark:text-terracota-fondo">
                <x-icono nombre="archivo" class="h-5 w-5 shrink-0" />
                <p>Este curso está archivado (finalizado) y no admite cambios retroactivos. Solo puedes cambiar su estado para reabrirlo.</p>
            </div>
        @endif

        <form wire:submit="guardar" class="space-y-6">
            <x-gestion.tarjeta titulo="Datos generales">
                <fieldset @disabled($this->esArchivado) class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <x-gestion.campo etiqueta="Nombre del curso" nombre="nombre" requerido class="sm:col-span-2">
                            <x-gestion.control id="nombre" wire:model="nombre" autofocus />
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Código" nombre="codigo" ayuda="Opcional. Letras, números y guiones (se guarda en mayúsculas). Lo usa la API de integración.">
                            <x-gestion.control id="codigo" wire:model.blur="codigo" placeholder="MAT-101" maxlength="30" class="uppercase" />
                        </x-gestion.campo>
                    </div>

                    <x-gestion.campo etiqueta="Profesor titular" nombre="profesor_id" requerido>
                        <x-gestion.control tipo="select" id="profesor_id" wire:model.live="profesor_id">
                            <option value="">Selecciona un profesor</option>
                            @foreach ($profesores as $prof)
                                <option value="{{ $prof->id }}">{{ $prof->name }} ({{ $prof->email }})</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>

                    <x-gestion.campo etiqueta="Profesores adicionales" nombre="profesoresAdicionales" ayuda="Opcional. Comparten con el titular la gestión del contenido, las actividades y las notas del curso.">
                        @php $candidatos = $profesores->reject(fn ($prof) => (int) $prof->id === (int) $profesor_id); @endphp
                        @if ($candidatos->isEmpty())
                            <p class="text-sm text-stone-500 dark:text-stone-400">No hay otros profesores registrados.</p>
                        @else
                            <div class="grid max-h-48 grid-cols-1 gap-1 overflow-y-auto rounded-lg border border-arena-medio p-2 sm:grid-cols-2 dark:border-stone-600">
                                @foreach ($candidatos as $prof)
                                    <label wire:key="coprof-{{ $prof->id }}" class="flex cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                        <input type="checkbox" value="{{ $prof->id }}" wire:model="profesoresAdicionales" class="rounded border-arena-medio text-menta-oscuro focus:ring-menta-base">
                                        <span class="truncate">{{ $prof->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        @endif
                    </x-gestion.campo>

                    <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                        <x-gestion.control tipo="textarea" id="descripcion" rows="4" wire:model="descripcion" />
                    </x-gestion.campo>

                    <x-gestion.campo etiqueta="Portada" nombre="portada" ayuda="Imagen PNG, JPG, GIF o WEBP de hasta 10 MB.">
                        <div class="flex items-center gap-4">
                            @if ($portada)
                                <img src="{{ $portada->temporaryUrl() }}" alt="" class="h-16 w-24 rounded-lg object-cover">
                            @elseif ($curso?->imagen_portada_url)
                                <img src="{{ $curso->portadaUrl() }}" alt="" class="h-16 w-24 rounded-lg object-cover">
                            @endif
                            <input wire:model="portada" id="portada" type="file" accept="image/png,image/jpeg,image/gif,image/webp"
                                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                        </div>
                    </x-gestion.campo>
                </fieldset>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Configuración académica">
                <div class="space-y-4">
                    <x-gestion.campo etiqueta="Estado del curso" nombre="estado" requerido>
                        <x-gestion.control tipo="select" id="estado" wire:model="estado">
                            @foreach ($estadosCurso as $valor => $etiqueta)
                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                            @endforeach
                        </x-gestion.control>
                    </x-gestion.campo>

                    <fieldset @disabled($this->esArchivado) class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-gestion.campo etiqueta="Fecha de inicio" nombre="fecha_inicio">
                                <x-gestion.control id="fecha_inicio" type="date" wire:model="fecha_inicio" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Fecha de fin" nombre="fecha_fin">
                                <x-gestion.control id="fecha_fin" type="date" wire:model="fecha_fin" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Duración (semanas)" nombre="semanas_duracion" requerido ayuda="El contenido del curso se organiza en esta cantidad de semanas (Semana 1, Semana 2...).">
                                <x-gestion.control id="semanas_duracion" type="number" min="1" max="104" wire:model="semanas_duracion" placeholder="16" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="N.° de sesiones de clase" nombre="sesiones_clase_count" ayuda="Encuentros con asistencia; independientes de las semanas de contenido.">
                                <x-gestion.control id="sesiones_clase_count" type="number" min="1" max="999" wire:model="sesiones_clase_count" placeholder="32" />
                            </x-gestion.campo>
                        </div>

                        <label class="flex items-start gap-3 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model="usa_asistencia" class="mt-0.5 rounded border-arena-medio text-menta-oscuro focus:ring-menta-base">
                            <span>
                                Registrar asistencia en este curso
                                <span class="block text-xs text-stone-500">Desmárcalo si el curso no requiere control de asistencia.</span>
                            </span>
                        </label>
                    </fieldset>
                </div>
            </x-gestion.tarjeta>

            <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <x-gestion.boton variante="secundario" :href="$curso ? route('cursos.show', $curso) : route('cursos.index')">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" icono="check" wire:loading.attr="disabled" wire:target="guardar,portada">
                    {{ $curso ? 'Guardar cambios' : 'Crear curso' }}
                </x-gestion.boton>
            </div>
        </form>
    </div>
</div>
