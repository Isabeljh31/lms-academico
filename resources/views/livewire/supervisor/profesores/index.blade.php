<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Profesores y carga académica" icono="birrete"
            descripcion="Registra docentes, asigna cursos y supervisa el avance de sus calificaciones.">
            <x-gestion.boton variante="secundario" icono="subir" :href="route('supervisor.profesores.importar')">Importar profesores</x-gestion.boton>
            <x-gestion.boton icono="usuario-mas" wire:click="abrirCrear">Registrar profesor</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />
        <x-input-error :messages="$errors->get('eliminar')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-gestion.indicador etiqueta="Docentes" :valor="$totalProfesores" icono="birrete" detalle="Profesores registrados" />
            <x-gestion.indicador etiqueta="Cursos" :valor="$totalCursos" icono="libro" tono="cafe" detalle="Cursos en el catálogo" />
            <x-gestion.indicador etiqueta="Por calificar" :valor="$entregasTotalesPendientes" icono="reloj" tono="terracota" detalle="Entregas pendientes en la institución" />
            @if ($cursosSinProfesor > 0)
                <x-gestion.indicador etiqueta="Sin profesor" :valor="$cursosSinProfesor" icono="escudo" tono="alerta"
                    wire:click="toggleCursosSinProfesor" @keydown.enter="$el.click()" @keydown.space.prevent="$el.click()"
                    role="button" tabindex="0" aria-expanded="{{ $mostrarCursosSinProfesor ? 'true' : 'false' }}"
                    class="cursor-pointer text-left transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-terracota-base focus:ring-offset-1"
                    detalle="{{ $mostrarCursosSinProfesor ? 'Ocultar la lista' : 'Clic para ver y asignar' }}" />
            @else
                <x-gestion.indicador etiqueta="Sin profesor" :valor="$cursosSinProfesor" icono="escudo" tono="alerta" detalle="Todos los cursos tienen profesor" />
            @endif
        </div>

        @if ($mostrarCursosSinProfesor && $cursosSinProfesorLista->isNotEmpty())
            <x-gestion.tarjeta titulo="Cursos sin profesor">
                <ul class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($cursosSinProfesorLista as $c)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2.5" wire:key="sin-profesor-{{ $c->id }}">
                            <a href="{{ route('cursos.show', $c) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $c->nombre }}</a>
                            <x-gestion.boton variante="secundario" tamano="sm" icono="usuario-mas" wire:click="abrirModalAsignarDesdeCurso({{ $c->id }})">Asignar profesor</x-gestion.boton>
                        </li>
                    @endforeach
                </ul>
            </x-gestion.tarjeta>
        @endif

        <x-gestion.tarjeta>
            <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-[1fr_auto_auto]">
                <x-gestion.campo etiqueta="Buscar" nombre="busqueda-profesor">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <x-icono nombre="lupa" class="h-4 w-4" />
                        </span>
                        <x-gestion.control id="busqueda-profesor" type="search" class="pl-9" wire:model.live.debounce.300ms="busqueda"
                            placeholder="Nombre, correo, código o especialidad" />
                    </div>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Ordenar por" nombre="orden" class="lg:w-56">
                    <x-gestion.control tipo="select" id="orden" wire:model.live="orden">
                        <option value="nombre">Nombre (A-Z)</option>
                        <option value="atrasos">Más atrasos de calificación</option>
                        <option value="cursos">Más cursos a cargo</option>
                        <option value="reciente">Registro más reciente</option>
                    </x-gestion.control>
                </x-gestion.campo>
                <div>
                    <span class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Estado</span>
                    <div class="inline-flex rounded-lg border border-arena-medio bg-arena-claro/50 p-1 dark:border-stone-600 dark:bg-stone-900/40">
                        @foreach ([['', 'Todos', $totalProfesores], ['activo', 'Activos', $totalProfesoresActivos], ['inactivo', 'Inactivos', $totalProfesoresInactivos]] as [$valor, $etiqueta, $cantidad])
                            <button type="button" wire:click="$set('filtroEstado', '{{ $valor }}')"
                                class="rounded-md px-3 py-1.5 text-sm font-semibold transition {{ $filtroEstado === $valor
                                    ? 'bg-white text-menta-oscuro shadow-sm dark:bg-stone-700 dark:text-menta-base'
                                    : 'text-stone-500 hover:text-stone-700 dark:text-stone-400 dark:hover:text-stone-200' }}">
                                {{ $etiqueta }} {{ $cantidad }}
                            </button>
                        @endforeach
                    </div>
                </div>
            </div>
            <p class="mt-3 text-sm text-stone-500 dark:text-stone-400">Mostrando {{ $paginacion->count() }} de {{ $paginacion->total() }} docentes</p>
        </x-gestion.tarjeta>

        <x-gestion.tarjeta :relleno="false">
            @if ($profesoresConMetricas->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="birrete" titulo="No se encontraron profesores"
                        descripcion="Registra un profesor o ajusta la búsqueda." />
                </div>
            @else
                <div class="hidden items-center gap-4 border-b border-arena-medio bg-arena-claro/50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-400 lg:flex">
                    <span class="lg:min-w-0 lg:flex-1">Profesor</span>
                    <span class="lg:w-24 lg:shrink-0">Código</span>
                    <span class="lg:w-40 lg:shrink-0">Especialidad</span>
                    <span class="lg:w-20 lg:shrink-0">Cursos</span>
                    <span class="lg:w-40 lg:shrink-0">Calificación</span>
                    <span class="lg:w-[17rem] lg:shrink-0 lg:text-right">Acciones</span>
                </div>

                <div class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($profesoresConMetricas as $fila)
                        @php $prof = $fila['usuario']; $tasa = $fila['tasa_calificacion']; @endphp
                        <div wire:key="prof-{{ $prof->id }}" class="flex flex-col gap-3 px-4 py-4 lg:flex-row lg:items-center lg:gap-4">
                            <div class="flex min-w-0 items-center gap-3 lg:min-w-0 lg:flex-1">
                                <img src="{{ $prof->fotoUrl('3C7A75') }}"
                                    alt="" class="h-11 w-11 shrink-0 rounded-full border-2 border-arena-medio object-cover">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('supervisor.profesores.show', $prof) }}" wire:navigate class="truncate text-base font-bold text-stone-900 hover:underline dark:text-stone-100">{{ $prof->name }}</a>
                                        @if ($prof->activo)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-exito-base/15 px-2 py-0.5 text-xs font-medium text-exito-oscuro dark:text-green-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-exito-base"></span> Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Desactivado
                                            </span>
                                        @endif
                                    </div>
                                    <p class="truncate text-sm text-stone-500 dark:text-stone-400">{{ $prof->email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-sm text-stone-500 dark:text-stone-400 lg:w-24 lg:shrink-0">
                                <span class="font-mono lg:hidden">Código:</span>
                                <span class="font-mono">{{ $prof->codigo ?? '—' }}</span>
                            </div>

                            <div class="text-sm text-stone-700 dark:text-stone-200 lg:w-40 lg:shrink-0">
                                <span class="truncate">{{ $prof->especialidad ?? 'Sin especialidad' }}</span>
                            </div>

                            <div class="flex items-center gap-1.5 text-sm text-stone-600 dark:text-stone-300 lg:w-20 lg:shrink-0">
                                <x-icono nombre="libro" class="h-3.5 w-3.5 shrink-0 text-stone-400" />
                                {{ $fila['cursos']->count() }} {{ $fila['cursos']->count() === 1 ? 'curso' : 'cursos' }}
                            </div>

                            <div class="lg:w-40 lg:shrink-0">
                                <span class="text-sm font-semibold {{ $tasa >= 80 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }}">{{ $tasa }}% calificado</span>
                                <div class="mt-1.5 h-1.5 w-full overflow-hidden rounded-full bg-arena-medio/50 dark:bg-stone-700" role="progressbar" aria-valuenow="{{ $tasa }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="h-1.5 rounded-full {{ $tasa >= 80 ? 'bg-exito-base' : 'bg-terracota-base' }}" style="width: {{ $tasa }}%"></div>
                                </div>
                                @if ($fila['atrasos_calificacion'] > 0)
                                    <p class="mt-1 flex items-center gap-1 text-xs font-medium text-terracota-oscuro dark:text-terracota-fondo">
                                        <x-icono nombre="alerta" class="h-3.5 w-3.5 shrink-0" /> {{ $fila['atrasos_calificacion'] }} {{ $fila['atrasos_calificacion'] === 1 ? 'atrasada' : 'atrasadas' }}
                                    </p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 lg:w-[17rem] lg:shrink-0 lg:justify-end">
                                <x-gestion.boton variante="secundario" tamano="sm" icono="ojo" :href="route('supervisor.profesores.show', $prof)">Ver detalle</x-gestion.boton>
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditar({{ $prof->id }})">Editar</x-gestion.boton>
                                <x-dropdown align="right" width="48">
                                    <x-slot name="trigger">
                                        <button type="button" aria-label="Más acciones para {{ $prof->name }}"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-stone-500 hover:bg-arena-claro hover:text-stone-700 dark:text-stone-400 dark:hover:bg-stone-700">
                                            <x-icono nombre="puntos" class="h-4 w-4" />
                                        </button>
                                    </x-slot>
                                    <x-slot name="content">
                                        <button type="button" wire:click="abrirModalAsignar({{ $prof->id }})"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                            <x-icono nombre="mas" class="h-4 w-4" /> Asignar curso
                                        </button>
                                        <button type="button" wire:click="confirmarCambiarEstado({{ $prof->id }})"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                            <x-icono :nombre="$prof->activo ? 'prohibido' : 'refrescar'" class="h-4 w-4" />
                                            {{ $prof->activo ? 'Desactivar' : 'Reactivar' }}
                                        </button>
                                        <hr class="my-1 border-arena-medio dark:border-stone-700">
                                        <button type="button" wire:click="confirmarEliminarProfesor({{ $prof->id }})"
                                            class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-stone-700">
                                            <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                        </button>
                                    </x-slot>
                                </x-dropdown>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-arena-medio px-4 py-3 dark:border-stone-700">
                    {{ $paginacion->links() }}
                </div>
            @endif
        </x-gestion.tarjeta>
    </div>

    @if ($mostrarModalCrear)
        <x-gestion.modal titulo="Registrar profesor" icono="birrete" cerrar="$set('mostrarModalCrear', false)"
            descripcion="Completa los datos para registrar una cuenta de docente.">
            <form wire:submit="crearProfesor" id="form-crear-profesor" class="space-y-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Datos personales</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Nombres" nombre="nuevoNombres" requerido>
                        <x-gestion.control id="nuevoNombres" icono="persona" wire:model="nuevoNombres" placeholder="Carlos" maxlength="60" autocomplete="off" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Apellidos" nombre="nuevoApellidos" requerido>
                        <x-gestion.control id="nuevoApellidos" icono="persona" wire:model="nuevoApellidos" placeholder="Mendoza Ríos" maxlength="60" autocomplete="off" />
                    </x-gestion.campo>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="DNI" nombre="nuevoDni" requerido ayuda="8 dígitos, solo números.">
                        <x-gestion.control id="nuevoDni" icono="identificacion" wire:model="nuevoDni" placeholder="12345678" inputmode="numeric" maxlength="8"
                            autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Correo institucional" nombre="nuevoEmail" requerido>
                        <x-gestion.control id="nuevoEmail" icono="sobre" type="email" wire:model="nuevoEmail" placeholder="docente@institucion.edu.pe" />
                    </x-gestion.campo>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Año de nacimiento" nombre="nuevoAnioNacimiento" requerido>
                        <x-gestion.control id="nuevoAnioNacimiento" type="number" wire:model="nuevoAnioNacimiento" placeholder="1990" inputmode="numeric" maxlength="4" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Celular" nombre="nuevoCelular" ayuda="Opcional. 9 dígitos.">
                        <x-gestion.control id="nuevoCelular" wire:model="nuevoCelular" placeholder="987654321" inputmode="numeric" maxlength="9"
                            autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)" />
                    </x-gestion.campo>
                </div>
                <x-gestion.campo etiqueta="Correo personal" nombre="nuevoCorreoPersonal" ayuda="Opcional. Contacto de respaldo.">
                    <x-gestion.control id="nuevoCorreoPersonal" icono="sobre" type="email" wire:model="nuevoCorreoPersonal" placeholder="personal@correo.com" />
                </x-gestion.campo>

                <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Datos profesionales</p>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Especialidad" nombre="nuevaEspecialidad" ayuda="Opcional. Ej. Matemática y Física.">
                        <x-gestion.control id="nuevaEspecialidad" icono="birrete" wire:model="nuevaEspecialidad" placeholder="Matemática y Física" maxlength="120" autocomplete="off" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Grado académico" nombre="nuevoGradoAcademico" ayuda="Opcional. Ej. Magíster en Educación.">
                        <x-gestion.control id="nuevoGradoAcademico" icono="birrete" wire:model="nuevoGradoAcademico" placeholder="Magíster en Educación" maxlength="120" autocomplete="off" />
                    </x-gestion.campo>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="N.° de colegiatura" nombre="nuevaColegiatura" ayuda="Opcional. Colegio de Profesores del Perú.">
                        <x-gestion.control id="nuevaColegiatura" icono="identificacion" wire:model="nuevaColegiatura" placeholder="123456" maxlength="60" autocomplete="off" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Fecha de inicio de labores" nombre="nuevaFechaInicioLabores" ayuda="Opcional.">
                        <x-gestion.control id="nuevaFechaInicioLabores" type="date" wire:model="nuevaFechaInicioLabores" />
                    </x-gestion.campo>
                </div>
                <x-gestion.campo etiqueta="CV" nombre="nuevoCv" ayuda="Opcional. PDF o Word, hasta 10 MB.">
                    <input type="file" id="nuevoCv" wire:model="nuevoCv" accept=".pdf,.doc,.docx"
                        class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
                    <div wire:loading wire:target="nuevoCv" class="mt-1 text-xs text-stone-500">Subiendo...</div>
                </x-gestion.campo>

                <div class="flex items-start gap-3 rounded-xl border border-menta-base/30 bg-menta-fondo px-4 py-3 dark:border-menta-borde-oscuro dark:bg-menta-superficie-oscura">
                    <x-icono nombre="llave" class="h-5 w-5 shrink-0 text-menta-oscuro dark:text-menta-base" />
                    <p class="text-sm text-stone-700 dark:text-stone-200">
                        <span class="font-semibold">Contraseña predeterminada: el DNI del usuario.</span>
                        Se enviará un correo de bienvenida y deberá cambiarla al ingresar por primera vez.
                    </p>
                </div>
            </form>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" form="form-crear-profesor" wire:loading.attr="disabled" wire:target="crearProfesor">Registrar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalEditar)
        @include('livewire.supervisor.profesores._modal-editar')
    @endif

    @if ($mostrarModalAsignar)
        @include('livewire.supervisor.profesores._modal-asignar')
    @endif

    @if ($mostrarConfirmacion)
        @include('livewire.supervisor.profesores._modal-confirmar')
    @endif
</div>
