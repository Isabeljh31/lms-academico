<?php

namespace App\Services\Evaluacion;

use App\Models\BancoPregunta;
use App\Models\Curso;
use App\Services\HojasCalculo\HojaXlsx;
use App\Services\HojasCalculo\LectorXlsx;
use App\Services\HojasCalculo\LibroXlsx;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Carga masiva del banco de preguntas desde un libro de Excel (.xlsx) o un
 * CSV (UTF-8, separador coma o punto y coma). Extensión del ERS RF-47; sigue
 * el patrón de la importación de usuarios (RF-11 a RF-13): cada fila se
 * valida por separado, las válidas se guardan y las demás se reportan con su
 * número de fila y el motivo, sin bloquear al resto.
 *
 * Una fila con el mismo tema y enunciado que otra del banco del curso (o que
 * una fila anterior del archivo) se omite como duplicada.
 *
 * Todo se guarda como texto plano; las vistas lo muestran escapado.
 */
class ImportadorBancoPreguntas
{
    public const MAX_FILAS = 500;

    public const MAX_KB = 2048;

    public const MAX_OPCIONES = 6;

    /** @var list<string> */
    public const COLUMNAS = [
        'unidad', 'tema', 'tipo', 'enunciado',
        'opcion_1', 'opcion_2', 'opcion_3', 'opcion_4', 'opcion_5', 'opcion_6',
        'correcta', 'puntaje', 'retroalimentacion',
    ];

    /** @var list<string> */
    public const COLUMNAS_OBLIGATORIAS = ['unidad', 'tema', 'tipo', 'enunciado', 'correcta'];

    /**
     * Valor de la columna "tipo" => tipo de pregunta (Quiz::TIPOS_PREGUNTA).
     *
     * @var array<string, string>
     */
    public const TIPOS = [
        'unica' => 'opcion_unica',
        'multiple' => 'opcion_multiple',
        'vf' => 'verdadero_falso',
    ];

    /** @var array<string, string> Otras formas aceptadas de escribir el tipo (sin tildes, en minúsculas). */
    protected const ALIAS_TIPOS = [
        'opcion_unica' => 'unica', 'opcion unica' => 'unica',
        'opcion_multiple' => 'multiple', 'opcion multiple' => 'multiple',
        'verdadero_falso' => 'vf', 'verdadero/falso' => 'vf', 'v/f' => 'vf',
    ];

    public function __construct(protected LectorXlsx $lector) {}

    /**
     * Lee y valida el archivo sin guardar nada.
     *
     * @return array{
     *     validas: list<array{fila: int, unidad: string, tema: string, pregunta: string, tipo: string, opciones: list<string>, respuestas_correctas: list<int>, puntaje: ?float, explicacion: ?string}>,
     *     errores: list<array{fila: int, enunciado: string, motivo: string}>,
     *     duplicadas: list<array{fila: int, enunciado: string, motivo: string}>
     * }
     *
     * @throws RuntimeException si el archivo no se puede leer, no tiene filas, le faltan columnas o supera el máximo de filas.
     */
    public function analizar(string $ruta, string $extension, Curso $curso): array
    {
        $filas = $this->filasAsociadas(
            strtolower($extension) === 'xlsx' ? $this->lector->filas($ruta) : $this->filasCsv($ruta)
        );

        if ($filas === []) {
            throw new RuntimeException('El archivo no tiene preguntas: agrega al menos una fila debajo de los encabezados.');
        }

        // Archivo hecho con la plantilla anterior (sin unidad): se pide la nueva.
        if (! array_key_exists('unidad', reset($filas))) {
            throw new RuntimeException('Al archivo le falta la columna "unidad" (T1, T2, T3 o T4), antes de "tema". Descarga y usa la plantilla nueva.');
        }

        if ($faltantes = array_values(array_diff(self::COLUMNAS_OBLIGATORIAS, array_keys(reset($filas))))) {
            throw new RuntimeException('Al archivo le faltan las columnas: '.implode(', ', $faltantes).'. Usa la plantilla.');
        }

        if (count($filas) > self::MAX_FILAS) {
            throw new RuntimeException('El archivo tiene '.count($filas).' filas con datos; el máximo es '.self::MAX_FILAS.' por importación.');
        }

        $existentes = $curso->bancoPreguntasCurso()->get(['tema', 'pregunta'])
            ->mapWithKeys(fn (BancoPregunta $p) => [BancoPregunta::claveDuplicado($p->tema, $p->pregunta) => true])
            ->all();
        $vistas = [];
        $resultado = ['validas' => [], 'errores' => [], 'duplicadas' => []];

        foreach ($filas as $numeroFila => $fila) {
            $enunciado = Str::limit($this->texto($fila['enunciado'] ?? ''), 80);
            [$datos, $motivos] = $this->analizarFila($fila);

            if ($motivos !== []) {
                $resultado['errores'][] = ['fila' => $numeroFila, 'enunciado' => $enunciado, 'motivo' => implode(' ', $motivos)];

                continue;
            }

            $clave = BancoPregunta::claveDuplicado($datos['tema'], $datos['pregunta']);

            if (isset($existentes[$clave]) || isset($vistas[$clave])) {
                $resultado['duplicadas'][] = [
                    'fila' => $numeroFila,
                    'enunciado' => $enunciado,
                    'motivo' => isset($vistas[$clave])
                        ? "Repite el tema y el enunciado de la fila {$vistas[$clave]}."
                        : 'Ya existe en el banco una pregunta con el mismo tema y enunciado.',
                ];

                continue;
            }

            $vistas[$clave] = $numeroFila;
            $resultado['validas'][] = ['fila' => $numeroFila, ...$datos];
        }

        return $resultado;
    }

