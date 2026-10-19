<?php

namespace App\Services\HojasCalculo;

use App\Services\ConfiguracionPlataforma;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) con formato, generado sin dependencias externas
 * (extensión zip de PHP). Se usa para todas las exportaciones de LearnUp.
 *
 * Paleta: la de las pantallas de Profesor y Supervisor (menta oscuro en
 * encabezados, menta suave en secciones, terracota para alertas).
 *
 * Uso:
 *   $libro = new LibroXlsx('Libro de calificaciones');
 *   $libro->hoja('Notas')->titulo('...', '...')->encabezados([...], fijar: true)->fila([...]);
 *   return $libro->descargar('notas.xlsx');
 */
class LibroXlsx
{
    public const TIPO_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var list<HojaXlsx> */
    protected array $hojas = [];

    /**
     * @param  string|null  $formatoFechaHora  Código de Excel para las celdas de fecha; por defecto, el formato elegido en Configuración (RF-108).
     */
    public function __construct(protected string $titulo = 'LearnUp', protected ?string $formatoFechaHora = null) {}

    public function hoja(string $nombre): HojaXlsx
    {
        $hoja = new HojaXlsx($this->nombreHojaValido($nombre));
        $this->hojas[] = $hoja;

        return $hoja;
    }

    public function descargar(string $nombreArchivo): StreamedResponse
    {
        $contenido = $this->contenido();

        return response()->streamDownload(function () use ($contenido) {
            echo $contenido;
        }, $nombreArchivo, ['Content-Type' => self::TIPO_MIME]);
    }

    /** Bytes del archivo .xlsx. */
    public function contenido(): string
    {
        if ($this->hojas === []) {
            $this->hoja('Hoja 1');
        }

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive;

        if ($ruta === false || $zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo generar el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido());
        $zip->addFromString('_rels/.rels', $this->relacionesRaiz());
        $zip->addFromString('docProps/core.xml', $this->propiedades());
        $zip->addFromString('xl/workbook.xml', $this->libro());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesLibro());
        $zip->addFromString('xl/styles.xml', $this->estilos());

        foreach ($this->hojas as $indice => $hoja) {
            $zip->addFromString('xl/worksheets/sheet'.($indice + 1).'.xml', $hoja->xml());
        }

        $zip->close();
        $contenido = (string) file_get_contents($ruta);
        @unlink($ruta);

