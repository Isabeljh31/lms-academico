<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Profesores y carga académica" icono="birrete"
            descripcion="Registra docentes, asigna cursos y supervisa el avance de sus calificaciones.">
            <x-gestion.boton icono="usuario-mas" wire:click="abrirCrear">Registrar profesor</x-gestion.boton>
        </x-gestion.encabezado>

        <x-gestion.alertas />
        <x-input-error :messages="$errors->get('eliminar')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-gestion.indicador etiqueta="Docentes" :valor="$totalProfesores" icono="birrete" detalle="Profesores registrados" />
            <x-gestion.indicador etiqueta="Cursos" :valor="$totalCursos" icono="libro" tono="cafe" detalle="Cursos en el catálogo" />
            <x-gestion.indicador etiqueta="Por calificar" :valor="$entregasTotalesPendientes" icono="reloj" tono="terracota" detalle="Entregas pendientes en la institución" />
            <x-gestion.indicador etiqueta="Sin profesor" :valor="$cursosSinProfesor" icono="escudo" tono="alerta" detalle="Cursos a cargo del Supervisor" />
        </div>

        <x-gestion.tarjeta>
            <label for="busqueda-profesor" class="sr-only">Buscar profesor</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                    <x-icono nombre="lupa" class="h-4 w-4" />
                </span>
                <x-gestion.control id="busqueda-profesor" type="search" class="pl-9" wire:model.live.debounce.300ms="busqueda"
                    placeholder="Buscar docente por nombre o correo" />
            </div>
        </x-gestion.tarjeta>

        <div class="space-y-4">
            @forelse ($profesoresConMetricas as $fila)
                @php $prof = $fila['usuario']; @endphp
                <x-gestion.tarjeta wire:key="prof-{{ $prof->id }}">
                    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                        <div class="flex items-start gap-4 lg:col-span-4">
                            <img src="{{ $prof->fotoUrl('3C7A75') }}"
                                alt="" class="h-12 w-12 shrink-0 rounded-full border-2 border-arena-medio object-cover">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-bold text-stone-900 dark:text-stone-100">{{ $prof->name }}</h2>
                                <p class="truncate text-sm text-stone-500 dark:text-stone-400">{{ $prof->email }}</p>
                                <p class="text-xs text-stone-500">{{ $prof->activo ? 'Activo' : 'Desactivado' }}</p>
                                <div class="mt-2 flex flex-wrap gap-1">
                                    <x-gestion.boton variante="fantasma" tamano="sm" wire:click="abrirEditar({{ $prof->id }})">Editar</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma" tamano="sm" wire:click="cambiarEstado({{ $prof->id }})"
                                        wire:confirm="¿Cambiar el estado de la cuenta de {{ $prof->name }}?">{{ $prof->activo ? 'Desactivar' : 'Reactivar' }}</x-gestion.boton>
                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" wire:click="eliminarProfesor({{ $prof->id }})"
                                        wire:confirm="¿Eliminar definitivamente a {{ $prof->name }}? Esta acción no se puede deshacer. Debes reasignar sus cursos antes de eliminarlo.">Eliminar</x-gestion.boton>
                                </div>
                                <p class="mt-1 text-xs text-stone-500">{{ $fila['estudiantes_count'] }} estudiantes activos - {{ $fila['tareas_count'] }} tareas</p>
                            </div>
                        </div>

                        <div class="space-y-2 border-t border-arena-medio/60 pt-4 lg:col-span-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0 dark:border-stone-700">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">Cursos a cargo ({{ $fila['cursos']->count() }})</span>
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="mas" wire:click="abrirModalAsignar({{ $prof->id }})">Asignar curso</x-gestion.boton>
                            </div>
                            @if ($fila['cursos']->isEmpty())
                                <p class="text-xs text-stone-500">Sin cursos asignados.</p>
                            @else
                                <div class="flex flex-wrap gap-2">
                                    @foreach ($fila['cursos'] as $c)
                                        <span class="inline-flex items-center gap-1.5 rounded-lg border border-arena-medio bg-arena-claro/40 px-2.5 py-1 text-xs dark:border-stone-600 dark:bg-stone-700/50" wire:key="curso-tag-{{ $c->id }}">
                                            <a href="{{ route('cursos.show', $c) }}" wire:navigate class="font-medium text-stone-800 hover:underline dark:text-stone-200">{{ $c->nombre }}</a>
                                            @unless ($c->estaFinalizado())
                                                <button type="button" wire:click="desasignarCurso({{ $c->id }})"
                                                    wire:confirm="¿Desvincular a {{ $prof->name }} del curso {{ $c->nombre }}? El curso quedará a cargo del Supervisor."
                                                    class="text-stone-400 hover:text-terracota-oscuro" title="Desvincular" aria-label="Desvincular del curso {{ $c->nombre }}">
                                                    <x-icono nombre="cerrar" class="h-3.5 w-3.5" />
                                                </button>
                                            @endunless
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="space-y-2 border-t border-arena-medio/60 pt-4 lg:col-span-4 lg:border-l lg:border-t-0 lg:pl-6 lg:pt-0 dark:border-stone-700">
                            <span class="text-xs font-semibold uppercase tracking-wide text-stone-500">Calificación de entregas</span>
                            @php $tasa = $fila['tasa_calificacion']; @endphp
                            <div class="flex items-center justify-between text-xs">
                                <span class="text-stone-600 dark:text-stone-400">Revisadas</span>
                                <span class="font-bold {{ $tasa >= 80 ? 'text-exito-oscuro' : 'text-terracota-oscuro' }}">{{ $tasa }} %</span>
                            </div>
                            <div class="h-2 w-full overflow-hidden rounded-full bg-arena-medio/50 dark:bg-stone-700" role="progressbar" aria-valuenow="{{ $tasa }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="h-2 rounded-full {{ $tasa >= 80 ? 'bg-exito-base' : 'bg-terracota-base' }}" style="width: {{ $tasa }}%"></div>
                            </div>
                            <p class="text-xs text-stone-500">{{ $fila['calificadas'] }} calificadas - {{ $fila['pendientes'] }} pendientes - {{ $fila['total_entregas'] }} en total</p>
                            @if ($fila['atrasos_calificacion'] > 0)
                                <p class="flex items-center gap-1.5 rounded-md bg-arena-claro px-2 py-1.5 text-xs text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                    <x-icono nombre="alerta" class="h-3.5 w-3.5" />
                                    {{ $fila['atrasos_calificacion'] }} {{ $fila['atrasos_calificacion'] === 1 ? 'entrega lleva' : 'entregas llevan' }} más de 3 días sin revisar
                                </p>
                            @endif
                        </div>
                    </div>
                </x-gestion.tarjeta>
            @empty
                <x-gestion.vacio icono="birrete" titulo="No se encontraron profesores"
                    descripcion="Registra un profesor o ajusta la búsqueda." />
            @endforelse

            <div>{{ $paginacion->links() }}</div>
        </div>
    </div>

    @if ($mostrarModalCrear)
        <x-gestion.modal titulo="Registrar profesor" cerrar="$set('mostrarModalCrear', false)"
            descripcion="La contraseña predeterminada será el DNI del usuario. Se enviará un correo de bienvenida.">
            <form wire:submit="crearProfesor" id="form-crear-profesor" class="space-y-4">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <x-gestion.campo etiqueta="Nombres" nombre="nuevoNombres" requerido>
                        <x-gestion.control id="nuevoNombres" wire:model="nuevoNombres" placeholder="Carlos" maxlength="60" autocomplete="off" />
                    </x-gestion.campo>
                    <x-gestion.campo etiqueta="Apellidos" nombre="nuevoApellidos" requerido>
                        <x-gestion.control id="nuevoApellidos" wire:model="nuevoApellidos" placeholder="Mendoza Ríos" maxlength="60" autocomplete="off" />
                    </x-gestion.campo>
                </div>
                <x-gestion.campo etiqueta="DNI" nombre="nuevoDni" requerido ayuda="8 dígitos, solo números.">
                    <x-gestion.control id="nuevoDni" wire:model="nuevoDni" placeholder="12345678" inputmode="numeric" maxlength="8"
                        autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)" />
                <p class="mt-1 text-xs text-stone-500">Contraseña predeterminada: su DNI. Deberá cambiarla al iniciar sesión por primera vez.</p>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Correo institucional" nombre="nuevoEmail" requerido>
                    <x-gestion.control id="nuevoEmail" type="email" wire:model="nuevoEmail" placeholder="docente@institucion.edu.pe" />
                </x-gestion.campo>
            </form>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" form="form-crear-profesor">Registrar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalEditar)
        <x-gestion.modal titulo="Editar profesor" cerrar="$set('mostrarModalEditar', false)">
            <form wire:submit="guardarEdicion" id="form-editar-profesor" class="space-y-4">
                <x-gestion.campo etiqueta="Nombres" nombre="editarNombres" requerido>
                    <x-gestion.control id="editarNombres" wire:model="editarNombres" maxlength="60" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Apellidos" nombre="editarApellidos" requerido>
                    <x-gestion.control id="editarApellidos" wire:model="editarApellidos" maxlength="60" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="DNI" nombre="editarDni" requerido>
                    <x-gestion.control id="editarDni" wire:model="editarDni" inputmode="numeric" maxlength="8" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Correo institucional" nombre="editarEmail" requerido>
                    <x-gestion.control id="editarEmail" type="email" wire:model="editarEmail" />
                </x-gestion.campo>
            </form>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalEditar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton type="submit" form="form-editar-profesor" wire:loading.attr="disabled" wire:target="guardarEdicion">Guardar cambios</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalAsignar)
        <x-gestion.modal titulo="Asignar curso" cerrar="$set('mostrarModalAsignar', false)"
            descripcion="{{ $profesorSeleccionado?->name }} pasará a ser el profesor titular del curso elegido.">
            <x-gestion.campo etiqueta="Curso" nombre="cursoAAsignarId" requerido ayuda="Los cursos archivados no se pueden reasignar.">
                <x-gestion.control tipo="select" id="cursoAAsignarId" wire:model="cursoAAsignarId">
                    <option value="">Selecciona un curso</option>
                    @foreach ($cursosDisponibles as $c)
                        <option value="{{ $c->id }}">{{ $c->nombre }} (actual: {{ $c->profesor?->name ?? 'sin asignar' }})</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalAsignar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton wire:click="asignarCurso">Confirmar asignación</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
