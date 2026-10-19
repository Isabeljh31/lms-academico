<?php

namespace App\Livewire\Auth;

use App\Models\BitacoraAuditoria;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-05: cambio obligatorio de contraseña en el primer inicio de sesión.
 * El sistema no permite acceder a ninguna otra sección hasta completar este
 * paso (ver App\Http\Middleware\PrimerLogin).
 */
#[Layout('layouts.guest')]
class CambiarPasswordPrimerLogin extends Component
{
    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        if (! auth()->user()?->primer_login) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function guardar(): void
    {
        $usuario = auth()->user();
        abort_unless($usuario?->primer_login, 403);

        $this->validate([
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
        ], [
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        if (Hash::check($this->password, $usuario->password)) {
            $this->addError('password', 'La nueva contraseña debe ser distinta de la contraseña temporal.');

            return;
        }

        $usuario->forceFill([
            'password' => $this->password,
            'primer_login' => false,
        ])->save();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $usuario,
            tipoEvento: 'cambio_password_inicial',
            descripcion: 'El usuario completó el cambio de contraseña obligatorio del primer ingreso.',
            usuarioAfectado: $usuario
        );

        $this->reset(['password', 'password_confirmation']);
        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.cambiar-password-primer-login')
            ->title('Establece tu contraseña — LearnUp');
    }
}
