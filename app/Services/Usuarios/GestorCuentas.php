<?php

namespace App\Services\Usuarios;

use App\Enums\Rol;
use App\Mail\BienvenidaMail;
use App\Models\BitacoraAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Alta de cuentas (ERS 3.2). Es el único punto de creación de usuarios para
 * que todas las vías (alta individual, importación masiva, directorio de
 * profesores y API de integración) cumplan las mismas reglas:
 *
 * - RF-03: contraseña temporal generada por el sistema.
 * - RF-04: correo de bienvenida con la contraseña temporal y el enlace.
 * - RF-05 / regla de negocio 2: cambio obligatorio en el primer ingreso.
 * - RF-96: la creación queda en la bitácora de auditoría.
 */
class GestorCuentas
{
    /**
     * Roles que el Supervisor puede asignar al crear una cuenta (RF-10).
     *
     * @var array<int, string>
     */
    public const ROLES_CREABLES = ['estudiante', 'profesor'];

    /**
     * @return array{usuario: User, password_temporal: string, correo_enviado: bool}
     */
    public function crear(string $nombre, string $email, string $rol, ?User $ejecutor, string $origen = 'manual'): array
    {
        $rol = Rol::from($rol)->value;
        $passwordTemporal = $this->generarPasswordTemporal();

        $usuario = new User([
            'name' => trim($nombre),
            'email' => strtolower(trim($email)),
            'password' => $passwordTemporal,
            'activo' => true,
            'primer_login' => true,
        ]);
        $usuario->email_verified_at = now();
        $usuario->save();
        $usuario->assignRole($rol);

        $correoEnviado = $this->enviarBienvenida($usuario, $passwordTemporal);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: $ejecutor,
            tipoEvento: 'creacion_cuenta',
            descripcion: "Cuenta creada ({$origen}): {$usuario->name} ({$usuario->email}) con rol {$rol}.",
            usuarioAfectado: $usuario,
        );

        return [
            'usuario' => $usuario,
            'password_temporal' => $passwordTemporal,
            'correo_enviado' => $correoEnviado,
        ];
    }

    public function generarPasswordTemporal(): string
    {
        return Str::password(12, symbols: false);
    }

    /**
     * El fallo del servicio de correo no impide crear la cuenta: el
     * Supervisor ve la contraseña temporal en pantalla y puede entregarla.
     */
    protected function enviarBienvenida(User $usuario, string $passwordTemporal): bool
    {
        try {
            Mail::to($usuario->email)->send(new BienvenidaMail($usuario, $passwordTemporal));

            return true;
        } catch (Throwable $e) {
            Log::warning('No se pudo enviar el correo de bienvenida', [
                'usuario_id' => $usuario->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
