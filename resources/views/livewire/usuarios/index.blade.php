<div class="py-8">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        {{-- Encabezado --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Gestión de Usuarios</h1>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Crea cuentas, asigna roles y gestiona el acceso a la plataforma.
                </p>
            </div>
            {{-- ERS RF-03 + RF-11: Crear usuario o importar CSV --}}
            <div class="flex items-center gap-2">
                <a href="{{ route('usuarios.importar') }}" wire:navigate
                    class="inline-flex items-center gap-2 rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm font-medium text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    ð¤ Importar CSV
                </a>
                <button wire:click="$set('mostrarModalCrear', true)"
                    id="btn-crear-usuario"
                    class="inline-flex items-center gap-2 rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-terracota-oscuro transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                </svg>
                Nuevo usuario
            </button>
            </div>
        </div>

        {{-- Flash: password temporal (ERS RF-03) --}}
        @if(session('password_temporal'))
        <div class="rounded-xl border border-amber-300 dark:border-amber-600 bg-amber-50 dark:bg-amber-900/20 p-4">
            <div class="flex items-start gap-3">
                <svg class="h-5 w-5 text-amber-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v2m0-2h2m-2 0H10m8-6V9a4 4 0 00-8 0v2M5 11h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2z" />
                </svg>
                <div>
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">
                        Cuenta "{{ session('usuario_temporal_nombre') }}" creada exitosamente
                    </p>
                    <p class="text-sm text-amber-700 dark:text-amber-400 mt-1">
                        Contraseña temporal: <code class="font-mono bg-amber-100 dark:bg-amber-900/40 px-2 py-0.5 rounded text-amber-900 dark:text-amber-200 font-bold">{{ session('password_temporal') }}</code>
                    </p>
                    <p class="text-xs text-amber-600 dark:text-amber-500 mt-1">
                        ⚠️ Anota esta contraseña ahora. No se volverá a mostrar. El usuario deberá cambiarla en su primer inicio de sesión.
                    </p>
                </div>
            </div>
        </div>
        @endif

        @if(session('estado') && !session('password_temporal'))
        <div class="rounded-lg bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-700 px-4 py-3 text-sm text-green-700 dark:text-green-400">
            {{ session('estado') }}
        </div>
        @endif

        {{-- Filtros --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl border border-stone-200 dark:border-stone-700 p-4">
            <div class="flex flex-wrap gap-3 items-end">
                <div class="flex-1 min-w-[200px]">
                    <label class="block text-xs text-stone-500 mb-1">Buscar</label>
                    <input wire:model.live.debounce.300ms="busqueda" type="search"
                        placeholder="Nombre o correo..."
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                </div>
                <div>
                    <label class="block text-xs text-stone-500 mb-1">Rol</label>
                    <select wire:model.live="filtroRol"
                        class="rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <option value="">Todos los roles</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs text-stone-500 mb-1">Estado</label>
                    <select wire:model.live="filtroEstado"
                        class="rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        <option value="">Todos</option>
                        <option value="activo">Activos</option>
                        <option value="inactivo">Desactivados</option>
                        <option value="nuevo">Primer login pendiente</option>
                    </select>
                </div>
            </div>
        </div>

        {{-- Tabla de usuarios --}}
        <div class="bg-white dark:bg-stone-800 rounded-xl shadow-sm border border-stone-200 dark:border-stone-700 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="border-b border-stone-200 dark:border-stone-700">
                    <tr class="text-left text-xs font-medium text-stone-500 dark:text-stone-400 uppercase tracking-wider">
                        <th class="px-4 py-3">Usuario</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-stone-100 dark:divide-stone-700/50">
                    @forelse($usuarios as $usuario)
                    <tr wire:key="usuario-{{ $usuario->id }}"
                        class="hover:bg-stone-50 dark:hover:bg-stone-700/30 transition-colors {{ !$usuario->activo ? 'opacity-60' : '' }}">

                        {{-- Nombre + email + flags --}}
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="{{ $usuario->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($usuario->name).'&background=7FB3AE&color=fff&size=36' }}"
                                    class="h-8 w-8 rounded-full object-cover shrink-0" alt="">
                                <div>
                                    <p class="font-medium text-stone-800 dark:text-stone-200">{{ $usuario->name }}</p>
                                    <p class="text-xs text-stone-400 dark:text-stone-500">{{ $usuario->email }}</p>
                                </div>
                                @if($usuario->primer_login)
                                <span class="ml-1 inline-flex items-center rounded-full bg-amber-100 dark:bg-amber-900/30 px-2 py-0.5 text-xs font-medium text-amber-700 dark:text-amber-400"
                                    title="Este usuario aún no cambió su contraseña temporal">
                                    🔑 1er login
                                </span>
                                @endif
                            </div>
                        </td>

                        {{-- Rol (editable) --}}
                        <td class="px-4 py-3">
                            @if($usuario->isNot(auth()->user()))
                            <select wire:change="actualizarRol({{ $usuario->id }}, $event.target.value)"
                                class="rounded-lg border border-stone-200 dark:border-stone-600 dark:bg-stone-800 dark:text-stone-200 text-xs px-2 py-1 focus:ring-terracota-base focus:border-terracota-base">
                                @foreach($roles as $r)
                                <option value="{{ $r->value }}" @selected($usuario->rolPrincipal() === $r->value)>
                                    {{ $r->etiqueta() }}
                                </option>
                                @endforeach
                            </select>
                            @else
                            <span class="text-xs text-stone-400">(tú) {{ ucfirst($usuario->rolPrincipal() ?? '—') }}</span>
                            @endif
                        </td>

                        {{-- Estado cuenta --}}
                        <td class="px-4 py-3">
                            @if($usuario->activo)
                                <span class="inline-flex items-center gap-1 rounded-full bg-green-100 dark:bg-green-900/30 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:text-green-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span> Activo
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-red-100 dark:bg-red-900/30 px-2.5 py-0.5 text-xs font-medium text-red-700 dark:text-red-400">
                                    <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span> Desactivado
                                </span>
                            @endif
                        </td>

                        {{-- Acciones --}}
                        <td class="px-4 py-3 text-right">
                            @if($usuario->isNot(auth()->user()))
                                @if($usuario->activo)
                                    <button wire:click="confirmarDesactivar({{ $usuario->id }})"
                                        class="text-xs text-red-500 hover:text-red-700 dark:hover:text-red-400 transition font-medium px-3 py-1 rounded-lg hover:bg-red-50 dark:hover:bg-red-900/20">
                                        Desactivar
                                    </button>
                                @else
                                    <button wire:click="activarCuenta({{ $usuario->id }})"
                                        class="text-xs text-green-600 hover:text-green-800 dark:hover:text-green-400 transition font-medium px-3 py-1 rounded-lg hover:bg-green-50 dark:hover:bg-green-900/20">
                                        Reactivar
                                    </button>
                                @endif
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-sm text-stone-400 dark:text-stone-500">
                            No se encontraron usuarios con los filtros seleccionados.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>

            <div class="px-4 py-3 border-t border-stone-100 dark:border-stone-700">
                {{ $usuarios->links() }}
            </div>
        </div>
    </div>

    {{-- Modal: Crear usuario (ERS RF-03) --}}
    @if($mostrarModalCrear)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-1">Nuevo usuario</h2>
            <p class="text-xs text-stone-500 dark:text-stone-400 mb-5">
                Se generará una contraseña temporal. El usuario deberá cambiarla en su primer inicio de sesión.
            </p>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">
                        Nombre completo <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="nuevoNombre" type="text" placeholder="ej. María García López"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                    @error('nuevoNombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">
                        Correo electrónico <span class="text-red-500">*</span>
                    </label>
                    <input wire:model="nuevoEmail" type="email" placeholder="correo@institucion.edu.pe"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                    @error('nuevoEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">
                        Rol <span class="text-red-500">*</span>
                    </label>
                    <select wire:model="nuevoRol"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                        @foreach($roles as $r)
                        <option value="{{ $r->value }}">{{ $r->etiqueta() }}</option>
                        @endforeach
                    </select>
                    @error('nuevoRol') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModalCrear', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="crearUsuario"
                    class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                    Crear usuario
                </button>
            </div>
        </div>
    </div>
    @endif

    {{-- Modal: Confirmar desactivación --}}
    @if($mostrarModalDesactivar)
    @php $uDes = $usuarios->find($usuarioADesactivarId) ?? \App\Models\User::find($usuarioADesactivarId); @endphp
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-red-100 dark:bg-red-900/30 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-stone-800 dark:text-stone-100">Desactivar cuenta</h3>
            <p class="text-sm text-stone-500 dark:text-stone-400 mt-2">
                ¿Desactivar la cuenta de <strong class="text-stone-800 dark:text-stone-200">{{ $uDes?->name }}</strong>?
                El usuario no podrá iniciar sesión pero su historial académico se conservará.
            </p>
            <div class="flex gap-3 mt-6">
                <button wire:click="$set('mostrarModalDesactivar', false)"
                    class="flex-1 rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="desactivarCuenta"
                    class="flex-1 rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition">
                    Sí, desactivar
                </button>
            </div>
        </div>
    </div>
    @endif
</div>
    {{-- Modal: Editar usuario (ERS RF-14) --}}
    @if($mostrarModalEditar)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm">
        <div class="bg-white dark:bg-stone-800 rounded-2xl shadow-2xl w-full max-w-md p-6">
            <h2 class="text-lg font-bold text-stone-800 dark:text-stone-100 mb-4">Editar datos del usuario</h2>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Nombre completo *</label>
                    <input wire:model="editarNombre" type="text"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                    @error('editarNombre') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="block text-sm font-medium text-stone-700 dark:text-stone-300 mb-1">Correo electrónico *</label>
                    <input wire:model="editarEmail" type="email"
                        class="w-full rounded-lg border border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-100 px-3 py-2 text-sm focus:ring-terracota-base focus:border-terracota-base">
                    @error('editarEmail') <p class="text-red-500 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
            <div class="flex justify-end gap-3 mt-6">
                <button wire:click="$set('mostrarModalEditar', false)"
                    class="rounded-lg border border-stone-300 dark:border-stone-600 px-4 py-2 text-sm text-stone-700 dark:text-stone-300 hover:bg-stone-50 dark:hover:bg-stone-700 transition">
                    Cancelar
                </button>
                <button wire:click="guardarEdicion"
                    class="rounded-lg bg-terracota-base px-4 py-2 text-sm font-semibold text-white hover:bg-terracota-oscuro transition">
                    Guardar cambios
                </button>
            </div>
        </div>
    </div>
    @endif

}