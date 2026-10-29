<?php

namespace Tests\Feature;

use App\Livewire\Profesor\BancoPreguntas;
use App\Livewire\Quizzes\Formulario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * El puntaje se decide en el quiz: puntaje total (0–20, por defecto 20),
 * "Repartir en partes iguales" y aviso cuando la suma no coincide. El banco
 * solo propone un puntaje sugerido.
 */
class QuizPuntajeTotalTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_repartir_en_partes_iguales_cuadra_la_suma_con_el_total(): void
    {
        $escenario = $this->escenario();

        $componente = $this->nuevoQuiz($escenario)
            ->assertSet('puntaje_total', '20')
            ->set('preguntas', $this->preguntas(3))
            ->call('repartirEnPartesIguales')
            ->assertHasNoErrors();
        $this->assertSame([6.67, 6.67, 6.66], array_column($componente->get('preguntas'), 'puntaje'));
        $componente->assertSee('Suma actual: 20 / Total: 20');

        $componente->set('puntaje_total', '10')->set('preguntas', $this->preguntas(4))->call('repartirEnPartesIguales');
        $this->assertSame([2.5, 2.5, 2.5, 2.5], array_column($componente->get('preguntas'), 'puntaje'));

        // El puntaje de cada pregunta sigue editable a mano.
        $componente->set('preguntas.0.puntaje', 4)->assertSee('Suma actual: 11.5 / Total: 10');
    }

    public function test_el_total_se_valida_y_el_reparto_respeta_el_minimo_por_pregunta(): void
    {
        $escenario = $this->escenario();

        $this->nuevoQuiz($escenario)->set('preguntas', $this->preguntas(2))
            ->set('puntaje_total', '25')->call('repartirEnPartesIguales')->assertHasErrors(['puntaje_total' => 'max'])
            ->set('puntaje_total', '0')->call('repartirEnPartesIguales')->assertHasErrors(['puntaje_total' => 'gt'])
            ->set('puntaje_total', '1')->set('preguntas', $this->preguntas(3))->call('repartirEnPartesIguales')
            ->assertHasErrors('puntaje_total')
            ->assertSee('cada una debe valer al menos 0.5');
    }

    public function test_si_la_suma_no_coincide_avisa_pero_se_guarda_y_el_total_se_conserva(): void
    {
        $escenario = $this->escenario();

        $this->nuevoQuiz($escenario)
            ->set('tema', 'Quiz de suma')
            ->set('modulo_id', $escenario['modulo']->id)
            ->set('puntaje_total', '15')
            ->set('preguntas', $this->preguntas(2))
            ->assertSee('Suma actual: 2 / Total: 15')
            ->assertSee('Los puntajes no suman el total')
            ->call('guardar')
            ->assertHasNoErrors();

        $quiz = $escenario['curso']->quizzes()->where('tema', 'Quiz de suma')->sole();
        $this->assertEquals(15, $quiz->configuracion['puntaje_total']);

        Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso'], 'quiz' => $quiz])
            ->assertSet('puntaje_total', '15');

        // Un quiz guardado antes de este cambio (sin total) toma 20.
        Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->assertSet('puntaje_total', '20');
    }

    public function test_con_un_puntaje_manual_las_del_banco_traen_su_puntaje_sugerido_sin_repartir(): void
    {
        $escenario = $this->escenario();
        $sugerida = $escenario['curso']->bancoPreguntasCurso()->create(['tema' => 'Álgebra', 'pregunta' => '¿3 + 3?', 'tipo' => 'opcion_unica', 'opciones' => ['5', '6'], 'respuestas_correctas' => [1], 'puntaje' => 3]);
        $sinSugerencia = $escenario['curso']->bancoPreguntasCurso()->create(['tema' => 'Álgebra', 'pregunta' => '¿1 + 1?', 'tipo' => 'opcion_unica', 'opciones' => ['2', '3'], 'respuestas_correctas' => [0]]);

        $componente = $this->nuevoQuiz($escenario)
            ->set('preguntas', [['enunciado' => 'Propia', 'opciones' => ['A', 'B'], 'respuesta_correcta' => 0, 'puntaje' => 1]])
            // Un puntaje puesto a mano: desde aquí ya no se reparte solo.
            ->set('preguntas.0.puntaje', 4)
            ->call('abrirBanco')
            // Preguntas anteriores a las unidades: se eligen en "Sin unidad".
            ->set('unidadBanco', 'sin')
            ->set('temaBanco', 'Álgebra')
            ->set('seleccionBanco', [$sugerida->id, $sinSugerencia->id])
            ->call('agregarDesdeBanco');

        $this->assertEquals([4, 3, 1], array_column($componente->get('preguntas'), 'puntaje'));
    }

    public function test_con_intentos_no_se_puede_repartir(): void
    {
        $escenario = $this->escenario();
        $escenario['quiz']->resultados()->create(['estudiante_id' => $escenario['estudiante']->id, 'intento' => 1, 'respuestas' => [0], 'puntaje' => 10]);

        Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->assertDontSee('Repartir en partes iguales')
            ->assertSee('no se puede repartir el puntaje automáticamente')
            ->call('repartirEnPartesIguales')
            ->assertForbidden();
    }

    public function test_el_banco_presenta_el_puntaje_como_sugerido(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['profesor'])->test(BancoPreguntas::class, ['curso' => $escenario['curso']])
            ->call('nueva')
            ->assertSee('Puntaje sugerido (opcional)')
            ->assertSee('Vacío = 1. Al agregarla a un quiz podrás cambiarlo.');
    }

    /**
     * @param  array<string, mixed>  $escenario
     */
    private function nuevoQuiz(array $escenario): Testable
    {
        return Livewire::actingAs($escenario['profesor'])->test(Formulario::class, ['curso' => $escenario['curso']]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function preguntas(int $cantidad): array
    {
        return array_map(fn (int $i) => ['enunciado' => "Pregunta {$i}", 'opciones' => ['A', 'B'], 'respuesta_correcta' => 0, 'puntaje' => 1], range(1, $cantidad));
    }
}
