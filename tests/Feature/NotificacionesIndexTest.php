<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Notificaciones\Index;
use App\Models\Entrega;
use App\Notifications\TareaCalificada;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Extensión fuera del ERS: historial completo de notificaciones (compartido
 * entre los 3 roles, cada quien solo ve las suyas).
 */
class NotificacionesIndexTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_el_usuario_ve_sus_propias_notificaciones(): void
    {
        $escenario = $this->escenario();
        $entrega = Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        $escenario['estudiante']->notify(new TareaCalificada($entrega));

        Livewire::actingAs($escenario['estudiante'])->test(Index::class)
            ->assertSee('Tarea calificada')
            ->assertSee('17.00/20');
    }

    public function test_un_usuario_no_ve_las_notificaciones_de_otro(): void
    {
        $escenario = $this->escenario();
        $otroEstudiante = $this->usuarioConRol(Rol::Estudiante->value);
        $entrega = Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $otroEstudiante->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        $otroEstudiante->notify(new TareaCalificada($entrega));

        Livewire::actingAs($escenario['estudiante'])->test(Index::class)
            ->assertDontSee('Tarea calificada')
            ->assertSee('Todavía no tienes notificaciones.');
    }

    public function test_marcar_una_notificacion_como_leida(): void
    {
        $escenario = $this->escenario();
        $entrega = Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        $escenario['estudiante']->notify(new TareaCalificada($entrega));
        $notificacion = $escenario['estudiante']->notifications()->first();

        Livewire::actingAs($escenario['estudiante'])->test(Index::class)
            ->call('marcarLeida', $notificacion->id);

        $this->assertNotNull($notificacion->fresh()->read_at);
    }

    public function test_marcar_todas_las_notificaciones_como_leidas(): void
    {
        $escenario = $this->escenario();
        $entrega = Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        $escenario['estudiante']->notify(new TareaCalificada($entrega));
        $escenario['estudiante']->notify(new TareaCalificada($entrega));

        Livewire::actingAs($escenario['estudiante'])->test(Index::class)
            ->call('marcarTodasLeidas');

        $this->assertSame(0, $escenario['estudiante']->unreadNotifications()->count());
    }

    public function test_el_filtro_solo_no_leidas_oculta_las_ya_leidas(): void
    {
        $escenario = $this->escenario();
        $entrega = Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        $escenario['estudiante']->notify(new TareaCalificada($entrega));
        $escenario['estudiante']->notifications()->first()->markAsRead();

        Livewire::actingAs($escenario['estudiante'])->test(Index::class)
            ->set('soloNoLeidas', true)
            ->assertSee('No tienes notificaciones sin leer.');
    }

    public function test_el_profesor_tambien_puede_ver_su_propio_historial(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['profesor'])->get(route('notificaciones.index'))->assertOk();
    }
}
