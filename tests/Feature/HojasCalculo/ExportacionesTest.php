<?php

namespace Tests\Feature\HojasCalculo;

use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\PanelAdmin\Auditoria;
use App\Models\BitacoraAuditoria;
use App\Models\NotaRubro;
use App\Services\HojasCalculo\HojaXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;
use ZipArchive;

class ExportacionesTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /**
     * @return array<string, string> Partes XML del .xlsx descargado por el componente.
     */
    protected function partesDescargadas(Testable $componente): array
    {
        $descarga = $componente->effects['download'];
        $ruta = tempnam(sys_get_temp_dir(), 'prueba');
        file_put_contents($ruta, base64_decode($descarga['content']));

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($ruta), 'La descarga no es un .xlsx válido');
        $partes = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $partes[$zip->getNameIndex($i)] = $zip->getFromIndex($i);
        }
        $zip->close();
        unlink($ruta);

        return $partes;
    }

    public function test_el_libro_de_calificaciones_se_exporta_a_excel_con_resumen_notas_y_practica(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 8]);
        NotaRubro::create(['rubro_id' => $rubros[1]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 9]);

        $componente = Livewire::actingAs($escenario['profesor'])
            ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->call('exportarExcel');

        $this->assertStringEndsWith('.xlsx', $componente->effects['download']['name']);
        $partes = $this->partesDescargadas($componente);

        $this->assertStringContainsString('name="Resumen"', $partes['xl/workbook.xml']);
        $this->assertStringContainsString('name="Notas por rubro"', $partes['xl/workbook.xml']);
        $this->assertStringContainsString('name="Práctica"', $partes['xl/workbook.xml']);

        $notas = $partes['xl/worksheets/sheet2.xml'];
        $this->assertStringContainsString($escenario['estudiante']->name, $notas);
        // Nota final 8*0.4 + 9*0.6 = 8.6, bajo la mínima: estilo de alerta y condición "Desaprobado".
        $this->assertMatchesRegularExpression('/s="'.HojaXlsx::DECIMAL_ALERTA.'"><v>8.6<\/v>/', $notas);
        $this->assertStringContainsString('Desaprobado', $notas);
        $this->assertStringContainsString('Promedio del curso', $notas);
    }

    public function test_con_rubros_pendientes_la_exportacion_marca_en_curso_y_no_desaprobado(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;
        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 8]);

        $componente = Livewire::actingAs($escenario['profesor'])
            ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->assertSee('En curso')
            ->call('exportarExcel');

        $notas = $this->partesDescargadas($componente)['xl/worksheets/sheet2.xml'];

        $this->assertStringContainsString('En curso (1 de 2 rubros)', $notas);
        $this->assertStringNotContainsString('Desaprobado', $notas);
        // Nota acumulada 8 × 0.4 = 3.2 sin estilo de alerta.
        $this->assertMatchesRegularExpression('/s="'.HojaXlsx::DECIMAL.'"><v>3.2<\/v>/', $notas);
    }

    // RF-98: la bitácora de auditoría se exporta con los filtros aplicados.
    public function test_la_bitacora_se_exporta_a_excel_respetando_los_filtros(): void
    {
        $escenario = $this->escenario();
        BitacoraAuditoria::registrarAccionAdmin(ejecutor: $escenario['supervisor'], tipoEvento: 'matricula_manual', descripcion: 'Matrícula de prueba', curso: $escenario['curso']);
        BitacoraAuditoria::registrarAccionAdmin(ejecutor: $escenario['supervisor'], tipoEvento: 'creacion_cuenta', descripcion: 'Cuenta de prueba');

        $componente = Livewire::actingAs($escenario['supervisor'])
            ->test(Auditoria::class)
            ->set('filtroTipo', 'matricula_manual')
            ->call('exportarExcel');

        $registros = $this->partesDescargadas($componente)['xl/worksheets/sheet2.xml'];

        $this->assertStringContainsString('Matrícula de prueba', $registros);
        $this->assertStringNotContainsString('Cuenta de prueba', $registros);
    }

    public function test_solo_el_supervisor_exporta_la_bitacora(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['profesor'])->test(Auditoria::class)->assertForbidden();
    }

    public function test_el_supervisor_tambien_puede_exportar_y_un_profesor_ajeno_no(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->usuarioConRol('profesor');

        Livewire::actingAs($escenario['supervisor'])
            ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->call('exportarExcel')
            ->assertFileDownloaded();

        Livewire::actingAs($ajeno)
            ->test(LibroCalificaciones::class, ['curso' => $escenario['curso']])
            ->assertForbidden();
    }
}