    /** Plantilla .xlsx: hoja "Preguntas" (encabezados y ejemplos) y hoja "Instrucciones". */
    public function plantilla(): LibroXlsx
    {
        $libro = new LibroXlsx('Plantilla del banco de preguntas');

        $libro->hoja('Preguntas')
            ->encabezados(self::COLUMNAS, fijar: true)
            ->fila(['T1', 'Conjuntos', 'unica', '¿Cuántos elementos tiene el conjunto {a, b, c}?', '2', '3', '4', '', '', '', 'B', 1, 'Se cuentan los elementos distintos.'])
            ->fila(['T1', 'Conjuntos', 'multiple', '¿Cuáles son números pares?', '2', '3', '4', '7', '', '', 'A,C', 2, ''])
            ->fila(['T2', 'Lógica', 'vf', 'Una proposición puede ser verdadera y falsa a la vez.', '', '', '', '', '', '', 'F', '', 'Principio de no contradicción.']);

        $libro->hoja('Instrucciones')
            ->titulo('Cómo llenar la plantilla', 'Llena la hoja "Preguntas" (una pregunta por fila) y borra las filas de ejemplo antes de importar.')
            ->espacio()
            ->encabezados(['Columna', 'Obligatoria', 'Qué escribir'])
            ->fila(['unidad', 'Sí', 'Unidad de la pregunta: T1, T2, T3 o T4 (no importan mayúsculas ni espacios).'])
            ->fila(['tema', 'Sí', 'Contenido dentro de la unidad, por ejemplo Conjuntos (máximo 150 caracteres).'])
            ->fila(['tipo', 'Sí', 'unica (una sola correcta), multiple (una o varias correctas) o vf (verdadero o falso).'])
            ->fila(['enunciado', 'Sí', 'Texto de la pregunta (máximo 1000 caracteres).'])
            ->fila(['opcion_1 a opcion_6', 'Sí, salvo en vf', 'Al menos 2 opciones (máximo 255 caracteres cada una). En vf se dejan vacías: las opciones son Verdadero y Falso.'])
            ->fila(['correcta', 'Sí', 'Letra de la opción correcta: A = opcion_1, B = opcion_2… En multiple, varias separadas por coma (A,C). En vf: V o F.'])
            ->fila(['puntaje', 'No', 'Número mayor que 0 (entre 0.5 y 100). Si se deja vacío vale 1.'])
            ->fila(['retroalimentacion', 'No', 'Explicación que ve el estudiante al revisar su resultado (máximo 1000 caracteres).'])
            ->espacio()
            ->pares([
                'Formatos' => 'Excel (.xlsx) o CSV en UTF-8 con separador coma o punto y coma.',
                'Límites' => 'Máximo 2 MB y '.self::MAX_FILAS.' preguntas por archivo.',
                'Duplicadas' => 'Una pregunta con el mismo tema y enunciado que otra del banco se omite.',
                'Errores' => 'Las filas con errores no se guardan; las demás sí. Al final puedes descargar el reporte.',
            ]);

        return $libro;
    }

