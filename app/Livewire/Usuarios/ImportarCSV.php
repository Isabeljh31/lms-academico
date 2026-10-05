<?php

namespace App\Livewire\Usuarios;

use App\Models\User;
use App\Services\HojasCalculo\LectorXlsx;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

/**
 * ERS RF-11: importación masiva de usuarios desde un libro de Excel (.xlsx)
 *            o un archivo CSV (separador coma o punto y coma).
 * ERS RF-12: validación fila por fila sin bloquear las filas válidas.
 * ERS RF-13: reporte final: creados, omitidos (ya existían) y con error.
 *
 * Columnas: nombres, apellidos, correo, rol (también se acepta "nombre" con
 * el nombre completo, y "email" en lugar de "correo").
 */
#[Layout('layouts.app')]
class ImportarCSV extends Component
{
    use WithFileUploads;

    public const MAX_FILAS = 1000;

    public $archivo = null;

    public bool $procesado = false;

    /** @var array<int, array<string, string>> */
    public array $creados = [];

    /** @var array<int, array<string, string|int>> */
    public array $omitidos = [];

    /** @var array<int, array<string, string|int>> */
    public array $errores = [];

    /** @var array<int, array<string, string>> */
    public array $preview = [];

    public bool $mostrarPreview = false;

    public function mount(): void
    {
        Gate::authorize('verListado', User::class);
    }

    public function updatedArchivo(): void
    {
        $this->procesado = false;
        $this->creados = $this->omitidos = $this->errores = $this->preview = [];
        $this->mostrarPreview = false;
        $this->previsualizar();
    }

    public function previsualizar(): void
    {
        Gate::authorize('verListado', User::class);

        if (! $this->archivo) {
            return;
        }

        $this->validarArchivo();

        $filas = $this->leerFilas();

        if ($filas === null) {
            return;
        }

        $this->preview = array_slice($filas, 0, 10);
        $this->mostrarPreview = true;
    }

    public function importar(GestorCuentas $gestor): void
    {
        Gate::authorize('verListado', User::class);
        $this->validarArchivo();

        $filas = $this->leerFilas();

        if ($filas === null) {
            return;
        }

        if (count($filas) > self::MAX_FILAS) {
            $this->addError('archivo', 'El archivo supera el máximo de '.self::MAX_FILAS.' filas por importación.');

            return;
        }

        $correosVistos = [];

        foreach ($filas as $i => $fila) {
            $linea = $i + 2; // la fila 1 es el encabezado

            $nombre = $this->nombreCompleto($fila);
            $correo = strtolower(trim($fila['correo'] ?? $fila['email'] ?? ''));
            $rol = strtolower(trim($fila['rol'] ?? '')) ?: 'estudiante';

            $error = match (true) {
                $nombre === '' || $correo === '' => 'Nombre o correo vacío.',
                ! filter_var($correo, FILTER_VALIDATE_EMAIL) => 'Correo electrónico con formato inválido.',
                ! in_array($rol, GestorCuentas::ROLES_CREABLES, true) => "Rol \"{$rol}\" no válido. Usa: ".implode(', ', GestorCuentas::ROLES_CREABLES).'.',
                isset($correosVistos[$correo]) => "Correo duplicado dentro del archivo (ya aparece en la línea {$correosVistos[$correo]}).",
                default => null,
            };

            if ($error) {
                $this->errores[] = ['linea' => $linea, 'datos' => trim("{$nombre} | {$correo}", ' |'), 'error' => $error];

                continue;
            }

            $correosVistos[$correo] = $linea;

            if (User::where('email', $correo)->exists()) {
                $this->omitidos[] = ['linea' => $linea, 'nombre' => $nombre, 'correo' => $correo, 'razon' => 'Ya existe una cuenta con este correo.'];

                continue;
            }

            try {
                $resultado = $gestor->crear($nombre, $correo, $rol, auth()->user(), 'importación masiva');

                $this->creados[] = [
                    'nombre' => $nombre,
                    'correo' => $correo,
                    'rol' => $rol,
                    'password' => $resultado['password_temporal'],
                ];
            } catch (Throwable $e) {
                report($e);
                $this->errores[] = ['linea' => $linea, 'datos' => "{$nombre} | {$correo}", 'error' => 'No se pudo crear la cuenta. Revisa los datos e inténtalo de nuevo.'];
            }
        }

        $this->procesado = true;
        $this->archivo = null;
        $this->mostrarPreview = false;
    }

    public function reiniciar(): void
    {
        $this->reset(['archivo', 'procesado', 'creados', 'omitidos', 'errores', 'preview', 'mostrarPreview']);
    }

    protected function validarArchivo(): void
    {
        $this->validate(
            ['archivo' => ['required', 'file', 'extensions:csv,txt,xlsx', 'max:2048']],
            [
                'archivo.required' => 'Selecciona un archivo de Excel (.xlsx) o CSV.',
                'archivo.extensions' => 'El archivo debe ser un libro de Excel (.xlsx) o un .csv.',
                'archivo.max' => 'El archivo no debe superar 2 MB.',
            ]
        );
    }

    /**
     * @param  array<string, string>  $fila
     */
    protected function nombreCompleto(array $fila): string
    {
        $partes = isset($fila['nombres']) || isset($fila['apellidos'])
            ? [trim($fila['nombres'] ?? ''), trim($fila['apellidos'] ?? '')]
            : [trim($fila['nombre'] ?? '')];

        return trim(implode(' ', array_filter($partes)));
    }

    /**
     * Filas del archivo como arreglos asociativos por encabezado, o null si no se pudo leer
     * (el error queda en el campo "archivo").
     *
     * @return array<int, array<string, string>>|null
     */
    protected function leerFilas(): ?array
    {
        if (strtolower($this->archivo->getClientOriginalExtension()) !== 'xlsx') {
            return $this->asociarEncabezados($this->filasCSV($this->archivo->getRealPath()));
        }

        try {
            return $this->asociarEncabezados(app(LectorXlsx::class)->filas($this->archivo->getRealPath()));
        } catch (RuntimeException $e) {
            $this->addError('archivo', $e->getMessage());

            return null;
        }
    }

    /**
     * @return list<list<string>>
     */
    protected function filasCSV(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return [];
        }

        $primeraLinea = (string) fgets($handle);
        $separador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';
        rewind($handle);

        $filas = [];

        while (($fila = fgetcsv($handle, 2000, $separador)) !== false) {
            $filas[] = array_map(fn ($valor) => (string) $valor, $fila);
        }

        fclose($handle);

        return $filas;
    }

    /**
     * La primera fila es el encabezado; se ignoran las filas vacías.
     *
     * @param  list<list<string>>  $filasCrudas
     * @return array<int, array<string, string>>
     */
    protected function asociarEncabezados(array $filasCrudas): array
    {
        $filas = [];
        $encabezados = null;

        foreach ($filasCrudas as $fila) {
            if ($encabezados === null && count(array_filter($fila, fn ($valor) => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            if ($encabezados === null) {
                $encabezados = array_map(
                    fn ($columna) => strtolower(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $columna))),
                    $fila
                );

                continue;
            }

            if (count(array_filter($fila, fn ($valor) => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            $fila = array_slice(array_pad($fila, count($encabezados), ''), 0, count($encabezados));
            $filas[] = array_combine($encabezados, array_map(fn ($valor) => (string) $valor, $fila));
        }

        return $filas;
    }

    public function render()
    {
        return view('livewire.usuarios.importar-csv', [
            'nombreVista' => fn (array $fila) => $this->nombreCompleto($fila),
        ])->title('Importar usuarios — LearnUp');
    }
}
