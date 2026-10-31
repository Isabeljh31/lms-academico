<?php

namespace Tests\Feature;

use App\Livewire\Compartido\Debates\Index as DebatesIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Desplegable propio (components/gestion/desplegable): la palomita de cada
 * opción depende de una expresión Alpine válida, así que solo se ve en la
 * opción elegida. Se prueba a través del formulario de Debates, que lo usa.
 */
class DesplegableTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_la_palomita_solo_depende_de_la_opcion_elegida(): void
    {
        $escenario = $this->escenario();

        $html = Livewire::actingAs($escenario['profesor'])->test(DebatesIndex::class, ['curso' => $escenario['curso']])
            ->call('abrirNuevo')
            ->html();

        // Antes, @js quedaba sin compilar dentro de <x-icono> y el x-show fallaba (palomita en todas las opciones).
        $this->assertStringNotContainsString('@js(', $html);
        $this->assertSame(2, preg_match_all('/<span class="shrink-0" x-show="String\(valor \?\? \'\'\) === \'(borrador|publicado)\'"/', $html));
    }
}
