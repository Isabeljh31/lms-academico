<div>
    <div class="mb-6 text-center">
        <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-amber-100 dark:bg-amber-900/30 mb-4">
            <svg class="h-7 w-7 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m0 0v2m0-2h2m-2 0H10m8-6V9a4 4 0 00-8 0v2M5 11h14a2 2 0 012 2v7a2 2 0 01-2 2H5a2 2 0 01-2-2v-7a2 2 0 012-2z" />
            </svg>
        </div>
        <h2 class="text-xl font-bold text-stone-800 dark:text-stone-100">Establece tu contraseña</h2>
        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
            Por seguridad, debes crear una contraseña personal antes de continuar.
        </p>
    </div>

    
    @if (session()->has('error'))
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
            {{ session('error') }}
        </div>
    @endif
    <form wire:submit="guardar" class="space-y-4">
        <div x-data="{ clave: '' }">
            <x-input-label for="password" value="Nueva contraseña" />
            <x-text-input wire:model="password" id="password" type="password" x-on:input="clave = $event.target.value"
                class="block mt-1 w-full" required autocomplete="new-password" />
            <x-requisitos-password />
            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password"
                class="block mt-1 w-full" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg border border-amber-200 dark:border-amber-700">
            <p class="text-xs text-amber-700 dark:text-amber-400 flex items-start gap-2">
                <svg class="h-4 w-4 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Tu contraseña temporal fue asignada por el Supervisor. Este cambio es obligatorio y no puede omitirse.
            </p>
        </div>

        <x-primary-button class="w-full justify-center mt-2">
            Establecer contraseña y continuar
        </x-primary-button>
    </form>
</div>
