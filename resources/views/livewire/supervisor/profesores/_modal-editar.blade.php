<x-gestion.modal titulo="Editar profesor" icono="lapiz" cerrar="$set('mostrarModalEditar', false)">
    <form wire:submit="guardarEdicion" id="form-editar-profesor" class="space-y-4">
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
            <x-gestion.campo etiqueta="Año de nacimiento" nombre="editarAnioNacimiento" requerido>
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

        <p class="pt-1 text-xs font-semibold uppercase tracking-wide text-stone-400 dark:text-stone-500">Datos profesionales</p>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="Especialidad" nombre="editarEspecialidad" ayuda="Opcional.">
                <x-gestion.control id="editarEspecialidad" icono="birrete" wire:model="editarEspecialidad" placeholder="Matemática y Física" maxlength="120" autocomplete="off" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Grado académico" nombre="editarGradoAcademico" ayuda="Opcional.">
                <x-gestion.control id="editarGradoAcademico" icono="birrete" wire:model="editarGradoAcademico" placeholder="Magíster en Educación" maxlength="120" autocomplete="off" />
            </x-gestion.campo>
        </div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-gestion.campo etiqueta="N.° de colegiatura" nombre="editarColegiatura" ayuda="Opcional.">
                <x-gestion.control id="editarColegiatura" icono="identificacion" wire:model="editarColegiatura" maxlength="60" autocomplete="off" />
            </x-gestion.campo>
            <x-gestion.campo etiqueta="Fecha de inicio de labores" nombre="editarFechaInicioLabores" ayuda="Opcional.">
                <x-gestion.control id="editarFechaInicioLabores" type="date" wire:model="editarFechaInicioLabores" />
            </x-gestion.campo>
        </div>
        <x-gestion.campo etiqueta="CV" nombre="editarCv" ayuda="Opcional. Sube uno nuevo solo si quieres reemplazarlo.">
            <input type="file" id="editarCv" wire:model="editarCv" accept=".pdf,.doc,.docx"
                class="block w-full text-sm text-stone-600 file:mr-4 file:rounded-lg file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-white dark:text-stone-300">
            <div wire:loading wire:target="editarCv" class="mt-1 text-xs text-stone-500">Subiendo...</div>
        </x-gestion.campo>
    </form>
    <x-slot name="pie">
        <x-gestion.boton variante="secundario" wire:click="$set('mostrarModalEditar', false)">Cancelar</x-gestion.boton>
        <x-gestion.boton type="submit" form="form-editar-profesor" wire:loading.attr="disabled" wire:target="guardarEdicion">Guardar cambios</x-gestion.boton>
    </x-slot>
</x-gestion.modal>
