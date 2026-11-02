<?php

namespace Tests\Feature;

use App\Livewire\Supervisor\Cursos\EsquemaEvaluacion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RF-55 y RF-56: rubros con nombre, semana y peso; los pesos admiten
 * como máximo 2 decimales y deben sumar exactamente 100 %.
 */
class EsquemaEvaluacionTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_guarda_pesos_con_dos_decimales_que_suman_100(): void
    {
        ['supervisor' => $supervisor, 'curso' => $curso] = $this->escenario();

        Livewire::actingAs($supervisor)
            ->test(EsquemaEvaluacion::class, ['curso' => $curso])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Evaluación 1', 'semana' => 4, 'peso' => '33.33'],
                ['id' => null, 'nombre' => 'Evaluación 2', 'semana' => 8, 'peso' => '33.33'],
                ['id' => null, 'nombre' => 'Evaluación 3', 'semana' => 12, 'peso' => '33.34'],
            ])
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('100.00', number_format((float) $curso->esquemaEvaluacion()->first()->rubros()->sum('peso'), 2, '.', ''));
    }

    public function test_rechaza_un_peso_con_mas_de_dos_decimales_y_no_guarda_el_esquema(): void
    {
        ['supervisor' => $supervisor, 'curso' => $curso] = $this->escenario();

        Livewire::actingAs($supervisor)
            ->test(EsquemaEvaluacion::class, ['curso' => $curso])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Evaluación 1', 'semana' => 4, 'peso' => '33.333'],
                ['id' => null, 'nombre' => 'Evaluación 2', 'semana' => 8, 'peso' => '33.333'],
                ['id' => null, 'nombre' => 'Evaluación 3', 'semana' => 12, 'peso' => '33.334'],
            ])
            ->call('guardar')
            ->assertHasErrors(['rubros.0.peso' => 'El peso admite como máximo 2 decimales (ej. 33.33).']);

        $this->assertSame(0, $curso->esquemaEvaluacion()->first()->rubros()->count());
    }

    public function test_marca_como_invalido_un_rubro_de_mas_de_100_apenas_se_escribe(): void
    {
        ['supervisor' => $supervisor, 'curso' => $curso] = $this->escenario();

        $componente = Livewire::actingAs($supervisor)
            ->test(EsquemaEvaluacion::class, ['curso' => $curso])
            ->set('rubros.0.peso', '120');

        $this->assertSame('Inválido: un rubro no puede pesar más de 100 %.', $componente->errors()->first('rubros.0.peso'));
        $componente->assertSeeHtml('aria-invalid="true"');
    }

    public function test_indica_cuanto_sobra_cuando_los_pesos_superan_100(): void
    {
        ['supervisor' => $supervisor, 'curso' => $curso] = $this->escenario();

        $componente = Livewire::actingAs($supervisor)
            ->test(EsquemaEvaluacion::class, ['curso' => $curso])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Parcial', 'semana' => 4, 'peso' => '60'],
                ['id' => null, 'nombre' => 'Final', 'semana' => 8, 'peso' => '50'],
            ])
            ->call('guardar');

        $this->assertSame(
            'Los pesos suman 110 %. Sobran 10 %: deben sumar exactamente 100 %. Ajusta los pesos para guardar el esquema.',
            $componente->errors()->first('rubros'),
        );
        $this->assertSame(0, $curso->esquemaEvaluacion()->first()->rubros()->count());
    }

    public function test_exige_la_semana_de_cada_rubro(): void
    {
        ['supervisor' => $supervisor, 'curso' => $curso] = $this->escenario();

        Livewire::actingAs($supervisor)
            ->test(EsquemaEvaluacion::class, ['curso' => $curso])
            ->set('rubros', [
                ['id' => null, 'nombre' => 'Examen final', 'semana' => '', 'peso' => '100'],
            ])
            ->call('guardar')
            ->assertHasErrors(['rubros.0.semana' => 'Indica la semana en que se realiza.']);

        $this->assertSame(0, $curso->esquemaEvaluacion()->first()->rubros()->count());
    }
}
