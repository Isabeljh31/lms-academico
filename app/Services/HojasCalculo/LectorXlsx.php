<?php

namespace App\Services\HojasCalculo;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Lee la primera hoja de un archivo .xlsx (Office Open XML) sin dependencias
 * externas: el formato es un ZIP con XML, así que bastan las extensiones zip
 * y SimpleXML de PHP. Solo se leen valores (texto, número, booleano); las
 * fórmulas se leen por su último valor calculado.
 *
 * ERS RF-11 (v2) / RF-12 (numeración del pedido): importación desde Excel.
 */
class LectorXlsx
{
    /** Tamaño máximo descomprimido de un XML interno (protege de ZIP bombs). */
    public const MAX_BYTES_XML = 20 * 1024 * 1024;

    /**
     * @return list<list<string>> Filas de la primera hoja, con las celdas vacías como ''.
     *
     * @throws RuntimeException si el archivo no es un .xlsx válido.
     */
    public function filas(string $ruta): array
    {
        // libzip no abre rutas largas en Windows (las temporales de Livewire superan los 260
        // caracteres), así que se trabaja sobre una copia con ruta corta.
        $copia = tempnam(sys_get_temp_dir(), 'xls');

        if ($copia === false || ! @copy($ruta, $copia)) {
            throw new RuntimeException('No se pudo leer el archivo.');
        }

        $zip = new ZipArchive;

        if ($zip->open($copia, ZipArchive::RDONLY) !== true) {
            @unlink($copia);

            throw new RuntimeException('El archivo no es un libro de Excel (.xlsx) válido.');
        }

        try {
            $textosCompartidos = $this->textosCompartidos($zip);
            $hoja = $this->xml($zip, $this->rutaPrimeraHoja($zip));

            if (! $hoja) {
                throw new RuntimeException('El libro de Excel no contiene hojas.');
            }

            $filas = [];

            foreach ($hoja->sheetData->row ?? [] as $fila) {
                $celdas = [];

                foreach ($fila->c as $celda) {
                    $indice = $this->indiceColumna((string) $celda['r']) ?? count($celdas);
                    $celdas[$indice] = $this->valorCelda($celda, $textosCompartidos);
                }

                if ($celdas === []) {
                    $filas[] = [];

                    continue;
                }

                $completa = array_fill(0, max(array_keys($celdas)) + 1, '');
                foreach ($celdas as $indice => $valor) {
                    $completa[$indice] = $valor;
                }
                $filas[] = $completa;
            }

            return $filas;
        } finally {
            $zip->close();
            @unlink($copia);
        }
    }

    protected function rutaPrimeraHoja(ZipArchive $zip): string
    {
        $libro = $this->xml($zip, 'xl/workbook.xml');
        $relaciones = $this->xml($zip, 'xl/_rels/workbook.xml.rels');

        if (! $libro || ! $relaciones || ! isset($libro->sheets->sheet[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $idRelacion = (string) $libro->sheets->sheet[0]->attributes('r', true)->id;

        foreach ($relaciones->Relationship as $relacion) {
            if ((string) $relacion['Id'] === $idRelacion) {
                $destino = ltrim((string) $relacion['Target'], '/');

                return str_starts_with($destino, 'xl/') ? $destino : 'xl/'.$destino;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return list<string>
     */
    protected function textosCompartidos(ZipArchive $zip): array
    {
        $xml = $this->xml($zip, 'xl/sharedStrings.xml');

        if (! $xml) {
            return [];
        }

        $textos = [];
        foreach ($xml->si as $item) {
            $textos[] = $this->textoEnriquecido($item);
        }

        return $textos;
    }

    /**
     * @param  list<string>  $textosCompartidos
     */
    protected function valorCelda(SimpleXMLElement $celda, array $textosCompartidos): string
    {
        $tipo = (string) $celda['t'];
        $valor = (string) ($celda->v ?? '');

        return match ($tipo) {
            's' => $textosCompartidos[(int) $valor] ?? '',
            'inlineStr' => $this->textoEnriquecido($celda->is),
            'b' => $valor === '1' ? 'VERDADERO' : 'FALSO',
            'str', 'e' => $valor,
            default => $this->numero($valor),
        };
    }

    /** Un texto puede venir como <t> simple o como varios fragmentos <r><t> con formato. */
    protected function textoEnriquecido(?SimpleXMLElement $nodo): string
    {
        if (! $nodo) {
            return '';
        }

        if (isset($nodo->t)) {
            return (string) $nodo->t;
        }

        $texto = '';
        foreach ($nodo->r as $fragmento) {
            $texto .= (string) $fragmento->t;
        }

        return $texto;
    }

    /** Excel guarda 12 como "12" o "12.0"; se devuelve sin ceros de relleno. */
    protected function numero(string $valor): string
    {
        if ($valor === '' || ! is_numeric($valor)) {
            return $valor;
        }

        $numero = (float) $valor;

        return floor($numero) === $numero && abs($numero) < PHP_INT_MAX
            ? (string) (int) $numero
            : (string) $numero;
    }

    protected function indiceColumna(string $referencia): ?int
    {
        if (! preg_match('/^([A-Z]+)\d*$/', strtoupper($referencia), $coincidencia)) {
            return null;
        }

        $indice = 0;
        foreach (str_split($coincidencia[1]) as $letra) {
            $indice = $indice * 26 + (ord($letra) - 64);
        }

        return $indice - 1;
    }

    protected function xml(ZipArchive $zip, string $ruta): ?SimpleXMLElement
    {
        $info = $zip->statName($ruta);

        if ($info === false) {
            return null;
        }

        if ($info['size'] > self::MAX_BYTES_XML) {
            throw new RuntimeException('El libro de Excel es demasiado grande.');
        }

        $contenido = $zip->getFromName($ruta);

        if ($contenido === false) {
            return null;
        }

        // Un .xlsx legítimo nunca declara DOCTYPE: se rechaza para evitar XXE y expansión de entidades.
        if (stripos($contenido, '<!DOCTYPE') !== false) {
            throw new RuntimeException('El libro de Excel no es válido.');
        }

        $xml = simplexml_load_string($contenido, SimpleXMLElement::class, LIBXML_NONET);

        return $xml === false ? null : $xml;
    }
}
