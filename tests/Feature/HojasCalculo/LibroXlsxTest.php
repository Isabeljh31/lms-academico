<?php

namespace Tests\Feature\HojasCalculo;

use App\Services\HojasCalculo\HojaXlsx;
use App\Services\HojasCalculo\LectorXlsx;
use App\Services\HojasCalculo\LibroXlsx;
use Tests\TestCase;
use ZipArchive;

class LibroXlsxTest extends TestCase
{
    /**
     * @return array<string, string> Partes XML del libro generado, por ruta interna.
     */
    protected function partes(LibroXlsx $libro): array
    {
        $ruta = tempnam(sys_get_temp_dir(), 'prueba');
        file_put_contents($ruta, $libro->contenido());

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta));

        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($ruta);

        return $partes;
    }

    protected function libroDeEjemplo(): LibroXlsx
    {
        $libro = new LibroXlsx('Notas');
        $libro->hoja('Notas')
            ->titulo('Libro de calificaciones', 'Curso de prueba')
            ->encabezados(['Estudiante', 'Nota', 'Fecha'], fijar: true)
            ->fila(['=HYPERLINK("http://x")', 12.5, now()])
            ->fila(['Ana & <Luis>', HojaXlsx::celda(9.0, HojaXlsx::DECIMAL_ALERTA), null])
            ->filaTotal(['Promedio', 10.75, '']);

        return $libro;
    }

    public function test_genera_un_paquete_xlsx_con_todas_sus_partes_bien_formadas(): void
    {
        $partes = $this->partes($this->libroDeEjemplo());

        foreach (['[Content_Types].xml', '_rels/.rels', 'xl/workbook.xml', 'xl/_rels/workbook.xml.rels', 'xl/styles.xml', 'xl/worksheets/sheet1.xml'] as $parte) {
            $this->assertArrayHasKey($parte, $partes);
            $this->assertNotFalse(simplexml_load_string($partes[$parte]), "{$parte} no es XML válido");
        }
    }

    public function test_los_textos_nunca_se_guardan_como_formulas_y_los_numeros_si_como_numeros(): void
    {
        $hoja = $this->partes($this->libroDeEjemplo())['xl/worksheets/sheet1.xml'];

        $this->assertStringNotContainsString('<f>', $hoja);
        $this->assertStringContainsString('t="inlineStr"><is><t xml:space="preserve">=HYPERLINK(&quot;http://x&quot;)</t>', $hoja);
        $this->assertStringContainsString('Ana &amp; &lt;Luis&gt;', $hoja);
        $this->assertMatchesRegularExpression('/<c r="B5" s="'.HojaXlsx::DECIMAL.'"><v>12.5<\/v><\/c>/', $hoja);
        $this->assertMatchesRegularExpression('/<c r="B6" s="'.HojaXlsx::DECIMAL_ALERTA.'"><v>9<\/v><\/c>/', $hoja);
    }

    public function test_aplica_titulo_combinado_encabezado_fijo_y_filtro(): void
    {
        $hoja = $this->partes($this->libroDeEjemplo())['xl/worksheets/sheet1.xml'];

        $this->assertStringContainsString('<mergeCell ref="A1:C1"/>', $hoja);
        $this->assertStringContainsString('<c r="A4" s="'.HojaXlsx::ENCABEZADO.'"', $hoja);
        $this->assertStringContainsString('ySplit="4"', $hoja);
        $this->assertStringContainsString('<autoFilter ref="A4:C7"/>', $hoja);
        $this->assertStringContainsString('<c r="A7" s="'.HojaXlsx::TOTAL_TEXTO.'"', $hoja);
        $this->assertStringContainsString('<c r="B7" s="'.HojaXlsx::TOTAL_DECIMAL.'"', $hoja);
    }

    public function test_el_lector_recupera_lo_que_escribe_el_libro(): void
    {
        $libro = new LibroXlsx;
        $libro->hoja('Datos')->encabezados(['a', 'b', 'c'])->fila(['texto', 7, 3.25]);

        $ruta = tempnam(sys_get_temp_dir(), 'prueba');
        file_put_contents($ruta, $libro->contenido());
        $filas = (new LectorXlsx)->filas($ruta);
        unlink($ruta);

        $this->assertSame([['a', 'b', 'c'], ['texto', '7', '3.25']], $filas);
    }

    public function test_los_nombres_de_hoja_invalidos_o_repetidos_se_corrigen(): void
    {
        $libro = new LibroXlsx;
        $libro->hoja('Notas: 2026/I');
        $libro->hoja('Notas: 2026/I');

        $workbook = $this->partes($libro)['xl/workbook.xml'];

        $this->assertStringContainsString('name="Notas  2026 I"', $workbook);
        $this->assertStringContainsString('name="Notas  2026 I 2"', $workbook);
    }
}
