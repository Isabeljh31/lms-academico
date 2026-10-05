<?php

namespace App\Services\HojasCalculo;

use DateTimeInterface;

/**
 * Una hoja de un libro .xlsx con formato: título, secciones, encabezados con
 * color, bordes, formato numérico y columnas autoajustadas.
 *
 * Los índices de estilo corresponden a los <cellXfs> que genera
 * LibroXlsx::estilos(). Las celdas se pueden pasar como valor simple (el
 * estilo se deduce del tipo) o como ['valor' => ..., 'estilo' => self::X].
 */
class HojaXlsx
{
    public const TITULO = 1;

    public const SUBTITULO = 2;

    public const ENCABEZADO = 3;

    public const TEXTO = 4;

    public const DECIMAL = 5;

    public const ENTERO = 6;

    public const PORCENTAJE = 7;

    public const FECHA = 8;

    public const SECCION = 9;

    public const DECIMAL_ALERTA = 10;

    public const TOTAL_TEXTO = 11;

    public const TOTAL_DECIMAL = 12;

    public const TEXTO_EXITO = 13;

    public const TEXTO_ALERTA = 14;

    public const ETIQUETA = 15;

    public const DECIMAL_EXITO = 16;

    public const TOTAL_PORCENTAJE = 17;

    /** @var list<array{tipo: string, celdas: list<array{valor: mixed, estilo: int}>, alto?: float}> */
    protected array $filas = [];

    /** Fila (1-based) que queda fija al desplazarse, con filtro automático. */
    protected ?int $filaEncabezadoFija = null;

    protected int $columnasEncabezadoFijo = 0;

    public function __construct(public readonly string $nombre) {}

    /**
     * Banda de título que ocupa todo el ancho de la tabla, con subtítulo opcional
     * (institución, curso, fecha de generación).
     */
    public function titulo(string $titulo, ?string $subtitulo = null): static
    {
        $this->filas[] = ['tipo' => 'ancho', 'celdas' => [['valor' => $titulo, 'estilo' => self::TITULO]], 'alto' => 26];

        if ($subtitulo) {
            $this->filas[] = ['tipo' => 'ancho', 'celdas' => [['valor' => $subtitulo, 'estilo' => self::SUBTITULO]]];
        }

        return $this->espacio();
    }

    /** Encabezado de sección que ocupa todo el ancho (p. ej. "Resumen", "Detalle por rubro"). */
    public function seccion(string $texto): static
    {
        $this->filas[] = ['tipo' => 'ancho', 'celdas' => [['valor' => $texto, 'estilo' => self::SECCION]], 'alto' => 20];

        return $this;
    }

    /**
     * @param  list<string>  $columnas
     * @param  bool  $fijar  Congela la fila y agrega filtro automático (solo la primera tabla de la hoja).
     */
    public function encabezados(array $columnas, bool $fijar = false): static
    {
        $this->filas[] = [
            'tipo' => 'normal',
            'celdas' => array_map(fn ($columna) => ['valor' => $columna, 'estilo' => self::ENCABEZADO], $columnas),
            'alto' => 22,
        ];

        if ($fijar && $this->filaEncabezadoFija === null) {
            $this->filaEncabezadoFija = count($this->filas);
            $this->columnasEncabezadoFijo = count($columnas);
        }

        return $this;
    }

    /**
     * @param  list<mixed>  $celdas
     */
    public function fila(array $celdas): static
    {
        $this->filas[] = ['tipo' => 'normal', 'celdas' => array_map(fn ($celda) => $this->normalizar($celda), $celdas)];

        return $this;
    }

    /**
     * Fila de totales o promedios: fondo suave y negrita.
     *
     * @param  list<mixed>  $celdas
     */
    public function filaTotal(array $celdas): static
    {
        $this->filas[] = ['tipo' => 'normal', 'celdas' => array_map(function ($celda) {
            $celda = $this->normalizar($celda);
            $celda['estilo'] = match (true) {
                in_array($celda['estilo'], [self::PORCENTAJE, self::TOTAL_PORCENTAJE], true) => self::TOTAL_PORCENTAJE,
                in_array($celda['estilo'], [self::DECIMAL, self::ENTERO, self::DECIMAL_ALERTA, self::DECIMAL_EXITO], true) => self::TOTAL_DECIMAL,
                default => self::TOTAL_TEXTO,
            };

            return $celda;
        }, $celdas)];

        return $this;
    }

