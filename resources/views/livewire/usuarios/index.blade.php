<div class="py-8">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Usuarios</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Gestiona las cuentas de la plataforma y su rol.</p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">
                {{ session('estado') }}
            </div>
        @endif

        <div class="bg-white dark:bg-stone-800 rounded-xl shadow-sm border border-arena-medio dark:border-stone-700">
            <div class="p-4 border-b border-arena-medio dark:border-stone-700">
                <input wire:model.live.debounce.300ms="busqueda" type="search" placeholder="Buscar por nombre o correo…"
                    class="w-full sm:w-80 rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 focus:border-menta-base focus:ring-menta-base text-sm">
            </div>

            <table class="w-full text-sm">
                <thead class="text-left text-stone-500 dark:text-stone-400 uppercase text-xs">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Correo</th>
                        <th class="px-4 py-3">Rol</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-arena-medio dark:divide-stone-700">
                    @foreach ($usuarios as $usuario)
                        <tr wire:key="usuario-{{ $usuario->id }}">
                            <td class="px-4 py-3 text-stone-800 dark:text-stone-100">{{ $usuario->name }}</td>
                            <td class="px-4 py-3 text-stone-500 dark:text-stone-400">{{ $usuario->email }}</td>
                            <td class="px-4 py-3">
                                <x-badge-estado :estado="$usuario->rolPrincipal() ?? 'sin_rol'" />
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if ($usuario->isNot(auth()->user()))
                                    <select
                                        wire:change="actualizarRol({{ $usuario->id }}, $event.target.value)"
                                        class="rounded-md border-stone-300 dark:border-stone-600 dark:bg-stone-900 dark:text-stone-200 text-xs focus:border-menta-base focus:ring-menta-base">
                                        @foreach ($roles as $rol)
                                            <option value="{{ $rol->value }}" @selected($usuario->rolPrincipal() === $rol->value)>
                                                {{ $rol->etiqueta() }}
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <span class="text-xs text-stone-400">Tú</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div class="p-4">
                {{ $usuarios->links() }}
            </div>
        </div>
    </div>
</div>