    /**
     * Reporte de las filas no importadas (con error u omitidas por duplicadas).
     *
     * @param  list<array{fila: int, enunciado: string, motivo: string}>  $errores
     * @param  list<array{fila: int, enunciado: string, motivo: string}>  $duplicadas
     */
    public function reporte(array $errores, array $duplicadas): LibroXlsx
    {
        $libro = new LibroXlsx('Reporte de importación del banco de preguntas');
        $hoja = $libro->hoja('Filas no importadas')->encabezados(['Fila', 'Resultado', 'Enunciado', 'Motivo'], fijar: true);

        $filas = collect($errores)->map(fn ($e) => [...$e, 'resultado' => 'Error'])
            ->merge(collect($duplicadas)->map(fn ($d) => [...$d, 'resultado' => 'Omitida (duplicada)']))
            ->sortBy('fila');

        foreach ($filas as $fila) {
            $hoja->fila([
                (int) $fila['fila'],
                ['valor' => $fila['resultado'], 'estilo' => $fila['resultado'] === 'Error' ? HojaXlsx::TEXTO_ALERTA : HojaXlsx::TEXTO],
                $fila['enunciado'],
                $fila['motivo'],
            ]);
        }

        return $libro;
    }

    /**
     * @param  array<string, string>  $fila
     * @return array{0: array{unidad: string, tema: string, pregunta: string, tipo: string, opciones: list<string>, respuestas_correctas: list<int>, puntaje: ?float, explicacion: ?string}, 1: list<string>}
     */
    protected function analizarFila(array $fila): array
    {
        $motivos = [];
        $unidadEscrita = $this->texto($fila['unidad'] ?? '');
        $unidad = BancoPregunta::normalizarUnidad($unidadEscrita);
        $tema = $this->texto($fila['tema'] ?? '');
        $pregunta = $this->texto($fila['enunciado'] ?? '');
        $tipoEscrito = Str::lower(Str::ascii($this->texto($fila['tipo'] ?? '')));
        $tipo = self::ALIAS_TIPOS[$tipoEscrito] ?? $tipoEscrito;
        $explicacion = $this->texto($fila['retroalimentacion'] ?? '');

        if ($unidadEscrita === '') {
            $motivos[] = 'Falta la unidad (T1, T2, T3 o T4).';
        } elseif ($unidad === null) {
            $motivos[] = 'Unidad "'.$unidadEscrita.'" no válida. Usa T1, T2, T3 o T4.';
        }

        if ($tema === '') {
            $motivos[] = 'Falta el tema.';
        } elseif (mb_strlen($tema) > 150) {
            $motivos[] = 'El tema supera 150 caracteres.';
        }

        if ($pregunta === '') {
            $motivos[] = 'Falta el enunciado.';
        } elseif (mb_strlen($pregunta) > 1000) {
            $motivos[] = 'El enunciado supera 1000 caracteres.';
        }

        if (mb_strlen($explicacion) > 1000) {
            $motivos[] = 'La retroalimentación supera 1000 caracteres.';
        }

        $puntaje = $this->puntaje($fila['puntaje'] ?? '', $motivos);

        if (! isset(self::TIPOS[$tipo])) {
            $motivos[] = $tipoEscrito === ''
                ? 'Falta el tipo (unica, multiple o vf).'
                : 'Tipo "'.$this->texto($fila['tipo']).'" no válido. Usa unica, multiple o vf.';

            return [[], $motivos];
        }

        [$opciones, $correctas] = $tipo === 'vf'
            ? $this->opcionesVerdaderoFalso($fila['correcta'] ?? '', $motivos)
            : $this->opcionesConLetras($fila, $tipo, $motivos);

        return [[
            'unidad' => (string) $unidad,
            'tema' => $tema,
            'pregunta' => $pregunta,
            'tipo' => self::TIPOS[$tipo],
            'opciones' => $opciones,
            'respuestas_correctas' => $correctas,
            'puntaje' => $puntaje,
            'explicacion' => $explicacion !== '' ? $explicacion : null,
        ], $motivos];
    }

    /**
     * @param  list<string>  $motivos
     * @return array{0: list<string>, 1: list<int>}
     */
    protected function opcionesVerdaderoFalso(string $correcta, array &$motivos): array
    {
        $valor = Str::upper(Str::ascii($this->texto($correcta)));
        $indice = match ($valor) {
            'V', 'VERDADERO' => 0,
            'F', 'FALSO' => 1,
            default => null,
        };

        if ($indice === null) {
            $motivos[] = 'En vf la respuesta correcta debe ser V o F.';
        }

        return [['Verdadero', 'Falso'], [$indice ?? 0]];
    }

