<x-app-layout>
    <x-slot name="header">Panel</x-slot>

    @if (auth()->user()->hasRole('estudiante'))
        {{-- Rediseño visual del rol estudiante (ver ARQUITECTURA.md). El
             panel de profesor/administrador que sigue abajo no se tocó. --}}
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <livewire:estudiante.panel-inicio />
            </div>
        </div>
    @else
        <div class="py-8">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <div class="bg-white dark:bg-stone-800 overflow-hidden shadow-sm rounded-xl border border-arena-medio dark:border-stone-700">
                    <div class="p-6 text-stone-700 dark:text-stone-200">
                        <p class="text-lg">Hola, <span class="font-semibold">{{ auth()->user()->name }}</span> 👋</p>
                        <p class="text-sm text-stone-500 dark:text-stone-400 mt-1">
                            Rol: <x-badge-estado :estado="auth()->user()->rolPrincipal() ?? 'estudiante'" />
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif
</x-app-layout>
