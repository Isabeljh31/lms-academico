<?php

namespace Tests\Unit\Models;

use App\Models\Quiz;
use Tests\TestCase;

/**
 * ERS RF-44 a RF-46: corrección automática de opción única, múltiple y V/F,
 * con puntaje por pregunta en escala 0–20.
 */
class QuizTest extends TestCase
{
    protected function quiz(): Quiz
    {
        return new Quiz(['preguntas' => [
            ['enunciado' => 'Única', 'tipo' => 'opcion_unica', 'opciones' => ['a', 'b', 'c'], 'respuesta_correcta' => 1, 'puntaje' => 1],
            ['enunciado' => 'Múltiple', 'tipo' => 'opcion_multiple', 'opciones' => ['a', 'b', 'c'], 'respuestas_correctas' => [0, 2], 'puntaje' => 2],
            ['enunciado' => 'V/F', 'tipo' => 'verdadero_falso', 'opciones' => ['Verdadero', 'Falso'], 'respuesta_correcta' => 0, 'puntaje' => 1],
        ]]);
    }

    public function test_todas_correctas_dan_veinte(): void
    {
        $this->assertSame(20.0, $this->quiz()->calcularPuntaje([1, [2, 0], 0]));
    }

    public function test_la_opcion_multiple_exige_exactamente_las_correctas(): void
    {
        // Falta una correcta en la múltiple (vale 2 de 4 puntos): 2/4 × 20 = 10.
        $this->assertSame(10.0, $this->quiz()->calcularPuntaje([1, [0], 0]));
        // Una incorrecta de más también la invalida.
        $this->assertSame(10.0, $this->quiz()->calcularPuntaje([1, [0, 1, 2], 0]));
    }

    public function test_el_puntaje_de_cada_pregunta_pondera_el_resultado(): void
    {
        // Solo la múltiple correcta: 2 de 4 puntos.
        $this->assertSame(10.0, $this->quiz()->calcularPuntaje([0, [0, 2], 1]));
    }

    public function test_compara_respuestas_enviadas_como_texto(): void
    {
        $this->assertSame(20.0, $this->quiz()->calcularPuntaje(['1', ['0', '2'], '0']));
    }

    public function test_respuestas_vacias_no_suman_puntos(): void
    {
        $this->assertSame(0.0, $this->quiz()->calcularPuntaje([null, [], null]));
    }

    public function test_lee_la_configuracion_con_valores_por_defecto(): void
    {
        $quiz = new Quiz(['configuracion' => ['intentos' => 3, 'tiempo_limite_min' => 0]]);

        $this->assertSame(3, $quiz->intentosPermitidos());
        $this->assertNull($quiz->tiempoLimiteMinutos());
        $this->assertTrue((new Quiz)->muestraRetroalimentacion());
        $this->assertSame(1, (new Quiz)->intentosPermitidos());
    }
}