    /**
     * Opciones escritas (se ignoran las vacías) y las correctas como índices
     * sobre esas opciones. La letra A corresponde a opcion_1, B a opcion_2…
     *
     * @param  array<string, string>  $fila
     * @param  list<string>  $motivos
     * @return array{0: list<string>, 1: list<int>}
     */
    protected function opcionesConLetras(array $fila, string $tipo, array &$motivos): array
    {
        $porLetra = [];

        foreach (range(1, self::MAX_OPCIONES) as $numero) {
            $texto = $this->texto($fila['opcion_'.$numero] ?? '');

            if ($texto === '') {
                continue;
            }

            if (mb_strlen($texto) > 255) {
                $motivos[] = "La opción {$numero} supera 255 caracteres.";
            }

            $porLetra[chr(64 + $numero)] = $texto;
        }

        if (count($porLetra) < 2) {
            $motivos[] = 'Escribe al menos 2 opciones.';
        }

        $letras = collect(preg_split('/[\s,;]+/', Str::upper($this->texto($fila['correcta'] ?? ''))))
            ->filter()->unique()->values();

        if ($letras->isEmpty()) {
            $motivos[] = 'Falta la respuesta correcta (por ejemplo A o A,C).';
        } elseif ($tipo === 'unica' && $letras->count() > 1) {
            $motivos[] = 'En unica solo puede haber una respuesta correcta.';
        }

        $invalidas = $letras->reject(fn (string $letra) => isset($porLetra[$letra]));
        if ($invalidas->isNotEmpty()) {
            $motivos[] = 'La respuesta correcta "'.$invalidas->join(',').'" no corresponde a ninguna opción escrita.';
        }

        $letrasEscritas = array_keys($porLetra);
        $correctas = $letras->map(fn (string $letra) => array_search($letra, $letrasEscritas, true))
            ->filter(fn ($indice) => $indice !== false)->sort()->values()->all();

        return [array_values($porLetra), $correctas];
    }

    /**
     * @param  list<string>  $motivos
     */
    protected function puntaje(string $valor, array &$motivos): ?float
    {
        $valor = str_replace(',', '.', $this->texto($valor));

        if ($valor === '') {
            return null;
        }

        if (! is_numeric($valor) || (float) $valor < 0.5 || (float) $valor > 100) {
            $motivos[] = 'El puntaje debe ser un número entre 0.5 y 100.';

            return null;
        }

        return round((float) $valor, 2);
    }

    /** Texto plano: sin espacios extremos ni caracteres de control. */
    protected function texto(string $valor): string
    {
        return trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? '');
    }

    /**
     * Filas de un CSV en UTF-8 (si no lo es, se interpreta como Windows-1252,
     * el formato de "CSV" de Excel en español). El separador es el que más
     * aparece en la primera línea: coma o punto y coma.
     *
     * @return list<list<string>>
     */
    protected function filasCsv(string $ruta): array
    {
        $contenido = (string) file_get_contents($ruta);

        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'Windows-1252');
        }

        $contenido = preg_replace('/^\xEF\xBB\xBF/', '', $contenido);
        $primeraLinea = strtok($contenido, "\n") ?: '';
        $separador = substr_count($primeraLinea, ';') > substr_count($primeraLinea, ',') ? ';' : ',';

        $flujo = fopen('php://temp', 'r+');
        fwrite($flujo, $contenido);
        rewind($flujo);

        $filas = [];
        while (($fila = fgetcsv($flujo, 0, $separador, '"', '')) !== false) {
            $filas[] = array_map(fn ($valor) => (string) $valor, $fila);
        }
        fclose($flujo);

        return $filas;
    }

    /**
     * La primera fila no vacía es el encabezado (sin tildes ni mayúsculas, y
     * con "_" en lugar de espacios). Se ignoran las filas vacías; cada fila
     * queda indexada por su número real en el archivo.
     *
     * @param  list<list<string>>  $filasCrudas
     * @return array<int, array<string, string>>
     */
    protected function filasAsociadas(array $filasCrudas): array
    {
        $filas = [];
        $encabezados = null;

        foreach ($filasCrudas as $indice => $fila) {
            if (count(array_filter($fila, fn ($valor) => trim((string) $valor) !== '')) === 0) {
                continue;
            }

            if ($encabezados === null) {
                $encabezados = array_map(
                    fn ($columna) => Str::of((string) $columna)->replaceMatches('/^\xEF\xBB\xBF/', '')->ascii()->lower()->trim()->replaceMatches('/\s+/', '_')->toString(),
                    $fila
                );

                continue;
            }

            $fila = array_slice(array_pad($fila, count($encabezados), ''), 0, count($encabezados));
            $filas[$indice + 1] = array_combine($encabezados, array_map(fn ($valor) => (string) $valor, $fila));
        }

        return $filas;
    }
}
