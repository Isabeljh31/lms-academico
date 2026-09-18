<?php

use App\Models\User;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $name = '';
    public string $email = '';

    #[Locked]
    public ?string $avatar_url = null;

    public ?UploadedFile $foto = null;

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $this->name = Auth::user()->name;
        $this->email = Auth::user()->email;
        $this->avatar_url = Auth::user()->avatar_url;
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(SubidorArchivos $subidor): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'foto' => ['nullable', 'image', 'max:4096'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
        ]);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        if ($this->foto) {
            $subidor->eliminar($user->avatar_url);
            $user->avatar_url = $subidor->subir($this->foto, 'avatars');
        }

        $user->save();

        $this->avatar_url = $user->avatar_url;
        $this->foto = null;

        $this->dispatch('profile-updated', name: $user->name, avatar_url: $user->avatar_url);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-stone-900 dark:text-stone-100">
            {{ __('Información de perfil') }}
        </h2>

        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            {{ __('Actualiza tu foto, nombre y correo electrónico.') }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div class="flex items-center gap-4">
            <img
                src="{{ $foto ? $foto->temporaryUrl() : ($avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($name).'&background=7FB3AE&color=fff') }}"
                alt="{{ __('Foto de perfil') }}"
                class="h-16 w-16 rounded-full object-cover ring-2 ring-menta-base"
            >

            <div class="flex-1">
                <x-input-label for="foto" :value="__('Foto de perfil')" />
                <input wire:model="foto" id="foto" type="file" accept="image/*"
                    class="mt-1 block w-full text-sm text-stone-600 dark:text-stone-400 file:me-4 file:rounded-md file:border-0 file:bg-menta-oscuro file:px-3 file:py-1.5 file:text-white file:text-sm hover:file:bg-menta-oscuro/90" />
                <div wire:loading wire:target="foto" class="text-xs text-stone-500 mt-1">{{ __('Subiendo…') }}</div>
                <x-input-error class="mt-2" :messages="$errors->get('foto')" />
            </div>
        </div>

        <div>
            <x-input-label for="name" :value="__('Nombre')" />
            <x-text-input wire:model="name" id="name" name="name" type="text" class="mt-1 block w-full" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Correo electrónico')" />
            <x-text-input wire:model="email" id="email" name="email" type="email" class="mt-1 block w-full" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-stone-800 dark:text-stone-200">
                        {{ __('Tu correo electrónico no está verificado.') }}

                        <button wire:click.prevent="sendVerification" class="underline text-sm text-stone-600 dark:text-stone-400 hover:text-stone-900 dark:hover:text-stone-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-menta-base">
                            {{ __('Haz clic aquí para reenviar el correo de verificación.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-exito-base">
                            {{ __('Se ha enviado un nuevo enlace de verificación a tu correo.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Guardado.') }}
            </x-action-message>
        </div>
    </form>
</section>
