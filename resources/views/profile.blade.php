<x-app-layout>
    <x-slot name="header">Perfil</x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="p-4 sm:p-8 bg-white dark:bg-stone-800 shadow-sm rounded-xl border border-arena-medio dark:border-stone-700">
                <livewire:profile.update-profile-information-form />
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-stone-800 shadow-sm rounded-xl border border-arena-medio dark:border-stone-700">
                <livewire:profile.update-password-form />
            </div>

            <div class="p-4 sm:p-8 bg-white dark:bg-stone-800 shadow-sm rounded-xl border border-arena-medio dark:border-stone-700">
                <livewire:profile.delete-user-form />
            </div>
        </div>
    </div>
</x-app-layout>
