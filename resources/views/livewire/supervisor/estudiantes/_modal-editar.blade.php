<x-gestion.modal titulo="Editar estudiante" icono="lapiz" cerrar="$set('mostrarModalEditar', false)">
    <form wire:submit="guardarEdicion" id="form-editar-estudiante" class="space-y-4">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Nombres" nombre="editarNombres" requerido>
                <x-gestion.control id="editarNombres" icono="persona" wire:model="editarNombres" maxlength="60" autocomplete="off" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Apellidos" nombre="editarApellidos" requerido>
                <x-gestion.control id="editarApellidos" icono="persona" wire:model="editarApellidos" maxlength="60" autocomplete="off" />
            </x-gestion.campo>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="DNI" nombre="editarDni" requerido>
                <x-gestion.control id="editarDni" icono="identificacion" wire:model="editarDni" inputmode="numeric" maxlength="8"
                    autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 8)" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Correo institucional" nombre="editarEmail" requerido>
                <x-gestion.control id="editarEmail" icono="sobre" type="email" wire:model="editarEmail" />
            </x-gestion.campo>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Año de nacimiento" nombre="editarAnioNacimiento" ayuda="Opcional.">
                <x-gestion.control id="editarAnioNacimiento" type="number" wire:model="editarAnioNacimiento" inputmode="numeric" maxlength="4" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Celular" nombre="editarCelular" ayuda="Opcional. 9 dígitos.">
                <x-gestion.control id="editarCelular" wire:model="editarCelular" inputmode="numeric" maxlength="9"
                    autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)" />
            </x-gestion.campo>
        </div>
        <x-gestion.campo etiqueta="Correo personal" nombre="editarCorreoPersonal" ayuda="Opcional.">
            <x-gestion.control id="editarCorreoPersonal" icono="sobre" type="email" wire:model="editarCorreoPersonal" />
        </x-gestion.campo>

        <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Apoderado</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Nombre del apoderado" nombre="editarApoderadoNombre" ayuda="Opcional.">
                <x-gestion.control id="editarApoderadoNombre" icono="persona" wire:model="editarApoderadoNombre" maxlength="120" autocomplete="off" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Celular del apoderado" nombre="editarApoderadoCelular" ayuda="Opcional. 9 dígitos.">
                <x-gestion.control id="editarApoderadoCelular" wire:model="editarApoderadoCelular" inputmode="numeric" maxlength="9"
                    autocomplete="off" oninput="this.value = this.value.replace(/\D/g, '').slice(0, 9)" />
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
        @endif
    </form>
    <x-slot name="pie">
        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalEditar', false)">Cancelar</x-gestion.boton>
        <x-gestion.boton type="submit" form="form-editar-estudiante" wire:loading.attr="disabled" wire:target="guardarEdicion">Guardar cambios</x-gestion.boton>
    </x-slot>
</x-gestion.modal>