        return $contenido;
    }

    protected function nombreHojaValido(string $nombre): string
    {
        // Excel no admite : \ / ? * [ ] en el nombre y lo limita a 31 caracteres.
        $nombre = trim(str_replace([':', '\\', '/', '?', '*', '[', ']'], ' ', $nombre)) ?: 'Hoja';
        $nombre = mb_substr($nombre, 0, 31);
        $base = $nombre;
        $contador = 2;

        while (in_array($nombre, array_map(fn (HojaXlsx $hoja) => $hoja->nombre, $this->hojas), true)) {
            $nombre = mb_substr($base, 0, 28).' '.$contador++;
        }

        return $nombre;
    }

    protected function tiposDeContenido(): string
    {
        $hojas = '';
        foreach ($this->hojas as $indice => $hoja) {
            $hojas .= '<Override PartName="/xl/worksheets/sheet'.($indice + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .$hojas
            .'</Types>';
    }

    protected function relacionesRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'</Relationships>';
    }

    protected function propiedades(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:title>'.HojaXlsx::escapar($this->titulo).'</dc:title>'
            .'<dc:creator>LearnUp</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->utc()->format('Y-m-d\TH:i:s\Z').'</dcterms:created>'
            .'</cp:coreProperties>';
    }

    protected function libro(): string
    {
        $hojas = '';
        foreach ($this->hojas as $indice => $hoja) {
            $hojas .= '<sheet name="'.HojaXlsx::escapar($hoja->nombre).'" sheetId="'.($indice + 1).'" r:id="rId'.($indice + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$hojas.'</sheets>'
            .'</workbook>';
    }

    protected function relacionesLibro(): string
    {
        $relaciones = '';
        foreach ($this->hojas as $indice => $hoja) {
            $relaciones .= '<Relationship Id="rId'.($indice + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($indice + 1).'.xml"/>';
        }
        $relaciones .= '<Relationship Id="rId'.(count($this->hojas) + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relaciones.'</Relationships>';
    }

    /**
     * Estilos. El orden de <cellXfs> define las constantes de HojaXlsx.
     */
    protected function estilos(): string
    {
        $borde = 'borderId="1" applyBorder="1"';
        $xf = fn (int $fuente, int $relleno, string $extra = '', int $formato = 0, string $alineacion = '<alignment vertical="center"/>') => '<xf numFmtId="'.$formato.'" fontId="'.$fuente.'" fillId="'.$relleno.'" '.$extra
            .' applyFont="1" applyFill="1"'.($formato ? ' applyNumberFormat="1"' : '').' applyAlignment="1">'.$alineacion.'</xf>';

        $celdas = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0"/>',                                         // 0 por defecto
            $xf(2, 2, 'borderId="0"', 0, '<alignment vertical="center" indent="1"/>'),                        // 1 TITULO
            $xf(4, 0, 'borderId="0"', 0, '<alignment vertical="center" indent="1"/>'),                        // 2 SUBTITULO
            $xf(3, 2, $borde, 0, '<alignment horizontal="center" vertical="center" wrapText="1"/>'),         // 3 ENCABEZADO
            $xf(0, 0, $borde),                                                                                // 4 TEXTO
            $xf(0, 0, $borde, 164, '<alignment horizontal="right" vertical="center"/>'),                     // 5 DECIMAL
            $xf(0, 0, $borde, 1, '<alignment horizontal="right" vertical="center"/>'),                       // 6 ENTERO
            $xf(0, 0, $borde, 165, '<alignment horizontal="right" vertical="center"/>'),                     // 7 PORCENTAJE
            $xf(0, 0, $borde, 166, '<alignment horizontal="left" vertical="center"/>'),                      // 8 FECHA
            $xf(7, 3, 'borderId="2" applyBorder="1"', 0, '<alignment vertical="center" indent="1"/>'),        // 9 SECCION
            $xf(5, 5, $borde, 164, '<alignment horizontal="right" vertical="center"/>'),                     // 10 DECIMAL_ALERTA
            $xf(1, 4, $borde),                                                                                // 11 TOTAL_TEXTO
            $xf(1, 4, $borde, 164, '<alignment horizontal="right" vertical="center"/>'),                     // 12 TOTAL_DECIMAL
            $xf(6, 0, $borde),                                                                                // 13 TEXTO_EXITO
            $xf(5, 0, $borde),                                                                                // 14 TEXTO_ALERTA
            $xf(1, 3, $borde),                                                                                // 15 ETIQUETA
            $xf(6, 0, $borde, 164, '<alignment horizontal="right" vertical="center"/>'),                     // 16 DECIMAL_EXITO
            $xf(1, 4, $borde, 165, '<alignment horizontal="right" vertical="center"/>'),                     // 17 TOTAL_PORCENTAJE
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3">'
            .'<numFmt numFmtId="164" formatCode="0.00"/>'
            .'<numFmt numFmtId="165" formatCode="0.0&quot;%&quot;"/>'
            .'<numFmt numFmtId="166" formatCode="'.($this->formatoFechaHora ?? app(ConfiguracionPlataforma::class)->formatoFechaHoraExcel()).'"/>'
            .'</numFmts>'
            .'<fonts count="8">'
            .'<font><sz val="11"/><color rgb="FF292524"/><name val="Calibri"/><family val="2"/></font>'                 // 0 normal
            .'<font><b/><sz val="11"/><color rgb="FF292524"/><name val="Calibri"/><family val="2"/></font>'             // 1 negrita
            .'<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'             // 2 título
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/><family val="2"/></font>'             // 3 encabezado
            .'<font><i/><sz val="10"/><color rgb="FF78716C"/><name val="Calibri"/><family val="2"/></font>'             // 4 subtítulo
            .'<font><b/><sz val="11"/><color rgb="FFB2604A"/><name val="Calibri"/><family val="2"/></font>'             // 5 alerta
            .'<font><b/><sz val="11"/><color rgb="FF3F6B4A"/><name val="Calibri"/><family val="2"/></font>'             // 6 éxito
            .'<font><b/><sz val="12"/><color rgb="FF3C7A75"/><name val="Calibri"/><family val="2"/></font>'             // 7 sección
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF3C7A75"/><bgColor indexed="64"/></patternFill></fill>' // 2 menta oscuro
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFECF8F8"/><bgColor indexed="64"/></patternFill></fill>' // 3 menta suave
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEE4E1"/><bgColor indexed="64"/></patternFill></fill>' // 4 arena
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8E7E0"/><bgColor indexed="64"/></patternFill></fill>' // 5 alerta suave
            .'</fills>'
            .'<borders count="3">'
            .'<border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFD6D3D1"/></left><right style="thin"><color rgb="FFD6D3D1"/></right><top style="thin"><color rgb="FFD6D3D1"/></top><bottom style="thin"><color rgb="FFD6D3D1"/></bottom><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="medium"><color rgb="FF3C7A75"/></bottom><diagonal/></border>'
            .'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($celdas).'">'.implode('', $celdas).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
