<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Validate;
use Livewire\Form;

class LoginForm extends Form
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    /**
     * ERS RF-06: intentar autenticar al usuario.
     * Bloqueo 15 min tras 3 intentos fallidos.
     * ERS RF-05: redirección obligatoria si es primer login.
     */
    public function authenticate(): void
    {
        $this->validate();

        $user = User::where('email', $this->email)->first();

        // ERS RF-06: verificar bloqueo por intentos fallidos
        if ($user && $user->estaBloqueada()) {
            $minutosRestantes = (int) now()->diffInMinutes($user->bloqueado_hasta, false);
            throw ValidationException::withMessages([
                'form.email' => "Cuenta bloqueada temporalmente. Intente en {$minutosRestantes} minuto(s) o recupere su contraseña.",
            ]);
        }

        // ERS RF-15: cuenta desactivada
        if ($user && !$user->activo) {
            throw ValidationException::withMessages([
                'form.email' => 'Esta cuenta ha sido desactivada. Contacte al Supervisor.',
            ]);
        }

        if (!Auth::attempt($this->only(['email', 'password']), $this->remember)) {
            // ERS RF-06: registrar intento fallido
            if ($user) {
                $user->registrarIntentoFallido();
            }

            throw ValidationException::withMessages([
                'form.password' => trans('auth.failed'),
            ]);
        }

        // Login exitoso: limpiar intentos fallidos
        if ($user) {
            $user->limpiarIntentosFallidos();
        }

        Session::regenerate();
    }
}
