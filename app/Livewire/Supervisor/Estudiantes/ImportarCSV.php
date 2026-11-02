<?php

namespace App\Livewire\Supervisor\Estudiantes;

use App\Models\User;
use App\Rules\Dni;
use App\Rules\NombrePersona;
use App\Services\HojasCalculo\LectorXlsx;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
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
 * Columnas: nombres, apellidos, dni, correo, rol (se acepta "email" en lugar
 * de "correo"; si el rol está vacío se usa estudiante).
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

        if ($faltantes = $this->columnasFaltantes($filas)) {
            $this->addError('archivo', 'Al archivo le faltan las columnas: '.implode(', ', $faltantes).'. Revisa el formato de ejemplo.');

            return;
        }

        $correosVistos = [];
        $dnisVistos = [];

        foreach ($filas as $i => $fila) {
            $linea = $i + 2; // la fila 1 es el encabezado

            $datos = [
                'nombres' => trim($fila['nombres'] ?? ''),
                'apellidos' => trim($fila['apellidos'] ?? ''),
                'dni' => trim($fila['dni'] ?? ''),
                'correo' => User::normalizarEmail($fila['correo'] ?? $fila['email'] ?? ''),
                'rol' => mb_strtolower(trim($fila['rol'] ?? '')) ?: 'estudiante',
            ];
            $nombre = trim("{$datos['nombres']} {$datos['apellidos']}");
            $resumen = trim("{$nombre} | {$datos['dni']} | {$datos['correo']}", ' |');

            $error = $this->errorDeFila($datos)
                ?? (isset($correosVistos[$datos['correo']]) ? "Correo duplicado dentro del archivo (ya aparece en la línea {$correosVistos[$datos['correo']]})." : null)
                ?? (isset($dnisVistos[$datos['dni']]) ? "DNI duplicado dentro del archivo (ya aparece en la línea {$dnisVistos[$datos['dni']]})." : null);

            if ($error) {
                $this->errores[] = ['linea' => $linea, 'datos' => $resumen, 'error' => $error];

                continue;
            }

            $correosVistos[$datos['correo']] = $linea;
            $dnisVistos[$datos['dni']] = $linea;

            if (User::where('email', $datos['correo'])->exists()) {
                $this->omitidos[] = ['linea' => $linea, 'nombre' => $nombre, 'correo' => $datos['correo'], 'razon' => 'Ya existe una cuenta con este correo.'];

                continue;
            }

            if (User::where('dni', $datos['dni'])->exists()) {
                $this->errores[] = ['linea' => $linea, 'datos' => $resumen, 'error' => 'Ya existe otra cuenta con este DNI.'];

                continue;
            }

            try {
                $resultado = $gestor->crear(
                    nombres: $datos['nombres'],
                    apellidos: $datos['apellidos'],
                    email: $datos['correo'],
                    rol: $datos['rol'],
                    ejecutor: auth()->user(),
                    origen: 'importación masiva',
                    dni: $datos['dni'],
                );

                $this->creados[] = [
                    'nombre' => $resultado['usuario']->name,
                    'dni' => $datos['dni'],
                    'correo' => $datos['correo'],
                    'rol' => $datos['rol'],
                    'password' => $resultado['password_temporal'],
                ];
            } catch (Throwable $e) {
                report($e);
                $this->errores[] = ['linea' => $linea, 'datos' => $resumen, 'error' => 'No se pudo crear la cuenta. Revisa los datos e inténtalo de nuevo.'];
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
        return trim(trim($fila['nombres'] ?? '').' '.trim($fila['apellidos'] ?? ''));
    }

    /**
     * ERS RF-12: las mismas reglas del alta individual, fila por fila. Devuelve
     * todos los problemas de la fila en un solo mensaje, o null si es válida.
     *
     * @param  array{nombres: string, apellidos: string, dni: string, correo: string, rol: string}  $datos
     */
    protected function errorDeFila(array $datos): ?string
    {
        $validador = Validator::make($datos, [
            'nombres' => ['required', 'string', new NombrePersona],
            'apellidos' => ['required', 'string', new NombrePersona],
            'dni' => ['required', new Dni],
            'correo' => ['required', 'email', 'max:255'],
            'rol' => [Rule::in(GestorCuentas::ROLES_CREABLES)],
        ], [
            'nombres.required' => 'Faltan los nombres.',
            'apellidos.required' => 'Faltan los apellidos.',
            'dni.required' => 'Falta el DNI.',
            'correo.required' => 'Falta el correo.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'correo.max' => 'El correo no puede tener más de 255 caracteres.',
            'rol.in' => "Rol \"{$datos['rol']}\" no válido. Usa: ".implode(', ', GestorCuentas::ROLES_CREABLES).'.',
        ], [
            'nombres' => 'nombres',
            'apellidos' => 'apellidos',
        ]);

        if (! $validador->fails()) {
            return null;
        }

        $errores = $validador->errors()->all();

        // Excel borra el 0 inicial si la columna es numérica (07123456 → 7123456).
        if (preg_match('/^\d{1,7}$/', $datos['dni'])) {
            $errores[] = 'Si el DNI empieza con 0, guarda la columna DNI como texto en Excel.';
        }

        return implode(' ', $errores);
    }

    /**
     * @param  array<int, array<string, string>>  $filas
     * @return list<string>
     */
    protected function columnasFaltantes(array $filas): array
    {
        $columnas = array_keys($filas[0] ?? []);

        $faltantes = array_values(array_diff(['nombres', 'apellidos', 'dni'], $columnas));

        if (! in_array('correo', $columnas, true) && ! in_array('email', $columnas, true)) {
            $faltantes[] = 'correo';
        }

        return $faltantes;
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
        return view('livewire.supervisor.estudiantes.importar-csv', [
            'nombreVista' => fn (array $fila) => $this->nombreCompleto($fila),
        ])->title('Importar usuarios — LearnUp');
    }
}
