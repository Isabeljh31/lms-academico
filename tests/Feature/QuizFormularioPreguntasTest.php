<?php

namespace Tests\Feature;

use App\Livewire\Quizzes\Formulario;
use App\Models\BancoPregunta;
use App\Models\Curso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Formulario de quiz del Profesor: empieza sin preguntas, con dos caminos
 * (banco o pregunta nueva); las del banco se copian y se ven de solo lectura
 * hasta "Editar"; el puntaje total se reparte solo salvo puntajes manuales.
 */
class QuizFormularioPreguntasTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_un_quiz_nuevo_empieza_sin_preguntas_y_ofrece_las_dos_acciones(): void
    {
        $escenario = $this->escenario();

        $this->nuevoQuiz($escenario)
            ->assertSet('preguntas', [])
            ->assertSee('Agregar desde el banco de preguntas')
            ->assertSee('Escribir una pregunta nueva')
            ->assertSee('Tu banco está vacío. Crea o importa preguntas en la pestaña Banco de preguntas.')
            ->assertSee('campos-sin-flechas')
            ->set('tema', 'Quiz vacío')
            ->set('modulo_id', $escenario['modulo']->id)
            ->call('guardar')
            ->assertHasErrors(['preguntas' => 'required'])
            ->assertSee('Agrega al menos una pregunta al quiz');

        $this->assertSame(0, $escenario['curso']->quizzes()->where('tema', 'Quiz vacío')->count());
    }

    public function test_el_panel_del_banco_filtra_selecciona_y_copia_las_preguntas(): void
    {
        $escenario = $this->escenario();
        $suma = $this->pregunta($escenario['curso'], 'Álgebra', '¿Cuánto es 2 + 2?');
        $this->pregunta($escenario['curso'], 'Álgebra', '¿Cuánto es 3 x 3?');
        $this->pregunta($escenario['curso'], 'Lógica', 'Una proposición', 'T2');

        $componente = $this->nuevoQuiz($escenario)
            ->assertDontSee('Tu banco está vacío')
            ->call('abrirBanco')
            // Dos pasos: la unidad (por defecto la actual) y luego solo los temas de esa unidad.
            ->assertSet('unidadBanco', 'T1')
            ->assertViewHas('unidadesBanco', ['T1' => 'T1 (actual)', 'T2' => 'T2'])
            ->assertViewHas('temasBanco', fn ($temas) => $temas->all() === ['Álgebra'])
            ->set('temaBanco', 'Álgebra')
            ->assertSee('¿Cuánto es 3 x 3?')
            ->assertDontSee('Una proposición')
            ->set('busquedaBanco', '2 + 2')
            ->assertSee('¿Cuánto es 2 + 2?')
            ->assertDontSee('¿Cuánto es 3 x 3?')
            ->assertSee('Seleccionar todas (1)')
            ->set('seleccionBanco', [$suma->id])
            ->call('agregarDesdeBanco')
            ->assertSet('mostrarBanco', false)
            // Compacta y de solo lectura, con su origen.
            ->assertSee('Del banco · T1 · Álgebra')
            ->assertDontSee('id="preguntas.0.enunciado"', false)
            ->assertSee('Editar la pregunta 1');

        $componente->set('tema', 'Quiz del banco')->set('modulo_id', $escenario['modulo']->id)->call('guardar')->assertHasNoErrors();

        $guardada = $escenario['curso']->quizzes()->where('tema', 'Quiz del banco')->sole()->preguntas[0];
        $this->assertSame('¿Cuánto es 2 + 2?', $guardada['enunciado']);
        $this->assertSame('T1', $guardada['banco_unidad']);
        $this->assertSame('Álgebra', $guardada['banco_tema']);
        $this->assertFalse($guardada['editada']);
    }

    public function test_editar_una_pregunta_del_banco_la_marca_como_editada_sin_cambiar_el_banco(): void
    {
        $escenario = $this->escenario();
        $original = $this->pregunta($escenario['curso'], 'Álgebra', '¿Cuánto es 2 + 2?');

        $componente = $this->nuevoQuiz($escenario)
            ->call('abrirBanco')->set('unidadBanco', 'T1')->set('temaBanco', 'Álgebra')->set('seleccionBanco', [$original->id])->call('agregarDesdeBanco')
            ->assertDontSee('>Editada<', false)
            ->call('editarPreguntaDelBanco', 0)
            ->assertSee('id="preguntas.0.enunciado"', false)
            ->set('preguntas.0.enunciado', '¿Cuánto es 2 + 3?')
            ->assertSee('Editada')
            ->set('tema', 'Quiz editado')->set('modulo_id', $escenario['modulo']->id)
            ->call('guardar')->assertHasNoErrors();

        $guardada = $escenario['curso']->quizzes()->where('tema', 'Quiz editado')->sole()->preguntas[0];
        $this->assertSame('¿Cuánto es 2 + 3?', $guardada['enunciado']);
        $this->assertTrue($guardada['editada']);
        $this->assertSame('¿Cuánto es 2 + 2?', $original->fresh()->pregunta);
    }

    public function test_el_total_se_reparte_solo_salvo_que_haya_un_puntaje_manual(): void
    {
        $escenario = $this->escenario();

        $componente = $this->nuevoQuiz($escenario)
            ->call('agregarPregunta')
            ->call('agregarPregunta');
        $this->assertEquals([10, 10], array_column($componente->get('preguntas'), 'puntaje'));

        $componente->call('agregarPregunta');
        $this->assertEquals([6.67, 6.67, 6.66], array_column($componente->get('preguntas'), 'puntaje'));

        $componente->call('eliminarPregunta', 2)->set('puntaje_total', '16');
        $this->assertEquals([8, 8], array_column($componente->get('preguntas'), 'puntaje'));

        // Un puntaje a mano no se pisa: al agregar, la nueva trae 1 y queda visible la suma frente al total.
        $componente->set('preguntas.0.puntaje', 5)->call('agregarPregunta')
            ->assertSee('Suma actual: 14 / Total: 16')
            ->assertSee('Repartir en partes iguales');
        $this->assertEquals([5, 8, 1], array_column($componente->get('preguntas'), 'puntaje'));

        // Repartir a pedido vuelve todo a automático.
        $componente->call('repartirEnPartesIguales')->call('agregarPregunta');
        $this->assertEquals([4, 4, 4, 4], array_column($componente->get('preguntas'), 'puntaje'));
    }

    public function test_un_quiz_guardado_con_puntajes_propios_no_se_reparte_al_editarlo(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['preguntas' => [
            ['enunciado' => 'A', 'tipo' => 'opcion_unica', 'opciones' => ['x', 'y'], 'respuesta_correcta' => 0, 'respuestas_correctas' => [0], 'puntaje' => 3],
            ['enunciado' => 'B', 'tipo' => 'opcion_unica', 'opciones' => ['x', 'y'], 'respuesta_correcta' => 0, 'respuestas_correctas' => [0], 'puntaje' => 7],
        ]]);

        $componente = Livewire::actingAs($escenario['profesor'])
            ->test(Formulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->call('agregarPregunta');

        $this->assertEquals([3, 7, 1], array_column($componente->get('preguntas'), 'puntaje'));
    }

    public function test_con_intentos_las_preguntas_del_banco_no_se_pueden_editar(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->update(['preguntas' => [
            ['enunciado' => 'Del banco', 'tipo' => 'opcion_unica', 'opciones' => ['x', 'y'], 'respuesta_correcta' => 0, 'respuestas_correctas' => [0], 'puntaje' => 1, 'banco_tema' => 'Álgebra', 'editada' => false],
        ]]);
        $escenario['quiz']->resultados()->create(['estudiante_id' => $escenario['estudiante']->id, 'intento' => 1, 'respuestas' => [0], 'puntaje' => 20]);

        Livewire::actingAs($escenario['profesor'])
            ->test(Formulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->assertSee('Del banco · Álgebra')
            ->assertDontSee('Editar la pregunta 1')
            ->assertDontSee('Escribir una pregunta nueva')
            ->call('editarPreguntaDelBanco', 0)
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function nuevoQuiz(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso']]);
    }

    private function pregunta(Curso $curso, string $tema, string $texto, ?string $unidad = 'T1'): BancoPregunta
    {
        return $curso->bancoPreguntasCurso()->create(['unidad' => $unidad, 'tema' => $tema, 'pregunta' => $texto, 'tipo' => 'opcion_unica', 'opciones' => ['4', '5'], 'respuestas_correctas' => [0]]);
    }
}
