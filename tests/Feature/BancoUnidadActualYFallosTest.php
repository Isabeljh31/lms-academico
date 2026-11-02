<?php

namespace Tests\Feature;

use App\Livewire\Compartido\Quizzes\Formulario;
use App\Models\BancoPregunta;
use App\Models\Curso;
use App\Models\Quiz;
use App\Services\Evaluacion\EstadisticaFallosBanco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Banco por unidad actual: las copias en un quiz guardan su origen
 * (banco_pregunta_id y unidad), el % de fallos de cada pregunta del banco
 * se calcula con los intentos enviados, y el formulario del quiz permite
 * incluir unidades anteriores priorizando las más falladas.
 */
class BancoUnidadActualYFallosTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_calcula_el_porcentaje_de_fallos_con_datos_de_ejemplo(): void
    {
        $escenario = $this->escenario();
        ['a' => $a, 'b' => $b, 'c' => $c, 'd' => $d] = $this->bancoConIntentos($escenario);

        $fallos = app(EstadisticaFallosBanco::class)->paraCurso($escenario['curso']);

        // A: 2 de 4 respuestas incorrectas (la copia editada del otro quiz no cuenta).
        $this->assertSame(['respuestas' => 4, 'incorrectas' => 2, 'porcentaje' => 50.0], $fallos[$a->id]);
        // B: copia anterior sin id, cruzada por tema + enunciado + mismo contenido: 1 de 4.
        $this->assertSame(['respuestas' => 4, 'incorrectas' => 1, 'porcentaje' => 25.0], $fallos[$b->id]);
        // C: menos de 3 respuestas => sin porcentaje.
        $this->assertSame(['respuestas' => 2, 'incorrectas' => 2, 'porcentaje' => null], $fallos[$c->id]);
        // D: el tema + enunciado se repite en el banco: el cruce no es seguro.
        $this->assertSame(0, $fallos[$d->id]['respuestas']);

        $this->assertSame('50 % de fallos (4 respuestas)', EstadisticaFallosBanco::texto($fallos[$a->id]));
        $this->assertSame('Sin datos', EstadisticaFallosBanco::texto($fallos[$c->id]));
    }

    public function test_el_quiz_incluye_unidades_anteriores_priorizando_las_mas_falladas(): void
    {
        $escenario = $this->escenario();
        ['a' => $a, 'b' => $b, 'c' => $c] = $this->bancoConIntentos($escenario);
        $this->pregunta($escenario['curso'], 'T2', 'Funciones', 'Pregunta de la unidad actual');

        $componente = Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso']])
            // Unidad actual guardada en el navegador: T2 (T1 queda archivada y fuera del paso "Unidad").
            ->call('abrirBanco', 'T2')
            ->assertSet('unidadBanco', 'T2')
            ->assertViewHas('unidadesBanco', fn ($opciones) => ! isset($opciones['T1']) && $opciones['T2'] === 'T2 (actual)')
            ->assertDontSee('Pregunta A')
            ->set('incluirAnteriores', true)
            ->assertSeeInOrder(['Pregunta A', '50 % de fallos (4 respuestas)', 'Pregunta B', '25 % de fallos (4 respuestas)', 'Pregunta C', 'Sin datos'])
            ->set('ordenAnteriores', 'tema')
            ->assertSeeInOrder(['Pregunta A', 'Pregunta B', 'Pregunta C'])
            ->set('cantidadMasFalladas', '1')
            ->call('seleccionarMasFalladas')
            ->assertSet('seleccionBanco', [(string) $a->id])
            ->call('agregarDesdeBanco')
            ->assertSee('Del banco · T1 · Conjuntos');

        $this->assertSame($a->id, $componente->get('preguntas.0.banco_pregunta_id'));

        $componente->set('tema', 'Repaso de T1')->set('modulo_id', $escenario['modulo']->id)->call('guardar')->assertHasNoErrors();
        $guardada = $escenario['curso']->quizzes()->where('tema', 'Repaso de T1')->sole()->preguntas[0];
        $this->assertSame($a->id, $guardada['banco_pregunta_id']);
        $this->assertSame('T1', $guardada['banco_unidad']);

        // Una unidad guardada que no es del curso: se usa la primera.
        Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso']])
            ->call('abrirBanco', 'T9')
            ->assertSet('unidadBanco', 'T1');
    }

    public function test_el_estudiante_no_recibe_el_origen_de_la_pregunta(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['preguntas' => [
            [...$this->copia('¿Origen oculto?', 0), 'banco_pregunta_id' => 987654, 'banco_unidad' => 'T1', 'banco_tema' => 'Conjuntos', 'editada' => false],
        ]]);

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.quizzes.show', [$escenario['curso'], $escenario['quiz']]))
            ->assertOk()
            ->assertSee('¿Origen oculto?')
            ->assertDontSee('banco_pregunta_id')
            ->assertDontSee('987654')
            ->assertDontSee('banco_unidad')
            ->assertDontSee('banco_tema');
    }

    /**
     * Banco de T1 con intentos enviados de ejemplo.
     *
     * @param  array<string, mixed>  $escenario
     * @return array{a: BancoPregunta, b: BancoPregunta, c: BancoPregunta, d: BancoPregunta}
     */
    private function bancoConIntentos(array $escenario): array
    {
        $curso = $escenario['curso'];
        $a = $this->pregunta($curso, 'T1', 'Conjuntos', 'Pregunta A', 0);
        $b = $this->pregunta($curso, 'T1', 'Conjuntos', 'Pregunta B', 1);
        $c = $this->pregunta($curso, 'T1', 'Lógica', 'Pregunta C', 0);
        $d = $this->pregunta($curso, 'T1', 'Repaso', 'Pregunta D', 0);
        $this->pregunta($curso, 'T1', 'Repaso', 'Pregunta D', 1);

        // Quiz 1: A copiada con id, B y D copias anteriores (solo tema de origen). 4 intentos.
        $this->quizConIntentos($escenario, [
            [...$this->copia('Pregunta A', 0), 'banco_pregunta_id' => $a->id, 'banco_unidad' => 'T1', 'banco_tema' => 'Conjuntos', 'editada' => false],
            [...$this->copia('Pregunta B', 1), 'banco_tema' => 'Conjuntos', 'editada' => false],
            [...$this->copia('Pregunta D', 0), 'banco_tema' => 'Repaso', 'editada' => false],
        ], [[0, 1, 0], [1, 1, 0], [1, 0, 0], [0, 1, 0]]);

        // Quiz 2: A editada en el quiz (no cuenta), todo incorrecto.
        $this->quizConIntentos($escenario, [
            [...$this->copia('Pregunta A editada', 0), 'banco_pregunta_id' => $a->id, 'banco_tema' => 'Conjuntos', 'editada' => true],
        ], [[1], [1], [1]]);

        // Quiz 3: C con solo 2 respuestas.
        $this->quizConIntentos($escenario, [
            [...$this->copia('Pregunta C', 0), 'banco_pregunta_id' => $c->id, 'banco_tema' => 'Lógica', 'editada' => false],
        ], [[1], [1]]);

        return compact('a', 'b', 'c', 'd');
    }

    /**
     * @param  array<string, mixed>  $escenario
     * @param  list<array<string, mixed>>  $preguntas
     * @param  list<list<int>>  $intentos
     */
    private function quizConIntentos(array $escenario, array $preguntas, array $intentos): Quiz
    {
        $quiz = Quiz::factory()->create(['curso_id' => $escenario['curso']->id, 'modulo_id' => $escenario['modulo']->id, 'preguntas' => $preguntas]);

        foreach ($intentos as $numero => $respuestas) {
            $quiz->resultados()->create(['estudiante_id' => $escenario['estudiante']->id, 'intento' => $numero + 1, 'respuestas' => $respuestas, 'puntaje' => 0]);
        }

        return $quiz;
    }

    /**
     * @return array<string, mixed>
     */
    private function copia(string $enunciado, int $correcta): array
    {
        return ['enunciado' => $enunciado, 'tipo' => 'opcion_unica', 'opciones' => ['x', 'y'], 'respuesta_correcta' => $correcta, 'respuestas_correctas' => [$correcta], 'puntaje' => 1];
    }

    private function pregunta(Curso $curso, string $unidad, string $tema, string $texto, int $correcta = 0): BancoPregunta
    {
        return $curso->bancoPreguntasCurso()->create(['unidad' => $unidad, 'tema' => $tema, 'pregunta' => $texto, 'tipo' => 'opcion_unica', 'opciones' => ['x', 'y'], 'respuestas_correctas' => [$correcta]]);
    }
}
