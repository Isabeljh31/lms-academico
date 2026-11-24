<?php

namespace Tests\Feature;

use App\Livewire\Compartido\Asistencia\Index as Asistencia;
use App\Livewire\Compartido\Grupos\Index as Grupos;
use App\Livewire\Compartido\Quizzes\Formulario as FormularioQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Los textos sr-only (position: absolute) necesitan un ancestro posicionado
 * dentro de la columna con scroll del layout: si no, se posicionan respecto
 * de <body>, estiran el documento y cortan el menú lateral. Las pantallas del
 * profesor con listas de sr-only llevan "relative" en su raíz; las vistas
 * compartidas con Supervisor solo lo agregan para el profesor.
 */
class ContencionTextosOcultosProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const RAIZ_RELATIVA = '/^\s*<div[^>]*class="[^"]*\brelative\b/';

    public function test_el_formulario_del_quiz_contiene_sus_textos_ocultos(): void
    {
        $escenario = $this->escenario();

        $html = Livewire::actingAs($escenario['profesor'])
            ->test(FormularioQuiz::class, ['curso' => $escenario['curso'], 'quiz' => $escenario['quiz']])
            ->html();

        $this->assertMatchesRegularExpression(self::RAIZ_RELATIVA, $html);
    }

    public function test_asistencia_y_grupos_lo_agregan_solo_para_el_profesor(): void
    {
        $escenario = $this->escenario();

        foreach ([Asistencia::class, Grupos::class] as $componente) {
            $this->assertMatchesRegularExpression(self::RAIZ_RELATIVA,
                Livewire::actingAs($escenario['profesor'])->test($componente, ['curso' => $escenario['curso']])->html());
            $this->assertDoesNotMatchRegularExpression(self::RAIZ_RELATIVA,
                Livewire::actingAs($escenario['supervisor'])->test($componente, ['curso' => $escenario['curso']])->html());
        }
    }
}
