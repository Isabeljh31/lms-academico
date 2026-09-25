<?php

namespace App\Livewire\Auth;

use App\Models\BitacoraAuditoria;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-05: cambio obligatorio de contraseña en el primer inicio de sesión.
 * El sistema no permite acceder a ninguna otra sección hasta completar este paso.
 */
#[Layout('layouts.guest')]
class CambiarPasswordPrimerLogin extends Component
{
    public string $password = '';
    public string $password_confirmation = '';

    public function mount(): void
    {
        if (!auth()->user()?->primer_login) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function guardar()
    {
        \Illuminate\Support\Facades\Log::info('Guardar invocado', ['password' => $this->password]);

        $this->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        \Illuminate\Support\Facades\Log::info('Validacion exitosa');

        try {
            $user = auth()->user();
            $user->update([
                'password'    => Hash::make($this->password),
                'primer_login' => false,
            ]);

            // ERS RF-95: auditar el cambio de contraseña inicial
            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: $user,
                tipoEvento: 'otro_admin',
                descripcion: 'El usuario completó el cambio de contraseña obligatorio en primer login.',
                usuarioAfectado: $user
            );

            \Illuminate\Support\Facades\Log::info('Actualizacion y auditoria exitosa, redirigiendo a dashboard');
            return $this->redirect(route('dashboard'), navigate: false);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error en guardar: ' . $e->getMessage());
            session()->flash('error', 'Error interno: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.auth.cambiar-password-primer-login')
            ->title('Establece tu contraseña — LearnUp');
    }
}
