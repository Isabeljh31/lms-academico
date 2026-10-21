<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Gestión de usuarios" icono="usuarios"
            descripcion="Crea cuentas de Estudiante y Profesor, edita sus datos y controla el acceso.">
            <x-gestion.boton variante="secundario" icono="subir" :href="route('usuarios.importar')">Importar usuarios</x-gestion.boton>
            <x-gestion.boton icono="usuario-mas" wire:click="abrirCrear" id="btn-crear-usuario">Nuevo usuario</x-gestion.boton>
        </x-gestion.encabezado>

        <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <button type="button" wire:click="filtrarRapido" wire:loading.attr="disabled" wire:target="filtrarRapido"
                class="flex items-center gap-3 rounded-xl border bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md dark:bg-stone-800 {{ $filtroRol === '' && $filtroEstado === '' ? 'border-menta-base ring-2 ring-menta-base/25' : 'border-arena-medio dark:border-stone-700' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-menta-fondo text-menta-oscuro dark:bg-stone-700 dark:text-menta-base">
                    <x-icono nombre="usuarios" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-2xl font-bold leading-none text-stone-800 dark:text-stone-100">{{ $totalUsuarios }}</span>
                    <span class="block mt-1 text-xs text-stone-500 dark:text-stone-400">Usuarios</span>
                </span>
            </button>
            <button type="button" wire:click="filtrarRapido('estudiante')" wire:loading.attr="disabled" wire:target="filtrarRapido"
                class="flex items-center gap-3 rounded-xl border bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md dark:bg-stone-800 {{ $filtroRol === 'estudiante' ? 'border-azul-oscuro ring-2 ring-azul-oscuro/25' : 'border-arena-medio dark:border-stone-700' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-azul-oscuro/10 text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo">
                    <x-icono nombre="birrete" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-2xl font-bold leading-none text-stone-800 dark:text-stone-100">{{ $totalEstudiantes }}</span>
                    <span class="block mt-1 text-xs text-stone-500 dark:text-stone-400">Estudiantes</span>
                </span>
            </button>
            <button type="button" wire:click="filtrarRapido('profesor')" wire:loading.attr="disabled" wire:target="filtrarRapido"
                class="flex items-center gap-3 rounded-xl border bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md dark:bg-stone-800 {{ $filtroRol === 'profesor' ? 'border-lavanda-oscuro ring-2 ring-lavanda-oscuro/25' : 'border-arena-medio dark:border-stone-700' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-lavanda-fondo/40 text-lavanda-oscuro dark:bg-lavanda-oscuro/25 dark:text-lavanda-fondo">
                    <x-icono nombre="maletin" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-2xl font-bold leading-none text-stone-800 dark:text-stone-100">{{ $totalProfesores }}</span>
                    <span class="block mt-1 text-xs text-stone-500 dark:text-stone-400">Profesores</span>
                </span>
            </button>
            <button type="button" wire:click="filtrarRapido('', 'nuevo')" wire:loading.attr="disabled" wire:target="filtrarRapido"
                class="flex items-center gap-3 rounded-xl border bg-white px-4 py-3 text-left transition hover:-translate-y-0.5 hover:shadow-md dark:bg-stone-800 {{ $filtroEstado === 'nuevo' ? 'border-terracota-base ring-2 ring-terracota-base/25' : 'border-arena-medio dark:border-stone-700' }}">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-terracota-fondo/40 text-terracota-oscuro dark:bg-terracota-oscuro/25 dark:text-terracota-fondo">
                    <x-icono nombre="llave" class="h-4.5 w-4.5" />
                </span>
                <span class="min-w-0">
                    <span class="block text-2xl font-bold leading-none text-stone-800 dark:text-stone-100">{{ $totalPrimerIngreso }}</span>
                    <span class="block mt-1 text-xs text-stone-500 dark:text-stone-400">Primer ingreso pendiente</span>
                </span>
            </button>
        </div>

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
                                No se pudo enviar el correo de bienvenida: entrega esta contraseña al usuario.
                            @endif
                            No se volverá a mostrar. El usuario deberá cambiarla en su primer inicio de sesión.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <x-gestion.alertas />
        <x-input-error :messages="$errors->get('eliminar')" />

        <x-gestion.tarjeta>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                    <x-gestion.control id="busqueda" icono="lupa" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Nombre, correo o DNI" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Rol" nombre="filtroRol">
                    <x-gestion.control tipo="select" id="filtroRol" wire:model.live="filtroRol">
                        <option value="">Todos los roles</option>
                        @foreach ($roles as $r)
                            <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Estado" nombre="filtroEstado">
                    <x-gestion.control tipo="select" id="filtroEstado" wire:model.live="filtroEstado">
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Desactivados</option>
                        <option value="nuevo">Primer ingreso pendiente</option>
                    </x-gestion.control>
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Curso" nombre="filtroCursoId">
                    <x-gestion.control tipo="select" id="filtroCursoId" wire:model.live="filtroCursoId">
                        <option value="">Todos los cursos</option>
                        @foreach ($cursos as $curso)
                            <option value="{{ $curso->id }}">{{ $curso->nombre }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
            </div>
            <p class="mt-3 text-xs text-stone-500 dark:text-stone-400">Mostrando {{ $usuarios->count() }} de {{ $usuarios->total() }}</p>
        </x-gestion.tarjeta>

        <x-gestion.tarjeta :relleno="false">
            @if ($usuarios->isEmpty())
                <div class="p-4">
                    <x-gestion.vacio icono="usuarios" titulo="No se encontraron usuarios"
                        descripcion="Ajusta los filtros o crea una cuenta nueva." />
                </div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-sm">
                        <thead class="border-b border-arena-medio bg-arena-claro/50 dark:border-stone-700 dark:bg-stone-900/40">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-stone-500 dark:text-stone-400">
                                <th class="px-4 py-3">Usuario</th>
                                <th class="px-4 py-3">Rol</th>
                                <th class="px-4 py-3">Estado</th>
                                <th class="px-4 py-3 text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-arena-medio/60 dark:divide-stone-700">
                            @foreach ($usuarios as $usuario)
                                <tr wire:key="usuario-{{ $usuario->id }}" class="hover:bg-arena-claro/40 dark:hover:bg-stone-700/40 {{ $usuario->activo ? '' : 'opacity-70' }}">
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-3">
                                            <img src="{{ $usuario->fotoUrl() }}"
                                                class="h-9 w-9 shrink-0 rounded-full object-cover" alt="">
                                            <div class="min-w-0">
                                                <p class="flex flex-wrap items-center gap-1.5 truncate font-medium text-stone-800 dark:text-stone-100">
                                                    {{ $usuario->nombreFormal() }}
                                                    @if ($usuario->primer_login)
                                                        <span title="Aún no cambió su contraseña temporal"
                                                            class="inline-flex shrink-0 items-center gap-1 rounded-full bg-terracota-fondo/40 px-2 py-0.5 text-xs font-medium text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                                            <x-icono nombre="llave" class="h-3 w-3" /> Primer ingreso
                                                        </span>
                                                    @endif
                                                </p>
                                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 truncate text-xs text-stone-500 dark:text-stone-400">
                                                    <span class="truncate">{{ $usuario->email }}</span>
                                                    @if ($usuario->dni)
                                                        <span>DNI {{ $usuario->dni }}</span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                                            <x-icono nombre="alerta" class="h-3 w-3" /> DNI sin registrar
                                                        </span>
                                                    @endif
                                                </p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        {{-- Cada cuenta tiene un único rol; solo se corrige desde "Editar" si aún no tiene historial. --}}
                                        @php $rol = \App\Enums\Rol::tryFrom((string) $usuario->rolPrincipal()); @endphp
                                        @if ($rol === \App\Enums\Rol::Profesor)
                                            <span class="inline-flex rounded-full bg-lavanda-fondo/40 px-2.5 py-0.5 text-xs font-medium text-lavanda-oscuro dark:bg-lavanda-oscuro/25 dark:text-lavanda-fondo">{{ $rol->etiqueta() }}</span>
                                        @elseif ($rol === \App\Enums\Rol::Estudiante)
                                            <span class="inline-flex rounded-full bg-azul-oscuro/10 px-2.5 py-0.5 text-xs font-medium text-azul-oscuro dark:bg-azul-oscuro/20 dark:text-azul-fondo">{{ $rol->etiqueta() }}</span>
                                        @else
                                            <span class="inline-flex rounded-full bg-arena-claro px-2.5 py-0.5 text-xs font-medium text-stone-700 dark:bg-stone-700 dark:text-stone-200">{{ $rol?->etiqueta() ?? '-' }}</span>
                                        @endif
                                        @if ($usuario->is(auth()->user()))
                                            <span class="text-xs text-stone-500">(tú)</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($usuario->activo)
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-exito-base/15 px-2.5 py-0.5 text-xs font-medium text-exito-oscuro dark:text-green-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-exito-base"></span> Activo
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1.5 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-950/40 dark:text-red-300">
                                                <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Desactivado
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-1">
                                            <x-gestion.boton variante="fantasma" tamano="sm" icono="lapiz" wire:click="abrirEditar({{ $usuario->id }})">Editar</x-gestion.boton>
                                            @if ($usuario->isNot(auth()->user()))
                                                <x-dropdown align="right" width="48">
                                                    <x-slot name="trigger">
                                                        <button type="button" aria-label="Más acciones para {{ $usuario->name }}"
                                                            class="inline-flex h-8 w-8 items-center justify-center rounded-lg text-stone-500 hover:bg-arena-claro hover:text-stone-700 dark:text-stone-400 dark:hover:bg-stone-700">
                                                            <x-icono nombre="puntos" class="h-4 w-4" />
                                                        </button>
                                                    </x-slot>
                                                    <x-slot name="content">
                                                        @if ($usuario->activo)
                                                            <button type="button" wire:click="confirmarDesactivar({{ $usuario->id }})"
                                                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                                                <x-icono nombre="prohibido" class="h-4 w-4" /> Desactivar
                                                            </button>
                                                        @else
                                                            <button type="button" wire:click="activarCuenta({{ $usuario->id }})"
                                                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-stone-700 hover:bg-arena-claro dark:text-stone-200 dark:hover:bg-stone-700">
                                                                <x-icono nombre="refrescar" class="h-4 w-4" /> Reactivar
                                                            </button>
                                                        @endif
                                                        @unless ($usuario->hasRole('supervisor'))
                                                            <hr class="my-1 border-arena-medio dark:border-stone-700">
                                                            <button type="button" wire:click="eliminarCuenta({{ $usuario->id }})"
                                                                wire:confirm="¿Eliminar definitivamente a {{ $usuario->name }}? Esta acción no se puede deshacer. Las cuentas con historial académico deben desactivarse."
                                                                class="flex w-full items-center gap-2 px-4 py-2 text-start text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-stone-700">
                                                                <x-icono nombre="papelera" class="h-4 w-4" /> Eliminar
                                                            </button>
                                                        @endunless
                                                    </x-slot>
                                                </x-dropdown>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="border-t border-arena-medio px-4 py-3 dark:border-stone-700">
                    {{ $usuarios->links() }}
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
        <x-gestion.modal titulo="Nuevo usuario" icono="usuario-mas" cerrar="$set('mostrarModalCrear', false)"
            descripcion="Completa los datos para registrar una cuenta en la plataforma.">
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

            <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Acceso</p>
            <x-gestion.campo etiqueta="Rol" nombre="nuevoRol" requerido>
                <x-gestion.control tipo="select" id="nuevoRol" icono="usuarios" wire:model="nuevoRol">
                    @foreach ($rolesCreables as $r)
                        <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>

            <div class="flex items-start gap-3 rounded-xl border border-menta-base/30 bg-menta-fondo px-4 py-3 dark:border-menta-borde-oscuro dark:bg-menta-superficie-oscura">
                <x-icono nombre="llave" class="h-5 w-5 shrink-0 text-menta-oscuro dark:text-menta-base" />
                <p class="text-sm text-stone-700 dark:text-stone-200">
                    <span class="font-semibold">Contraseña predeterminada: el DNI del usuario.</span>
                    Se enviará un correo de bienvenida y el usuario deberá cambiarla al ingresar por primera vez.
                </p>
            </div>

            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton id="nuevoUsuarioBtnCrear" wire:click="crearUsuario" wire:loading.attr="disabled" wire:target="crearUsuario" disabled>Crear usuario</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalEditar)
        <x-gestion.modal titulo="Editar usuario" cerrar="$set('mostrarModalEditar', false)">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Nombres" nombre="editarNombres" requerido>
                    <x-gestion.control id="editarNombres" wire:model="editarNombres" maxlength="60" autocomplete="off" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Apellidos" nombre="editarApellidos" requerido>
                    <x-gestion.control id="editarApellidos" wire:model="editarApellidos" maxlength="60" autocomplete="off" />
                </x-gestion.campo>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="DNI" nombre="editarDni" requerido ayuda="8 dígitos, solo números.">
                    <x-gestion.control id="editarDni" wire:model="editarDni" inputmode="numeric" maxlength="8"
                        autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Correo electrónico" nombre="editarEmail" requerido>
                    <x-gestion.control id="editarEmail" type="email" wire:model="editarEmail" />
                </x-gestion.campo>
            </div>
            @if ($usuarioEditar && auth()->user()->can('corregirRol', $usuarioEditar))
                <x-gestion.campo etiqueta="Rol" nombre="editarRol"
                    ayuda="Puedes corregir el rol solo porque esta cuenta aún no tiene historial académico. Una vez que se use en un curso, el rol queda fijo.">
                    <x-gestion.control tipo="select" id="editarRol" wire:model="editarRol">
                        @foreach ($rolesCreables as $r)
                            <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                        @endforeach
                    </x-gestion.control>
                </x-gestion.campo>
            @elseif ($usuarioEditar)
                <div class="space-y-1">
                    <p class="text-sm font-medium text-stone-700 dark:text-stone-300">Rol</p>
                    <p class="text-sm text-stone-800 dark:text-stone-100">{{ \App\Enums\Rol::tryFrom((string) $usuarioEditar->rolPrincipal())?->etiqueta() ?? '-' }}</p>
                    <p class="text-xs text-stone-500 dark:text-stone-400">Cada cuenta tiene un único rol. No se puede cambiar porque la cuenta ya tiene historial académico o es tu propia cuenta.</p>
                </div>
            @endif
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalEditar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton wire:click="guardarEdicion">Guardar cambios</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalDesactivar && $usuarioADesactivar)
        <x-gestion.modal titulo="Desactivar cuenta" cerrar="$set('mostrarModalDesactivar', false)" ancho="max-w-md">
            <p class="text-sm text-stone-600 dark:text-stone-300">
                ¿Desactivar la cuenta de <strong class="text-stone-900 dark:text-stone-100">{{ $usuarioADesactivar->name }}</strong>?
                No podrá iniciar sesión, pero su historial académico se conserva y la cuenta puede reactivarse.
            </p>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalDesactivar', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton variante="peligro" icono="prohibido" wire:click="desactivarCuenta">Sí, desactivar</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif
</div>
