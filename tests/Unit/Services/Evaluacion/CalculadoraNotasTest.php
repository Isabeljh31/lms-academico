<?php

namespace Tests\Unit\Services\Evaluacion;

use App\Models\RubroEvaluacion;
use App\Services\ConfiguracionPlataforma;
use App\Services\Evaluacion\CalculadoraNotas;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * ERS RF-56 (pesos al 100 %), RF-58 (nota final ponderada 0–20) y RF-64
 * (aprobación con la nota mínima configurable).
 */
class CalculadoraNotasTest extends TestCase
{
    protected function calculadora(float $notaMinima = 10.5): CalculadoraNotas
    {
        $configuracion = $this->createStub(ConfiguracionPlataforma::class);
        $configuracion->method('notaMinimaAprobatoria')->willReturn($notaMinima);

        return new CalculadoraNotas($configuracion);
    }

    /**
     * @param  array<int, float>  $pesos  id => peso
     * @return Collection<int, RubroEvaluacion>
     */
    protected function rubros(array $pesos): Collection
    {
        return collect($pesos)->map(function (float $peso, int $id) {
            $rubro = new RubroEvaluacion(['peso' => $peso]);
            $rubro->id = $id;

            return $rubro;
        })->values();
    }

    public function test_acepta_pesos_que_suman_exactamente_cien(): void
    {
        $this->assertTrue($this->calculadora()->pesosSumanCien([30, 30, 40]));
        $this->assertTrue($this->calculadora()->pesosSumanCien(['33.33', '33.33', '33.34']));
    }

    public function test_rechaza_pesos_que_no_suman_cien(): void
    {
        $this->assertFalse($this->calculadora()->pesosSumanCien([30, 30, 30]));
        $this->assertFalse($this->calculadora()->pesosSumanCien([50, 51]));
        $this->assertFalse($this->calculadora()->pesosSumanCien([]));
    }

    public function test_calcula_la_nota_final_como_suma_ponderada(): void
    {
        $rubros = $this->rubros([1 => 40, 2 => 60]);

        // 12 × 0,40 + 15 × 0,60 = 4,8 + 9 = 13,8
        $this->assertSame(13.8, $this->calculadora()->notaFinal($rubros, [1 => 12, 2 => 15]));
    }

    public function test_un_rubro_sin_nota_aporta_cero_a_la_nota_final(): void
    {
        $rubros = $this->rubros([1 => 40, 2 => 60]);

        $this->assertSame(8.0, $this->calculadora()->notaFinal($rubros, [1 => 20, 2 => null]));
    }

    public function test_sin_ninguna_nota_la_nota_final_es_nula(): void
    {
        $this->assertNull($this->calculadora()->notaFinal($this->rubros([1 => 100]), []));
    }

    public function test_la_nota_final_se_mantiene_en_la_escala_de_cero_a_veinte(): void
    {
        $rubros = $this->rubros([1 => 50, 2 => 50]);

        $this->assertSame(20.0, $this->calculadora()->notaFinal($rubros, [1 => 20, 2 => 20]));
        $this->assertSame(0.0, $this->calculadora()->notaFinal($rubros, [1 => 0, 2 => 0]));
    }

    public function test_aprueba_solo_con_nota_igual_o_mayor_a_la_minima_configurada(): void
    {
        $calculadora = $this->calculadora(11);

        $this->assertTrue($calculadora->aprueba(11.0));
        $this->assertFalse($calculadora->aprueba(10.9));
        $this->assertFalse($calculadora->aprueba(null));
    }

    public function test_con_rubros_pendientes_la_condicion_es_en_curso_y_no_desaprobado(): void
    {
        $rubros = $this->rubros([1 => 40, 2 => 60]);

        // 16 × 0.4 = 6.4 acumulado: todavía no puede desaprobar porque falta el 60 %.
        $this->assertSame('en_curso', $this->calculadora()->condicion($rubros, [1 => 16]));
        $this->assertFalse($this->calculadora()->estaCompleta($rubros, [1 => 16]));
    }

    public function test_con_todos_los_rubros_la_condicion_es_aprobado_o_desaprobado(): void
    {
        $rubros = $this->rubros([1 => 40, 2 => 60]);

        $this->assertSame('aprobado', $this->calculadora()->condicion($rubros, [1 => 16, 2 => 10]));
        $this->assertSame('desaprobado', $this->calculadora()->condicion($rubros, [1 => 8, 2 => 10]));
        $this->assertSame('sin_notas', $this->calculadora()->condicion($rubros, []));
    }

    public function test_el_rendimiento_promedia_solo_los_rubros_calificados(): void
    {
        $rubros = $this->rubros([1 => 20, 2 => 30, 3 => 50]);

        // (15 × 20 + 10 × 30) / 50 = 12
        $this->assertSame(12.0, $this->calculadora()->rendimiento($rubros, [1 => 15, 2 => 10]));
        $this->assertNull($this->calculadora()->rendimiento($rubros, []));
    }

    public function test_la_tasa_de_aprobacion_solo_cuenta_esquemas_completos(): void
    {
        $calculadora = $this->calculadora();

        $this->assertSame(50, $calculadora->tasaAprobacion([
            ['condicion' => 'aprobado'], ['condicion' => 'desaprobado'], ['condicion' => 'en_curso'], ['condicion' => 'sin_notas'],
        ]));
        $this->assertNull($calculadora->tasaAprobacion([['condicion' => 'en_curso']]));
    }
}
