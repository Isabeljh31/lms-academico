<?php

namespace App\Livewire\Forms;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
     * ERS RF-01: autenticación por correo y contraseña.
     * ERS RF-06: bloqueo temporal tras N intentos fallidos consecutivos
     * (config/auth.php → bloqueo_login, por defecto 3 intentos / 15 minutos).
     * ERS RNF-05: los intentos fallidos se registran en el log.
     * ERS RF-15: una cuenta desactivada no puede ingresar. El aviso se da
     * solo tras validar la contraseña, para no revelar qué cuentas existen.
     */
    public function authenticate(): void
    {
        $this->validate();

        $user = User::where('email', $this->email)->first();

        if ($user?->estaBloqueada()) {
            $this->rechazarPorBloqueo($user);
        }

        if (! $user || ! Auth::validate(['email' => $this->email, 'password' => $this->password])) {
            $user?->registrarIntentoFallido();

            Log::warning('Inicio de sesión fallido', [
                'email' => $this->email,
                'ip' => request()->ip(),
                'bloqueada' => (bool) $user?->estaBloqueada(),
            ]);

            if ($user?->estaBloqueada()) {
                $this->rechazarPorBloqueo($user);
            }

            throw ValidationException::withMessages([
                'form.password' => trans('auth.failed'),
            ]);
        }

        if (! $user->activo) {
            throw ValidationException::withMessages([
                'form.email' => 'Esta cuenta ha sido desactivada. Contacta al Supervisor.',
            ]);
        }

        Auth::login($user, $this->remember);
        $user->limpiarIntentosFallidos();

        Session::regenerate();
    }

    protected function rechazarPorBloqueo(User $user): never
    {
        $minutos = max(1, (int) ceil(now()->diffInSeconds($user->bloqueado_hasta, false) / 60));

        throw ValidationException::withMessages([
            'form.email' => "Cuenta bloqueada temporalmente por intentos fallidos. Intenta de nuevo en {$minutos} minuto(s) o usa \"¿Olvidaste tu contraseña?\".",
        ]);
    }
}
