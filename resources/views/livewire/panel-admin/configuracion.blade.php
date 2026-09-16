<div class="py-8">
    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div>
            <h1 class="text-2xl font-bold text-stone-900 dark:text-stone-100">Configuración general</h1>
            <p class="text-sm text-stone-500 dark:text-stone-400">Ajustes que afectan a toda la plataforma.</p>
        </div>

        @if (session('estado'))
            <div class="rounded-lg bg-exito-base/15 text-exito-oscuro px-4 py-3 text-sm">{{ session('estado') }}</div>
        @endif

        <div class="bg-white dark:bg-stone-800 rounded-xl border border-arena-medio dark:border-stone-700 p-6 flex items-center justify-between">
            <div>
                <h2 class="font-medium text-stone-800 dark:text-stone-100">Bot de sugerencias</h2>
                <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                    Al desactivarlo, el widget de chat deja de estar disponible para los 3 roles en toda la plataforma.
                </p>
            </div>

            <button wire:click="alternarBot"
                class="relative inline-flex h-7 w-12 items-center rounded-full transition {{ $botActivo ? 'bg-menta-oscuro' : 'bg-stone-300 dark:bg-stone-600' }}">
                <span class="inline-block h-5 w-5 transform rounded-full bg-white transition {{ $botActivo ? 'translate-x-6' : 'translate-x-1' }}"></span>
            </button>
        </div>
    </div>
</div>