    /**
     * Bloque de pares etiqueta / valor (ficha de resumen).
     *
     * @param  array<string, mixed>  $pares
     */
    public function pares(array $pares): static
    {
        foreach ($pares as $etiqueta => $valor) {
            $this->filas[] = ['tipo' => 'normal', 'celdas' => [
                ['valor' => $etiqueta, 'estilo' => self::ETIQUETA],
                $this->normalizar($valor),
            ]];
        }

        return $this;
    }

    public function espacio(): static
    {
        $this->filas[] = ['tipo' => 'normal', 'celdas' => []];

        return $this;
    }

    public static function celda(mixed $valor, int $estilo): array
    {
        return ['valor' => $valor, 'estilo' => $estilo];
    }

    /**
     * @return array{valor: mixed, estilo: int}
     */
    protected function normalizar(mixed $celda): array
    {
        if (is_array($celda) && array_key_exists('valor', $celda)) {
            return ['valor' => $celda['valor'], 'estilo' => (int) ($celda['estilo'] ?? self::TEXTO)];
        }

        return ['valor' => $celda, 'estilo' => match (true) {
            is_int($celda) => self::ENTERO,
            is_float($celda) => self::DECIMAL,
            $celda instanceof DateTimeInterface => self::FECHA,
            default => self::TEXTO,
        }];
    }

    public function totalColumnas(): int
    {
        $maximo = 1;
        foreach ($this->filas as $fila) {
            if ($fila['tipo'] === 'normal') {
                $maximo = max($maximo, count($fila['celdas']));
            }
        }

        return $maximo;
    }

