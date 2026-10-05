<div class="py-8">
    <div class="mx-auto max-w-6xl space-y-6 px-4 sm:px-6 lg:px-8">

        <x-gestion.encabezado titulo="Gestión de usuarios" icono="usuarios"
            descripcion="Crea cuentas de Estudiante y Profesor, edita sus datos, asigna roles y controla el acceso.">
            <x-gestion.boton variante="secundario" icono="subir" :href="route('usuarios.importar')">Importar usuarios</x-gestion.boton>
            <x-gestion.boton icono="usuario-mas" wire:click="abrirCrear" id="btn-crear-usuario">Nuevo usuario</x-gestion.boton>
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
                                No se pudo enviar el correo de bienvenida: entrega esta contraseña al usuario.
                            @endif
                            No se volverá a mostrar. El usuario deberá cambiarla en su primer inicio de sesión.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <x-gestion.alertas />

        <x-gestion.tarjeta>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <x-gestion.campo etiqueta="Buscar" nombre="busqueda">
                    <x-gestion.control id="busqueda" type="search" wire:model.live.debounce.300ms="busqueda" placeholder="Nombre o correo" />
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
                                            <img src="{{ $usuario->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($usuario->name).'&background=7FB3AE&color=fff&size=36' }}"
                                                class="h-8 w-8 shrink-0 rounded-full object-cover" alt="">
                                            <div class="min-w-0">
                                                <p class="truncate font-medium text-stone-800 dark:text-stone-100">{{ $usuario->name }}</p>
                                                <p class="truncate text-xs text-stone-500 dark:text-stone-400">{{ $usuario->email }}</p>
                                            </div>
                                            @if ($usuario->primer_login)
                                                <span title="Aún no cambió su contraseña temporal"
                                                    class="inline-flex shrink-0 items-center gap-1 rounded-full bg-arena-claro px-2 py-0.5 text-xs font-medium text-terracota-oscuro dark:bg-stone-700 dark:text-terracota-fondo">
                                                    <x-icono nombre="llave" class="h-3 w-3" /> Primer ingreso
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        @if ($usuario->isNot(auth()->user()))
                                            <label class="sr-only" for="rol-{{ $usuario->id }}">Rol de {{ $usuario->name }}</label>
                                            <x-gestion.control tipo="select" id="rol-{{ $usuario->id }}" class="!w-auto py-1 text-xs"
                                                wire:change="actualizarRol({{ $usuario->id }}, $event.target.value)">
                                                @foreach ($roles as $r)
                                                    <option value="{{ $r->value }}" @selected($usuario->rolPrincipal() === $r->value)>{{ $r->etiqueta() }}</option>
                                                @endforeach
                                            </x-gestion.control>
                                        @else
                                            <span class="text-xs text-stone-500">{{ ucfirst($usuario->rolPrincipal() ?? '—') }} (tú)</span>
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
                                                @if ($usuario->activo)
                                                    <x-gestion.boton variante="fantasma-peligro" tamano="sm" icono="prohibido" wire:click="confirmarDesactivar({{ $usuario->id }})">Desactivar</x-gestion.boton>
                                                @else
                                                    <x-gestion.boton variante="fantasma" tamano="sm" icono="refrescar" wire:click="activarCuenta({{ $usuario->id }})">Reactivar</x-gestion.boton>
                                                @endif
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

    @if ($mostrarModalCrear)
        <x-gestion.modal titulo="Nuevo usuario" cerrar="$set('mostrarModalCrear', false)"
            descripcion="Se generará una contraseña temporal y se enviará un correo de bienvenida. El usuario deberá cambiarla en su primer inicio de sesión.">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-gestion.campo etiqueta="Nombres" nombre="nuevoNombres" requerido>
                    <x-gestion.control id="nuevoNombres" wire:model="nuevoNombres" placeholder="María" />
                </x-gestion.campo>
                <x-gestion.campo etiqueta="Apellidos" nombre="nuevoApellidos" requerido>
                    <x-gestion.control id="nuevoApellidos" wire:model="nuevoApellidos" placeholder="García López" />
                </x-gestion.campo>
            </div>
            <x-gestion.campo etiqueta="Correo electrónico" nombre="nuevoEmail" requerido>
                <x-gestion.control id="nuevoEmail" type="email" wire:model="nuevoEmail" placeholder="correo@institucion.edu.pe" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Rol" nombre="nuevoRol" requerido>
                <x-gestion.control tipo="select" id="nuevoRol" wire:model="nuevoRol">
                    @foreach ($rolesCreables as $r)
                        <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                    @endforeach
                </x-gestion.control>
            </x-gestion.campo>
            <x-slot name="pie">
                <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalCrear', false)">Cancelar</x-gestion.boton>
                <x-gestion.boton wire:click="crearUsuario" wire:loading.attr="disabled" wire:target="crearUsuario">Crear usuario</x-gestion.boton>
            </x-slot>
        </x-gestion.modal>
    @endif

    @if ($mostrarModalEditar)
        <x-gestion.modal titulo="Editar usuario" cerrar="$set('mostrarModalEditar', false)">
            <x-gestion.campo etiqueta="Nombre completo" nombre="editarNombre" requerido>
                <x-gestion.control id="editarNombre" wire:model="editarNombre" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Correo electrónico" nombre="editarEmail" requerido>
                <x-gestion.control id="editarEmail" type="email" wire:model="editarEmail" />
            </x-gestion.campo>
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
