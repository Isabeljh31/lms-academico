<x-gestion.modal titulo="{{ $confirmacionTitulo }}" cerrar="cerrarConfirmacion()">
    <p class="text-sm text-stone-700 dark:text-stone-200">{!! $confirmacionMensaje !!}</p>
    <x-slot name="pie">
        <x-gestion.boton variante="secundario" wire:click="cerrarConfirmacion">Cancelar</x-gestion.boton>
        <x-gestion.boton :variante="$confirmacionVariante" wire:click="ejecutarConfirmacion"
            wire:loading.attr="disabled" wire:target="ejecutarConfirmacion">{{ $confirmacionTextoBoton }}</x-gestion.boton>
    </x-slot>
</x-gestion.modal>
