<?php

namespace Tests\Feature;

use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Fechas de los formularios de quiz y de tarea del Profesor con el selector
 * del sistema (Flatpickr, x-gestion.fecha-hora) en lugar del calendario
 * nativo. El valor que llega al servidor no cambia (Y-m-d\TH:i, hora local).
 */
class SelectorFechaHoraProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_los_formularios_usan_el_selector_del_sistema_en_vez_del_nativo(): void
    {
        $escenario = $this->escenario();

        foreach ([
            Livewire::actingAs($escenario['profesor'])->test(FormularioQuiz::class, ['curso' => $escenario['curso']]),
            Livewire::actingAs($escenario['profesor'])->test(FormularioTarea::class, ['curso' => $escenario['curso']]),
        ] as $formulario) {
            $html = $formulario->html();
            $this->assertStringContainsString("altFormat: 'd/m/Y H:i'", $html);
            $this->assertStringContainsString('time_24hr: true', $html);
            $this->assertStringContainsString('aria-label="Quitar fecha"', $html);
            $this->assertDoesNotMatchRegularExpression('/id="(fecha_inicio|fecha_limite|fecha_entrega)"[^>]*datetime-local/', $html);
        }
    }

    public function test_la_fecha_se_guarda_en_el_mismo_formato_y_se_puede_dejar_vacia(): void
    {
        $escenario = $this->escenario();
        $quiz = $escenario['quiz'];

        Livewire::actingAs($escenario['profesor'])->test(FormularioQuiz::class, ['curso' => $escenario['curso'], 'quiz' => $quiz])
            ->set('fecha_inicio', '')
            ->set('fecha_limite', '2026-11-05T18:30')
            ->call('guardar')
            ->assertHasNoErrors();

        $quiz->refresh();
        $this->assertNull($quiz->fecha_inicio);
        $this->assertSame('2026-11-05 18:30', $quiz->fecha_limite->format('Y-m-d H:i'));

        // Al volver a editar se carga la misma fecha y hora, sin corrimiento de zona horaria.
        Livewire::actingAs($escenario['profesor'])->test(FormularioQuiz::class, ['curso' => $escenario['curso'], 'quiz' => $quiz])
            ->assertSet('fecha_limite', '2026-11-05T18:30')
            ->assertSet('fecha_inicio', '');
    }
}
