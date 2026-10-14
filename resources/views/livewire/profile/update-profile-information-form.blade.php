<?php

use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

/**
 * Mi perfil. Los datos de identidad (nombres, apellidos, DNI y correo) se
 * muestran de solo lectura: solo el Supervisor los edita desde Usuarios, y el
 * cambio queda en la auditoría (ERS RF-14, RF-96). El usuario cambia su foto.
 */
new class extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?string $avatar_url = null;

    public ?UploadedFile $foto = null;

    public function mount(): void
    {
        $this->avatar_url = Auth::user()->avatar_url;
    }

    /**
     * Actualiza la foto de perfil del usuario autenticado.
     */
    public function updateProfileInformation(SubidorArchivos $subidor): void
    {
        $this->validate(
            ['foto' => ['required', 'image', 'max:4096']],
            [
                'foto.required' => 'Selecciona una imagen para tu foto de perfil.',
                'foto.image' => 'La foto debe ser una imagen (JPG, PNG, GIF o WEBP).',
                'foto.max' => 'La foto no puede pesar más de 4 MB.',
            ],
        );

        $user = Auth::user();

        $subidor->eliminar($user->avatar_url);
        $user->avatar_url = $subidor->subir($this->foto, 'avatars', 'foto');
        $user->save();

        $this->avatar_url = $user->avatar_url;
        $this->foto = null;

        $this->dispatch('profile-updated', name: $user->name, avatar_url: $user->avatar_url);
    }
}; ?>

<section>
    <header>
        <h2 class="text-lg font-medium text-stone-900 dark:text-stone-100">
            {{ __('Información de perfil') }}
        </h2>

        <p class="mt-1 text-sm text-stone-600 dark:text-stone-400">
            {{ __('Puedes cambiar tu foto. Si algún dato es incorrecto, solicita la corrección al Supervisor.') }}
        </p>
    </header>

    <form wire:submit="updateProfileInformation" class="mt-6 space-y-6">
        <div class="flex items-center gap-4">
            <img
                src="{{ $foto ? $foto->temporaryUrl() : ($avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode(auth()->user()->name).'&background=7FB3AE&color=fff') }}"
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

        @php($usuario = auth()->user())
        <dl class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            @foreach ([
                'Nombres' => $usuario->nombres,
                'Apellidos' => $usuario->apellidos,
                'DNI' => $usuario->dni,
                'Correo electrónico' => $usuario->email,
            ] as $etiqueta => $valor)
                <div>
                    <dt class="text-sm font-medium text-stone-700 dark:text-stone-300">{{ $etiqueta }}</dt>
                    <dd class="mt-1 rounded-md border border-stone-200 bg-stone-50 px-3 py-2 text-sm text-stone-800 dark:border-stone-700 dark:bg-stone-900 dark:text-stone-200">
                        {{ $valor ?: 'Sin registrar' }}
                    </dd>
                </div>
            @endforeach
        </dl>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Guardar foto') }}</x-primary-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Guardado.') }}
            </x-action-message>
        </div>
    </form>
</section>
