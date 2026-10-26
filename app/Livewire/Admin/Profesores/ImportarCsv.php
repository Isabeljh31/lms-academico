<?php

namespace App\Livewire\Admin\Profesores;

use App\Enums\Rol;
use App\Models\User;
use App\Rules\Dni;
use App\Rules\NombrePersona;
use App\Services\HojasCalculo\LectorXlsx;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;

/**
 * Importación masiva de cuentas de profesor desde un libro de Excel (.xlsx)
 * o un archivo CSV. Mismo mecanismo de lectura que `Usuarios\ImportarCSV`,
 * pero con las columnas propias del directorio de profesores (especialidad,
 * año de nacimiento, etc.) en vez de una columna "rol" genérica.
 */
#[Layout('layouts.app')]
class ImportarCsv extends Component
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
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
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
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

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
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
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
                'especialidad' => trim($fila['especialidad'] ?? ''),
                'celular' => trim($fila['celular'] ?? ''),
                'anio_nacimiento' => trim($fila['anio_nacimiento'] ?? $fila['año_nacimiento'] ?? ''),
                'correo_personal' => trim($fila['correo_personal'] ?? ''),
                'grado_academico' => trim($fila['grado_academico'] ?? ''),
                'colegiatura' => trim($fila['colegiatura'] ?? ''),
                'fecha_inicio_labores' => trim($fila['fecha_inicio_labores'] ?? ''),
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
                    rol: Rol::Profesor->value,
                    ejecutor: auth()->user(),
                    origen: 'importación masiva',
                    dni: $datos['dni'],
                );

                $resultado['usuario']->update([
                    'especialidad' => $datos['especialidad'] ?: null,
                    'celular' => $datos['celular'] ?: null,
                    'anio_nacimiento' => $datos['anio_nacimiento'] ?: null,
                    'correo_personal' => $datos['correo_personal'] ?: null,
                    'grado_academico' => $datos['grado_academico'] ?: null,
                    'colegiatura' => $datos['colegiatura'] ?: null,
                    'fecha_inicio_labores' => $datos['fecha_inicio_labores'] ?: null,
                ]);

                $this->creados[] = [
                    'nombre' => $resultado['usuario']->name,
                    'dni' => $datos['dni'],
                    'correo' => $datos['correo'],
                    'especialidad' => $datos['especialidad'],
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
     * Mismas reglas del alta individual de profesor (Index::crearProfesor),
     * fila por fila. Devuelve todos los problemas en un solo mensaje, o null
     * si la fila es válida.
     *
     * @param  array<string, string>  $datos
     */
    protected function errorDeFila(array $datos): ?string
    {
        $anioActual = (int) now()->format('Y');

        $validador = Validator::make($datos, [
            'nombres' => ['required', 'string', new NombrePersona],
            'apellidos' => ['required', 'string', new NombrePersona],
            'dni' => ['required', new Dni],
            'correo' => ['required', 'email', 'max:255'],
            'especialidad' => ['nullable', 'string', 'max:120'],
            'celular' => ['nullable', 'regex:/^9\d{8}$/'],
            'anio_nacimiento' => ['required', 'integer', 'min:'.($anioActual - 100), 'max:'.($anioActual - 18)],
            'correo_personal' => ['nullable', 'email', 'max:255'],
            'grado_academico' => ['nullable', 'string', 'max:120'],
            'colegiatura' => ['nullable', 'string', 'max:60'],
            'fecha_inicio_labores' => ['nullable', 'date'],
        ], [
            'nombres.required' => 'Faltan los nombres.',
            'apellidos.required' => 'Faltan los apellidos.',
            'dni.required' => 'Falta el DNI.',
            'correo.required' => 'Falta el correo.',
            'correo.email' => 'El correo no tiene un formato válido.',
            'correo.max' => 'El correo no puede tener más de 255 caracteres.',
            'celular.regex' => 'El celular debe tener 9 dígitos y empezar en 9.',
            'anio_nacimiento.required' => 'Falta el año de nacimiento.',
            'anio_nacimiento.integer' => 'El año de nacimiento no es válido.',
            'anio_nacimiento.min' => 'Revisa el año de nacimiento.',
            'anio_nacimiento.max' => 'El profesor debe ser mayor de edad.',
            'correo_personal.email' => 'El correo personal no tiene un formato válido.',
            'fecha_inicio_labores.date' => 'La fecha de inicio de labores no es válida.',
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

        if (! in_array('anio_nacimiento', $columnas, true) && ! in_array('año_nacimiento', $columnas, true)) {
            $faltantes[] = 'anio_nacimiento';
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
        return view('livewire.admin.profesores.importar-csv', [
            'nombreVista' => fn (array $fila) => $this->nombreCompleto($fila),
        ])->title('Importar profesores - LearnUp');
    }
}
