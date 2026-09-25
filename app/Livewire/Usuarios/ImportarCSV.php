<?php

namespace App\Livewire\Usuarios;

use App\Mail\BienvenidaMail;
use App\Models\BitacoraAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ERS RF-11: Importación masiva de usuarios desde CSV.
 * ERS RF-12: Validación fila por fila con reporte de errores.
 * ERS RF-13: Reporte final: creados / omitidos (ya existían) / con error.
 *
 * Formato CSV esperado:
 *   nombre,correo,rol
 *   Ana García,ana@escuela.pe,estudiante
 *   Juan López,juan@escuela.pe,profesor
 */
#[Layout('layouts.app')]
class ImportarCSV extends Component
{
    use WithFileUploads;

    public $archivo = null;
    public bool $procesado = false;

    // Resultados
    public array $creados = [];
    public array $omitidos = [];
    public array $errores = [];
    public array $preview = [];

    public bool $mostrarPreview = false;

    public function updatedArchivo(): void
    {
        $this->procesado = false;
        $this->creados = $this->omitidos = $this->errores = $this->preview = [];
        $this->mostrarPreview = false;
        $this->previsualizar();
    }

    public function previsualizar(): void
    {
        if (!$this->archivo) return;

        $this->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $path = $this->archivo->getRealPath();
        $filas = $this->parsearCSV($path);
        $this->preview = array_slice($filas, 0, 10);
        $this->mostrarPreview = true;
    }

    public function importar(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $this->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:2048']]);

        $path = $this->archivo->getRealPath();
        $filas = $this->parsearCSV($path);

        $rolesPermitidos = ['estudiante', 'profesor', 'supervisor'];

        foreach ($filas as $i => $fila) {
            $linea = $i + 2; // +2 porque la fila 1 es el header

            // ERS RF-12: Validar campos obligatorios
            $nombre = trim($fila['nombre'] ?? '');
            $correo = trim($fila['correo'] ?? $fila['email'] ?? '');
            $rol    = strtolower(trim($fila['rol'] ?? 'estudiante'));

            if (!$nombre || !$correo) {
                $this->errores[] = [
                    'linea' => $linea,
                    'datos' => implode(', ', $fila),
                    'error' => 'Nombre o correo vacío.',
                ];
                continue;
            }

            if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                $this->errores[] = [
                    'linea' => $linea,
                    'datos' => "{$nombre} | {$correo}",
                    'error' => 'Correo electrónico inválido.',
                ];
                continue;
            }

            if (!in_array($rol, $rolesPermitidos)) {
                $this->errores[] = [
                    'linea' => $linea,
                    'datos' => "{$nombre} | {$correo}",
                    'error' => "Rol '{$rol}' no es válido. Usar: estudiante, profesor, supervisor.",
                ];
                continue;
            }

            // Verificar si ya existe
            if (User::where('email', $correo)->exists()) {
                $this->omitidos[] = [
                    'linea' => $linea,
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'razon'  => 'Ya existe una cuenta con este correo.',
                ];
                continue;
            }

            // Crear cuenta con contraseña temporal (ERS RF-03)
            $passwordTemporal = 'TMP-' . strtoupper(Str::random(8));

            try {
                $usuario = User::create([
                    'name'          => $nombre,
                    'email'         => $correo,
                    'password'      => Hash::make($passwordTemporal),
                    'primer_login'  => true,
                    'activo'        => true,
                    'email_verified_at' => now(),
                ]);

                $usuario->assignRole($rol);

                // ERS RF-04: enviar correo de bienvenida (si MAIL configurado)
                try {
                    Mail::to($usuario->email)->send(new BienvenidaMail($usuario, $passwordTemporal));
                } catch (\Exception $e) {
                    // Mail no fatal — se continúa aunque falle
                }

                // Auditoría
                BitacoraAuditoria::registrarCreacionCuenta(
                    ejecutor: auth()->user(),
                    usuario: $usuario
                );

                $this->creados[] = [
                    'nombre'   => $nombre,
                    'correo'   => $correo,
                    'rol'      => $rol,
                    'password' => $passwordTemporal,
                ];
            } catch (\Exception $e) {
                $this->errores[] = [
                    'linea' => $linea,
                    'datos' => "{$nombre} | {$correo}",
                    'error' => 'Error al crear la cuenta: ' . $e->getMessage(),
                ];
            }
        }

        $this->procesado = true;
        $this->archivo = null;
        $this->mostrarPreview = false;
    }

    private function parsearCSV(string $path): array
    {
        $filas = [];
        $handle = fopen($path, 'r');
        if (!$handle) return [];

        $headers = null;
        while (($row = fgetcsv($handle, 1000, ',')) !== false) {
            if ($headers === null) {
                $headers = array_map('strtolower', array_map('trim', $row));
                continue;
            }
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $filas[] = array_combine($headers, $row);
        }
        fclose($handle);
        return $filas;
    }

    public function render()
    {
        return view('livewire.usuarios.importar-csv');
    }
}
