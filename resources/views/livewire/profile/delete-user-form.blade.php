<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    /**
     * Delete the currently authenticated user.
     */
    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }
}; ?>

<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            Eliminar cuenta
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            Una vez eliminada tu cuenta, todos sus recursos y datos se borrarán permanentemente. Antes de eliminarla, descarga cualquier información que quieras conservar.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Eliminar cuenta</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable>
        <form wire:submit="deleteUser" class="p-6">

            <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                ¿Seguro que quieres eliminar tu cuenta?
            </h2>

            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Una vez eliminada tu cuenta, todos sus recursos y datos se borrarán permanentemente. Ingresa tu contraseña para confirmar que quieres eliminarla de forma definitiva.
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="Contraseña" class="sr-only" />

                <x-text-input
                    wire:model="password"
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="Contraseña"
                />

                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    Cancelar
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    Eliminar cuenta
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
