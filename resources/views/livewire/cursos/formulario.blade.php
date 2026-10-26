<div class="py-8">
    <div class="mx-auto max-w-3xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado :titulo="$curso ? 'Editar curso' : 'Nuevo curso'" icono="libro"
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
                        <x-gestion.campo etiqueta="Nombre del curso" nombre="nombre" requerido class="sm:col-span-2"
                            ayuda="Escribe uno nuevo, o elige uno ya registrado si es otra sección de la misma materia.">
                            <x-gestion.control id="nombre" icono="libro" list="nombres-cursos-existentes" autocomplete="off"
                                wire:model.live.debounce.400ms="nombre" autofocus />
                            <datalist id="nombres-cursos-existentes">
                                @foreach ($nombresExistentes as $nombreExistente)
                                    <option value="{{ $nombreExistente }}"></option>
                                @endforeach
                            </datalist>
                        </x-gestion.campo>
                        <x-gestion.campo etiqueta="Código" nombre="codigo"
                            ayuda="Se arma solo a partir del nombre; puedes editarlo si quieres.">
                            <x-gestion.control id="codigo" icono="identificacion" wire:model.live.debounce.400ms="codigo"
                                placeholder="MAT1-26-01" maxlength="30" class="uppercase" autocomplete="off" />
                        </x-gestion.campo>
                    </div>

                    @if ($sugerenciaNombre)
                        <div class="flex items-center justify-between gap-3 rounded-xl border border-terracota-base/40 bg-arena-claro px-4 py-2.5 text-sm dark:border-stone-600 dark:bg-stone-800">
                            <p class="flex items-center gap-2 text-stone-700 dark:text-stone-200">
                                <x-icono nombre="info" class="h-4 w-4 shrink-0 text-terracota-oscuro" />
                                ¿Quisiste decir <strong>"{{ $sugerenciaNombre }}"</strong>? Ya existe un curso con ese nombre.
                            </p>
                            <x-gestion.boton variante="secundario" tamano="sm" wire:click="usarNombreSugerido">Usar ese</x-gestion.boton>
                        </div>
                    @endif

                    <x-gestion.campo etiqueta="Profesor titular" nombre="profesor_id" :requerido="! $asignarProfesorDespues">
                        @if ($asignarProfesorDespues)
                            <div class="flex items-start gap-3 rounded-xl border border-terracota-base/40 bg-arena-claro px-4 py-3 text-sm dark:border-stone-600 dark:bg-stone-800">
                                <x-icono nombre="info" class="h-4 w-4 shrink-0 text-terracota-oscuro" />
                                <p class="text-stone-700 dark:text-stone-200">El curso quedará a tu nombre como responsable provisional. Asígnalo a un profesor real desde <strong>Admin &gt; Profesores</strong> cuando lo sepas.</p>
                            </div>
                        @elseif ($profesor_id)
                            <div class="flex items-center justify-between gap-3 rounded-lg border border-arena-medio bg-arena-claro/60 px-3 py-2 dark:border-stone-600 dark:bg-stone-800">
                                <span class="truncate text-sm font-medium text-stone-800 dark:text-stone-100">{{ $busquedaProfesorTitular }}</span>
                                <button type="button" wire:click="cambiarProfesorTitular" class="shrink-0 text-xs font-semibold text-menta-oscuro hover:underline dark:text-menta-base">Cambiar</button>
                            </div>
                        @else
                            <div class="relative">
                                <x-gestion.control id="profesor_id_busqueda" icono="lupa" wire:model.live.debounce.300ms="busquedaProfesorTitular"
                                    placeholder="Escribe el nombre o correo del profesor" autocomplete="off" />
                                @if ($profesoresBusquedaTitular->isNotEmpty())
                                    <div class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-arena-medio bg-white shadow-lg dark:border-stone-600 dark:bg-stone-800">
                                        @foreach ($profesoresBusquedaTitular as $prof)
                                            <button type="button" wire:click="seleccionarProfesorTitular({{ $prof->id }})" wire:key="titular-{{ $prof->id }}"
                                                class="flex w-full flex-col items-start gap-0.5 border-b border-arena-medio/60 px-4 py-2 text-left last:border-0 hover:bg-arena-claro dark:border-stone-700 dark:hover:bg-stone-700">
                                                <span class="text-sm font-medium text-stone-800 dark:text-stone-100">{{ $prof->name }}</span>
                                                <span class="text-xs text-stone-500 dark:text-stone-400">{{ $prof->email }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                @elseif (mb_strlen(trim($busquedaProfesorTitular)) >= 2)
                                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">Sin resultados.</p>
                                @endif
                            </div>
                        @endif
                    </x-gestion.campo>

                    @unless ($curso)
                        <label class="flex items-start gap-3 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model.live="asignarProfesorDespues" class="mt-0.5 rounded border-arena-medio text-menta-oscuro focus:ring-menta-base">
                            <span>Asignar profesor titular después
                                <span class="block text-xs text-stone-500">Útil si todavía no sabes quién lo va a dictar.</span>
                            </span>
                        </label>
                    @endunless

                    <x-gestion.campo etiqueta="Profesores adicionales" nombre="profesoresAdicionales" ayuda="Opcional. Comparten con el titular la gestión del contenido, las actividades y las notas del curso.">
                        @if ($profesoresAdicionalesSeleccionados->isNotEmpty())
                            <div class="mb-2 flex flex-wrap gap-2">
                                @foreach ($profesoresAdicionalesSeleccionados as $prof)
                                    <span wire:key="adicional-chip-{{ $prof->id }}" class="inline-flex items-center gap-1.5 rounded-full bg-menta-fondo px-3 py-1 text-xs font-medium text-menta-oscuro dark:bg-stone-700 dark:text-menta-base">
                                        {{ $prof->name }}
                                        <button type="button" wire:click="quitarProfesorAdicional({{ $prof->id }})" aria-label="Quitar a {{ $prof->name }}" class="hover:text-terracota-oscuro">
                                            <x-icono nombre="cerrar" class="h-3 w-3" />
                                        </button>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                        <div class="relative">
                            <x-gestion.control id="profesor_adicional_busqueda" icono="lupa" wire:model.live.debounce.300ms="busquedaProfesorAdicional"
                                placeholder="Busca otro profesor para agregar" autocomplete="off" />
                            @if ($profesoresBusquedaAdicional->isNotEmpty())
                                <div class="absolute left-0 right-0 top-full z-20 mt-1 overflow-hidden rounded-xl border border-arena-medio bg-white shadow-lg dark:border-stone-600 dark:bg-stone-800">
                                    @foreach ($profesoresBusquedaAdicional as $prof)
                                        <button type="button" wire:click="agregarProfesorAdicional({{ $prof->id }})" wire:key="adicional-{{ $prof->id }}"
                                            class="flex w-full flex-col items-start gap-0.5 border-b border-arena-medio/60 px-4 py-2 text-left last:border-0 hover:bg-arena-claro dark:border-stone-700 dark:hover:bg-stone-700">
                                            <span class="text-sm font-medium text-stone-800 dark:text-stone-100">{{ $prof->name }}</span>
                                            <span class="text-xs text-stone-500 dark:text-stone-400">{{ $prof->email }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (mb_strlen(trim($busquedaProfesorAdicional)) >= 2)
                                <p class="mt-1 text-xs text-stone-500 dark:text-stone-400">Sin resultados.</p>
                            @endif
                        </div>
                    </x-gestion.campo>

                    <x-gestion.campo etiqueta="Descripción" nombre="descripcion">
                        <x-gestion.control tipo="textarea" id="descripcion" rows="4" wire:model="descripcion" />
                    </x-gestion.campo>

                    <x-gestion.campo etiqueta="Portada" nombre="portada" ayuda="Imagen PNG, JPG, GIF o WEBP de hasta 10 MB. Haz clic en la miniatura para verla más grande.">
                        @php $urlPortada = $portada?->temporaryUrl() ?? $curso?->portadaUrl(); @endphp
                        <div class="flex items-center gap-4">
                            @if ($urlPortada)
                                <div x-data="{ ampliada: false }">
                                    <button type="button" @click="ampliada = true" aria-label="Ver portada más grande"
                                        class="group relative block overflow-hidden rounded-lg focus:outline-none focus:ring-2 focus:ring-menta-base">
                                        <img src="{{ $urlPortada }}" alt="" class="h-16 w-24 rounded-lg object-cover transition group-hover:brightness-90">
                                        <span class="absolute inset-0 hidden items-center justify-center bg-stone-900/30 group-hover:flex">
                                            <x-icono nombre="ojo" class="h-4 w-4 text-white" />
                                        </span>
                                    </button>

                                    <div x-show="ampliada" x-transition.opacity @click="ampliada = false"
                                        @keydown.escape.window="ampliada = false" style="display: none;"
                                        class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/80 p-4">
                                        <img src="{{ $urlPortada }}" alt="" class="max-h-[85vh] max-w-full rounded-xl object-contain shadow-2xl" @click.stop>
                                        <button type="button" @click="ampliada = false" aria-label="Cerrar"
                                            class="absolute right-4 top-4 rounded-lg bg-white/10 p-2 text-white hover:bg-white/20">
                                            <x-icono nombre="cerrar" class="h-5 w-5" />
                                        </button>
                                    </div>
                                </div>
                            @endif
                            <input wire:model="portada" id="portada" type="file" accept="image/png,image/jpeg,image/gif,image/webp"
                                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                        </div>
                    </x-gestion.campo>
                </fieldset>
            </x-gestion.tarjeta>

            <x-gestion.tarjeta titulo="Configuración académica">
                <div class="space-y-4">
                    @if ($curso)
                        <x-gestion.campo etiqueta="Estado del curso" nombre="estado" requerido>
                            <x-gestion.control tipo="select" id="estado" wire:model="estado">
                                @foreach ($estadosCurso as $valor => $etiqueta)
                                    <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                @endforeach
                            </x-gestion.control>
                        </x-gestion.campo>
                    @else
                        <div class="flex items-start gap-3 rounded-xl border border-arena-medio bg-arena-claro/60 px-4 py-3 text-sm dark:border-stone-600 dark:bg-stone-800">
                            <x-icono nombre="info" class="h-4 w-4 shrink-0 text-stone-500" />
                            <p class="text-stone-700 dark:text-stone-200">El curso nace como borrador: no lo verán los estudiantes hasta que agregues contenido y lo publiques desde "Editar curso".</p>
                        </div>
                    @endif

                    <fieldset @disabled($this->esArchivado) class="space-y-4">
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-gestion.campo etiqueta="Fecha de inicio" nombre="fecha_inicio">
                                <x-gestion.control id="fecha_inicio" type="date" wire:model.live="fecha_inicio" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Duración (semanas)" nombre="semanas_duracion" requerido ayuda="El contenido del curso se organiza en esta cantidad de semanas (Semana 1, Semana 2...).">
                                <x-gestion.control id="semanas_duracion" type="number" min="1" max="104" wire:model.live.debounce.400ms="semanas_duracion" placeholder="16" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Fecha de fin" nombre="fecha_fin" ayuda="Se calcula sola desde la fecha de inicio y la duración.">
                                <x-gestion.control id="fecha_fin" type="date" value="{{ $fecha_fin }}" disabled />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Cupo máximo" nombre="cupo_maximo" ayuda="Déjalo vacío para no poner límite de estudiantes.">
                                <x-gestion.control id="cupo_maximo" type="number" min="1" max="500" wire:model="cupo_maximo" placeholder="Sin límite" />
                            </x-gestion.campo>
                            <x-gestion.campo etiqueta="Modalidad" nombre="modalidad" requerido>
                                <x-gestion.control tipo="select" id="modalidad" wire:model.live="modalidad">
                                    <option value="presencial">Presencial</option>
                                    <option value="remoto">Remoto (en vivo, por videollamada)</option>
                                    <option value="virtual">Virtual (asíncrono, sin horario fijo)</option>
                                </x-gestion.control>
                            </x-gestion.campo>
                        </div>

                        <label class="flex items-start gap-3 text-sm text-stone-700 dark:text-stone-300">
                            <input type="checkbox" wire:model.live="usa_asistencia" class="mt-0.5 rounded border-arena-medio text-menta-oscuro focus:ring-menta-base">
                            <span>
                                Registrar asistencia en este curso
                                <span class="block text-xs text-stone-500">Desmárcalo si el curso no requiere control de asistencia.</span>
                            </span>
                        </label>

                        @if ($modalidad !== 'virtual' && $usa_asistencia)
                            <div class="rounded-xl border border-arena-medio p-4 dark:border-stone-600">
                                <p class="text-sm font-medium text-stone-700 dark:text-stone-300">Horario de clase</p>
                                <p class="mt-0.5 text-xs text-stone-500 dark:text-stone-400">
                                    Agrega un bloque por cada día de clase: pueden tener horas{{ $modalidad === 'presencial' ? ' y aulas' : '' }} distintas (por ejemplo, lunes en un horario y viernes en otro). Al crear el curso se genera automáticamente una sesión de asistencia por cada clase, entre la fecha de inicio y la de fin.
                                </p>

                                <div class="mt-3 space-y-2">
                                    @foreach ($horarioBloques as $i => $bloque)
                                        <div wire:key="bloque-horario-{{ $i }}" class="rounded-lg border border-arena-medio/70 bg-arena-claro/40 p-3 dark:border-stone-700 dark:bg-stone-900/40">
                                            <div class="flex flex-wrap items-end gap-2">
                                                <div class="w-36">
                                                    <x-gestion.campo etiqueta="Día" nombre="horarioBloques.{{ $i }}.dia">
                                                        <x-gestion.control tipo="select" id="horarioDia{{ $i }}" wire:model="horarioBloques.{{ $i }}.dia">
                                                            <option value="">Elige un día</option>
                                                            @foreach (['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '7' => 'Domingo'] as $valor => $etiqueta)
                                                                <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                                            @endforeach
                                                        </x-gestion.control>
                                                    </x-gestion.campo>
                                                </div>
                                                <div class="w-28">
                                                    <x-gestion.campo etiqueta="Hora inicio" nombre="horarioBloques.{{ $i }}.hora_inicio">
                                                        <x-gestion.control id="horarioInicio{{ $i }}" type="time" wire:model.live="horarioBloques.{{ $i }}.hora_inicio" />
                                                    </x-gestion.campo>
                                                </div>
                                                <div class="w-28">
                                                    <x-gestion.campo etiqueta="Hora fin" nombre="horarioBloques.{{ $i }}.hora_fin">
                                                        <x-gestion.control id="horarioFin{{ $i }}" type="time" wire:model.live="horarioBloques.{{ $i }}.hora_fin" />
                                                    </x-gestion.campo>
                                                </div>
                                                @if ($modalidad === 'presencial')
                                                    <div class="w-32 grow">
                                                        <x-gestion.campo etiqueta="Aula" nombre="horarioBloques.{{ $i }}.aula">
                                                            <x-gestion.control id="horarioAula{{ $i }}" wire:model="horarioBloques.{{ $i }}.aula" placeholder="Aula 204" maxlength="50" />
                                                        </x-gestion.campo>
                                                    </div>
                                                @endif
                                                <button type="button" wire:click="quitarBloqueHorario({{ $i }})" aria-label="Quitar este día"
                                                    class="mb-0.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg text-stone-400 transition hover:bg-red-50 hover:text-red-600 dark:hover:bg-stone-700">
                                                    <x-icono nombre="papelera" class="h-4 w-4" />
                                                </button>
                                            </div>
                                            @php $duracionMinutos = $this->duracionBloque($bloque); @endphp
                                            @if (filled($bloque['hora_inicio'] ?? null) && filled($bloque['hora_fin'] ?? null))
                                                <p class="mt-1.5 flex items-center gap-1.5 text-xs {{ $duracionMinutos ? 'text-stone-500 dark:text-stone-400' : 'font-medium text-red-600 dark:text-red-400' }}">
                                                    <x-icono nombre="reloj" class="h-3.5 w-3.5 shrink-0" />
                                                    @if ($duracionMinutos)
                                                        Dura {{ intdiv($duracionMinutos, 60) }} h{{ $duracionMinutos % 60 ? ' '.($duracionMinutos % 60).' min' : '' }} por clase.
                                                    @else
                                                        La hora de fin debe ser después de la hora de inicio.
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>

                                @if (empty($horarioBloques))
                                    <p class="mt-2 text-xs text-stone-500 dark:text-stone-400">Sin bloques de horario todavía.</p>
                                @endif

                                @if (count($horarioBloques) < 7)
                                    <x-gestion.boton type="button" variante="secundario" tamano="sm" icono="mas" wire:click="agregarBloqueHorario" class="mt-3">
                                        Agregar día
                                    </x-gestion.boton>
                                @endif
                            </div>
                        @endif
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