    /** XML de la hoja (xl/worksheets/sheetN.xml). */
    public function xml(): string
    {
        $totalColumnas = $this->totalColumnas();
        $anchos = array_fill(0, $totalColumnas, 10.0);
        $filasXml = '';
        $combinadas = [];

        foreach ($this->filas as $indice => $fila) {
            $numeroFila = $indice + 1;
            $atributos = isset($fila['alto']) ? ' ht="'.$fila['alto'].'" customHeight="1"' : '';
            $celdasXml = '';

            foreach ($fila['celdas'] as $columna => $celda) {
                $celdasXml .= $this->celdaXml(self::referencia($columna, $numeroFila), $celda);

                if ($fila['tipo'] === 'normal') {
                    $anchos[$columna] = max($anchos[$columna], $this->anchoSugerido($celda));
                }
            }

            if ($fila['tipo'] === 'ancho' && $totalColumnas > 1) {
                // Las celdas combinadas conservan el fondo del título en todo el ancho.
                $estilo = $fila['celdas'][0]['estilo'];
                for ($columna = 1; $columna < $totalColumnas; $columna++) {
                    $celdasXml .= '<c r="'.self::referencia($columna, $numeroFila).'" s="'.$estilo.'"/>';
                }
                $combinadas[] = 'A'.$numeroFila.':'.self::referencia($totalColumnas - 1, $numeroFila);
            }

            $filasXml .= '<row r="'.$numeroFila.'"'.$atributos.'>'.$celdasXml.'</row>';
        }

        $columnasXml = '';
        foreach ($anchos as $columna => $ancho) {
            $columnasXml .= '<col min="'.($columna + 1).'" max="'.($columna + 1).'" width="'.round(min($ancho, 60), 2).'" customWidth="1"/>';
        }

        $vista = '<sheetViews><sheetView workbookViewId="0" showGridLines="0">';
        if ($this->filaEncabezadoFija) {
            $vista .= '<pane ySplit="'.$this->filaEncabezadoFija.'" topLeftCell="A'.($this->filaEncabezadoFija + 1).'" activePane="bottomLeft" state="frozen"/>';
        }
        $vista .= '</sheetView></sheetViews>';

        $filtro = '';
        if ($this->filaEncabezadoFija) {
            $ultimaFilaTabla = $this->ultimaFilaDeTabla($this->filaEncabezadoFija);
            $filtro = '<autoFilter ref="A'.$this->filaEncabezadoFija.':'.self::referencia($this->columnasEncabezadoFijo - 1, $ultimaFilaTabla).'"/>';
        }

        $combinadasXml = $combinadas === [] ? '' : '<mergeCells count="'.count($combinadas).'">'
            .implode('', array_map(fn ($rango) => '<mergeCell ref="'.$rango.'"/>', $combinadas)).'</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            // Al imprimir, la hoja se ajusta al ancho de una página.
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .$vista
            .'<sheetFormatPr defaultRowHeight="16"/>'
            .'<cols>'.$columnasXml.'</cols>'
            .'<sheetData>'.$filasXml.'</sheetData>'
            .$filtro
            .$combinadasXml
            .'<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/>'
            .'<pageSetup orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /** Última fila contigua de datos debajo del encabezado (hasta la primera fila vacía). */
    protected function ultimaFilaDeTabla(int $filaEncabezado): int
    {
        $ultima = $filaEncabezado;

        for ($indice = $filaEncabezado; $indice < count($this->filas); $indice++) {
            if ($this->filas[$indice]['celdas'] === [] || $this->filas[$indice]['tipo'] !== 'normal') {
                break;
            }
            $ultima = $indice + 1;
        }

        return $ultima;
    }

    /**
     * @param  array{valor: mixed, estilo: int}  $celda
     */
    protected function celdaXml(string $referencia, array $celda): string
    {
        $valor = $celda['valor'];
        $estilo = $celda['estilo'];

        if ($valor === null || $valor === '') {
            return '<c r="'.$referencia.'" s="'.$estilo.'"/>';
        }

        if ($valor instanceof DateTimeInterface) {
            return '<c r="'.$referencia.'" s="'.$estilo.'"><v>'.self::fechaExcel($valor).'</v></c>';
        }

        if (is_int($valor) || is_float($valor)) {
            return '<c r="'.$referencia.'" s="'.$estilo.'"><v>'.(is_float($valor) ? round($valor, 6) : $valor).'</v></c>';
        }

        if (is_bool($valor)) {
            $valor = $valor ? 'Sí' : 'No';
        }

        // Texto siempre como cadena en línea: nunca se interpreta como fórmula.
        return '<c r="'.$referencia.'" s="'.$estilo.'" t="inlineStr"><is><t xml:space="preserve">'.self::escapar((string) $valor).'</t></is></c>';
    }

    /**
     * @param  array{valor: mixed, estilo: int}  $celda
     */
    protected function anchoSugerido(array $celda): float
    {
        $valor = $celda['valor'];

        $longitud = match (true) {
            $valor instanceof DateTimeInterface => 16,
            is_float($valor) => strlen(number_format($valor, 2)),
            is_int($valor) => strlen((string) $valor),
            default => max(array_map('mb_strlen', explode("\n", (string) $valor)) ?: [0]),
        };

        $factor = in_array($celda['estilo'], [self::ENCABEZADO, self::ETIQUETA, self::TOTAL_TEXTO], true) ? 1.2 : 1.1;

        return $longitud * $factor + 3;
    }

    public static function referencia(int $columna, int $fila): string
    {
        $letras = '';
        $columna++;

        while ($columna > 0) {
            $resto = ($columna - 1) % 26;
            $letras = chr(65 + $resto).$letras;
            $columna = intdiv($columna - 1, 26);
        }

        return $letras.$fila;
    }

    /** Número de serie de Excel (días desde 1899-12-30) en la hora local de la fecha. */
    public static function fechaExcel(DateTimeInterface $fecha): float
    {
        $segundos = $fecha->getTimestamp() + $fecha->getOffset();

        return round($segundos / 86400 + 25569, 6);
    }

    public static function escapar(string $texto): string
    {
        // Quita los caracteres de control que XML 1.0 no admite.
        $texto = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $texto) ?? '';

        return htmlspecialchars($texto, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
