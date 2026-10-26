<div class="py-8">
    <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Estudiantes" icono="usuarios"
            descripcion="Registra estudiantes, revisa su avance por curso y detecta riesgo de abandono a tiempo.">
            <x-gestion.boton variante="secundario" icono="subir" :href="route('usuarios.importar')">Importar estudiantes</x-gestion.boton>
            <x-gestion.boton icono="usuario-mas" wire:click="abrirCrear" id="btn-crear-usuario">Nuevo estudiante</x-gestion.boton>
        </x-gestion.encabezado>

        @if (session('password_temporal'))
            <div role="alert" class="rounded-xl border border-terracota-base/50 bg-arena-claro p-4 dark:border-stone-600 dark:bg-stone-800">
                <div class="flex items-start gap-3">
                    <x-icono nombre="llave" class="h-5 w-5 shrink-0 text-terracota-oscuro" />
                    <div class="space-y-1 text-sm">
                        <p class="font-semibold text-stone-800 dark:text-stone-100">Cuenta de "{{ session('usuario_temporal_nombre') }}" creada.</p>
                        <p class="text-stone-700 dark:text-stone-300">
                            Contraseña temporal:
                            <code class="rounded bg-white px-2 py-0.5 font-mono font-bold text-stone-900 dark:bg-stone-900 dark:text-stone-100">{{ session('password_temporal') }}</code>
                        </p>
                        <p class="text-xs text-stone-600 dark:text-stone-400">
                            @if (session('correo_enviado'))
                                Se envió un correo de bienvenida con estas credenciales.
                            @else
                                No se pudo enviar el correo de bienvenida: entrega esta contraseña al estudiante.
                            @endif
                            No se volverá a mostrar. El estudiante deberá cambiarla en su primer inicio de sesión.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <x-gestion.alertas />
        <x-input-error :messages="$errors->get('eliminar')" />

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-gestion.indicador etiqueta="Estudiantes" :valor="$totalEstudiantes" icono="usuarios" detalle="Cuentas registradas" />
            <x-gestion.indicador etiqueta="Matrículas activas" :valor="$matriculasActivas" icono="libro" tono="cafe" detalle="Inscripciones vigentes" />
            <x-gestion.indicador etiqueta="En riesgo" :valor="$totalEnRiesgo" icono="alerta" tono="alerta" detalle="Asistencia o nota bajo el mínimo" />
            <x-gestion.indicador etiqueta="Primer ingreso pendiente" :valor="$totalPrimerIngreso" icono="llave" tono="terracota" detalle="Aún no cambian su contraseña" />
        </div>

        <x-gestion.tarjeta>
            <div class="grid grid-cols-1 items-end gap-4 lg:grid-cols-[1fr_auto_auto]">
                <x-gestion.campo etiqueta="Buscar" nombre="busqueda-estudiante">
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-stone-400">
                            <x-icono nombre="lupa" class="h-4 w-4" />
                        </span>
                        <x-gestion.control id="busqueda-estudiante" type="search" class="pl-9" wire:model.live.debounce.300ms="busqueda"
                            placeholder="Nombre, correo, DNI o código" />
                    </div>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Ordenar por" nombre="orden" class="lg:w-56">
                    <x-gestion.control tipo="select" id="orden" wire:model.live="orden">
                        <option value="nombre">Nombre (A-Z)</option>
                        <option value="riesgo">Más en riesgo primero</option>
                        <option value="reciente">Registro más reciente</option>
                    </x-gestion.control>
                </x-gestion.campo>
                <div>
                    <span class="mb-1 block text-sm font-medium text-stone-700 dark:text-stone-300">Estado</span>
                    <div class="inline-flex rounded-lg border border-arena-medio bg-arena-claro/50 p-1 dark:border-stone-600 dark:bg-stone-900/40">
                        @foreach ([['', 'Todos', $totalEstudiantes], ['activo', 'Activos', $totalEstudiantesActivos], ['inactivo', 'Inactivos', $totalEstudiantesInactivos]] as [$valor, $etiqueta, $cantidad])
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
            <p class="mt-3 text-sm text-stone-500 dark:text-stone-400">Mostrando {{ $paginacion->count() }} de {{ $paginacion->total() }} estudiantes</p>
        </x-gestion.tarjeta>

        <x-gestion.tarjeta :relleno="false">
            @if ($estudiantesConMetricas->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="usuarios" titulo="No se encontraron estudiantes"
                        descripcion="Registra un estudiante o ajusta la búsqueda." />
                </div>
            @else
                <div class="hidden items-center gap-4 border-b border-arena-medio bg-arena-claro/50 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-stone-500 dark:border-stone-700 dark:bg-stone-900/40 dark:text-stone-400 lg:flex">
                    <span class="lg:min-w-0 lg:flex-1">Estudiante</span>
                    <span class="lg:w-24 lg:shrink-0">Código</span>
                    <span class="lg:w-20 lg:shrink-0">Cursos</span>
                    <span class="lg:w-32 lg:shrink-0">Riesgo</span>
                    <span class="lg:w-36 lg:shrink-0">Acceso</span>
                    <span class="lg:w-[17rem] lg:shrink-0 lg:text-right">Acciones</span>
                </div>

                <div class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                    @foreach ($estudiantesConMetricas as $fila)
                        @php $u = $fila['usuario']; @endphp
                        <div wire:key="est-{{ $u->id }}" class="flex flex-col gap-3 px-4 py-4 lg:flex-row lg:items-center lg:gap-4">
                            <div class="flex min-w-0 items-center gap-3 lg:min-w-0 lg:flex-1">
                                <img src="{{ $u->fotoUrl() }}" alt="" class="h-11 w-11 shrink-0 rounded-full border-2 border-arena-medio object-cover">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <a href="{{ route('admin.usuarios.show', $u) }}" wire:navigate class="truncate text-base font-bold text-stone-900 hover:underline dark:text-stone-100">{{ $u->nombreFormal() }}</a>
                                        @if ($u->activo)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-exito-base/15 px-2 py-0.5 text-xs font-medium text-exito-oscuro dark:text-green-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-exito-base"></span> Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Desactivado
                                            </span>
                                        @endif
                                    </div>
                                    <p class="truncate text-sm text-stone-500 dark:text-stone-400">{{ $u->email }}</p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 text-sm text-stone-500 dark:text-stone-400 lg:w-24 lg:shrink-0">
                                <span class="font-mono lg:hidden">Código:</span>
                                <span class="font-mono">{{ $u->codigo ?? '—' }}</span>
                            </div>

                            <div class="flex items-center gap-1.5 text-sm text-stone-600 dark:text-stone-300 lg:w-20 lg:shrink-0">
                                <x-icono nombre="libro" class="h-3.5 w-3.5 shrink-0 text-stone-400" />
                                {{ $fila['cursos_count'] }} {{ $fila['cursos_count'] === 1 ? 'curso' : 'cursos' }}
                            </div>

                            <div class="lg:w-32 lg:shrink-0">
                                @if ($fila['riesgo_nivel'] === 'Alto')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                        <x-icono nombre="alerta" class="h-3.5 w-3.5" /> Riesgo alto
                                    </span>
                                @elseif ($fila['riesgo_nivel'] === 'Medio')
                                    <span class="inline-flex items-center gap-1 rounded-full bg-arena-claro px-2.5 py-1 text-xs font-medium text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                        <x-icono nombre="alerta" class="h-3.5 w-3.5" /> Riesgo medio
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-exito-base/15 px-2.5 py-1 text-xs font-medium text-exito-oscuro dark:text-green-300">
                                        <x-icono nombre="check-circulo" class="h-3.5 w-3.5" /> Sin riesgo
                                    </span>
                                @endif
                            </div>

                            <div class="lg:w-36 lg:shrink-0">
                                @if ($u->primer_login)
                                    <span title="Aún no cambió su contraseña temporal"
                                        class="inline-flex shrink-0 items-center gap-1 rounded-full bg-terracota-fondo/40 px-2.5 py-1 text-xs font-medium text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                        <x-icono nombre="llave" class="h-3.5 w-3.5" /> Primer ingreso
                                    </span>
                                @else
                                    <span class="text-sm text-stone-400 dark:text-stone-500">—</span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-1.5 lg:w-[17rem] lg:shrink-0 lg:justify-end">
                                <x-gestion.boton variante="secundario" tamano="sm" icono="ojo" :href="route('admin.usuarios.show', $u)">Ver detalle</x-gestion.boton>
                                <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditar({{ $u->id }})">Editar</x-gestion.boton>
                                @if ($u->isNot(auth()->user()))
                                    <x-dropdown align="right" width="48">
                                        <x-slot name="trigger">
                                            <button type="button" aria-label="Más acciones para {{ $u->name }}"
                                                class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-stone-500 hover:bg-arena-claro hover:text-stone-700 dark:text-stone-400 dark:hover:bg-stone-700">
                                                <x-icono nombre="puntos" class="h-4 w-4" />
                                            </button>
                                        </x-slot>
                                        <x-slot name="content">
                                            <button type="button" wire:click="confirmarCambiarEstado({{ $u->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                                <x-icono :nombre="$u->activo ? 'prohibido' : 'refrescar'" class="h-4 w-4" />
                                                {{ $u->activo ? 'Desactivar' : 'Reactivar' }}
                                            </button>
                                            <hr class="my-1 border-arena-medio dark:border-stone-700">
                                            <button type="button" wire:click="confirmarEliminarCuenta({{ $u->id }})"
                                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-stone-700">
                                                <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                            </button>
                                        </x-slot>
                                    </x-dropdown>
                                @endif
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

    <script>
        function validarFormularioNuevoUsuario() {
            const dni = document.getElementById('nuevoDni')?.value ?? '';
            const contador = document.getElementById('nuevoDniContador');
            if (contador) contador.textContent = dni.length + '/8';

            const nombres = document.getElementById('nuevoNombres')?.value.trim() ?? '';
            const apellidos = document.getElementById('nuevoApellidos')?.value.trim() ?? '';
            const email = document.getElementById('nuevoEmail')?.value.trim() ?? '';
            const emailValido = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);

            const boton = document.getElementById('nuevoUsuarioBtnCrear');
            if (boton) boton.disabled = !(nombres.length >= 2 && apellidos.length >= 2 && dni.length === 8 && emailValido);
        }
    </script>

    @if ($mostrarModalCrear)
        <x-gestion.modal titulo="Nuevo estudiante" icono="usuario-mas" cerrar="$set('mostrarModalCrear', false)"
            descripcion="Completa los datos para registrar una cuenta de estudiante.">
            <p class="text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Datos personales</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Nombres" nombre="nuevoNombres" requerido>
                    <x-gestion.control id="nuevoNombres" icono="persona" wire:model="nuevoNombres" placeholder="María José" maxlength="60" autocomplete="off"
                        oninput="validarFormularioNuevoUsuario()" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Apellidos" nombre="nuevoApellidos" requerido>
                    <x-gestion.control id="nuevoApellidos" icono="persona" wire:model="nuevoApellidos" placeholder="García López" maxlength="60" autocomplete="off"
                        oninput="validarFormularioNuevoUsuario()" />
                </x-gestion.campo>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="DNI" nombre="nuevoDni" requerido>
                    <x-gestion.control id="nuevoDni" icono="identificacion" wire:model="nuevoDni" placeholder="12345678" inputmode="numeric" maxlength="8"
                        autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8); validarFormularioNuevoUsuario()" />
                    <p class="flex items-center justify-between text-xs text-stone-500 dark:text-stone-400">
                        <span>8 dígitos, solo números.</span>
                        <span id="nuevoDniContador">0/8</span>
                    </p>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Correo electrónico" nombre="nuevoEmail" requerido>
                    <x-gestion.control id="nuevoEmail" icono="sobre" type="email" wire:model="nuevoEmail" placeholder="correo@institucion.edu.pe"
                        oninput="validarFormularioNuevoUsuario()" />
                </x-gestion.campo>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Año de nacimiento" nombre="nuevoAnioNacimiento" ayuda="Opcional.">
                    <x-gestion.control id="nuevoAnioNacimiento" type="number" wire:model="nuevoAnioNacimiento" placeholder="2012" inputmode="numeric" maxlength="4" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Celular" nombre="nuevoCelular" ayuda="Opcional. 9 dígitos.">
                    <x-gestion.control id="nuevoCelular" wire:model="nuevoCelular" placeholder="987654321" inputmode="numeric" maxlength="9"
                        autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)" />
                </x-gestion.campo>
            </div>
            <x-gestion.campo etiqueta="Correo personal" nombre="nuevoCorreoPersonal" ayuda="Opcional. Contacto de respaldo.">
                <x-gestion.control id="nuevoCorreoPersonal" icono="sobre" type="email" wire:model="nuevoCorreoPersonal" placeholder="personal@correo.com" />
            </x-gestion.campo>

            <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Apoderado</p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Nombre del apoderado" nombre="nuevoApoderadoNombre" ayuda="Opcional.">
                    <x-gestion.control id="nuevoApoderadoNombre" icono="persona" wire:model="nuevoApoderadoNombre" placeholder="Rosa Vela" maxlength="120" autocomplete="off" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Celular del apoderado" nombre="nuevoApoderadoCelular" ayuda="Opcional. 9 dígitos.">
                    <x-gestion.control id="nuevoApoderadoCelular" wire:model="nuevoApoderadoCelular" placeholder="987654321" inputmode="numeric" maxlength="9"
                        autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)" />
                </x-gestion.campo>
            </div>

            <div class="flex items-start gap-3 rounded-xl border border-menta-base/30 bg-menta-fondo px-4 py-3 dark:border-menta-borde-oscuro dark:bg-menta-superficie-oscura">
                <x-icono nombre="llave" class="h-5 w-5 shrink-0 text-menta-oscuro dark:text-menta-base" />
                <p class="text-sm text-stone-700 dark:text-stone-200">
                    <span class="font-semibold">Contraseña predeterminada: el DNI del usuario.</span>
                    Se enviará un correo de bienvenida y el estudiante deberá cambiarla al ingresar por primera vez.
                </p>
            </div>

            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton id="nuevoUsuarioBtnCrear" wire:click="crearUsuario" wire:loading.attr="disabled" wire:target="crearUsuario" disabled>Crear estudiante</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalEditar)
        @include('livewire.usuarios._modal-editar')
    @endif

    @if ($mostrarConfirmacion)
        @include('livewire.usuarios._modal-confirmar')
    @endif
</div>
