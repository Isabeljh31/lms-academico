<?php

namespace Tests\Unit\Services\Asistencia;

use App\Services\Asistencia\CalculadoraAsistencia;
use PHPUnit\Framework\TestCase;

/**
 * ERS RF-71: porcentaje de asistencia acumulado.
 */
class CalculadoraAsistenciaTest extends TestCase
{
    public function test_presente_y_tardanza_cuentan_como_asistencia(): void
    {
        $calculadora = new CalculadoraAsistencia;

        $this->assertSame(50.0, $calculadora->porcentaje(['presente', 'tardanza', 'ausente', 'ausente']));
    }

    public function test_el_justificado_no_cuenta_a_favor_ni_en_contra(): void
    {
        $calculadora = new CalculadoraAsistencia;

        $this->assertSame(100.0, $calculadora->porcentaje(['presente', 'justificado']));
        $this->assertSame(0.0, $calculadora->porcentaje(['ausente', 'justificado']));
    }

    public function test_una_sesion_sin_registro_cuenta_como_ausencia(): void
    {
        $this->assertSame(50.0, (new CalculadoraAsistencia)->porcentaje(['presente', null]));
    }

    public function test_sin_sesiones_consideradas_el_porcentaje_es_nulo(): void
    {
        $calculadora = new CalculadoraAsistencia;

        $this->assertNull($calculadora->porcentaje([]));
        $this->assertNull($calculadora->porcentaje(['justificado']));
    }
}
