<?php

namespace Tests\Feature;

use App\Livewire\EventoCalendario\Index as Calendario;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RF-78/RF-79: cada rol ve un calendario orientado a lo que necesita.
 */
class CalendarioPorRolTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_cada_actividad_lleva_el_color_y_el_id_de_su_curso(): void
    {
        $escenario = $this->escenario();
        $calendario = Livewire::actingAs($escenario['estudiante'])->test(Calendario::class)->instance();

        $tarea = collect($calendario->eventos)->firstWhere('id', 'tarea-'.$escenario['tarea']->id);

        $this->assertSame($escenario['curso']->id, $tarea['extendedProps']['cursoId']);
        $this->assertSame($calendario->coloresCursos[$escenario['curso']->id], $tarea['backgroundColor']);
    }

    public function test_el_estudiante_no_ve_en_el_calendario_los_cursos_archivados(): void
    {
        $escenario = $this->escenario();
        $archivado = Curso::factory()->archivado()->create(['profesor_id' => $escenario['profesor']->id, 'nombre' => 'Curso del periodo anterior']);
        $this->matricular($escenario['estudiante'], $archivado);

        Livewire::actingAs($escenario['estudiante'])->test(Calendario::class)
            ->assertSee('Curso de prueba')
            ->assertDontSee('Curso del periodo anterior');
    }

    public function test_el_profesor_ve_las_entregas_por_calificar_agrupadas_por_semana(): void
    {
        $escenario = $this->escenario();
        Entrega::create(['tarea_id' => $escenario['tarea']->id, 'estudiante_id' => $escenario['estudiante']->id, 'estado' => 'entregado', 'respuesta_texto' => 'Listo']);

        $calendario = Livewire::actingAs($escenario['profesor'])->test(Calendario::class)
            ->assertSee('Por calificar, por semana de entrega')
            ->instance();

        $this->assertSame(1, $calendario->agenda['porCalificar']->sum('total'));
        $tarea = collect($calendario->eventos)->firstWhere('id', 'tarea-'.$escenario['tarea']->id);
        $this->assertStringContainsString('1 por calificar', $tarea['title']);
    }

    public function test_el_supervisor_ve_el_panorama_institucional_sin_tareas_sueltas(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['fecha_inicio' => now()->startOfMonth(), 'fecha_fin' => now()->addMonths(3)]);
        $this->esquemaValido($escenario['curso']);
        Comunicado::create([
            'user_id' => $escenario['supervisor']->id, 'titulo' => 'Cierre de notas', 'contenido' => 'Recordatorio',
            'prioridad' => 'normal', 'destinatario' => 'todos', 'activo' => true, 'publicar_en' => now()->addDay(),
        ]);

        $calendario = Livewire::actingAs($escenario['supervisor'])->test(Calendario::class)
            ->assertSee('Cursos en marcha')
            ->assertSee('Cierre de notas')
            ->instance();

        $tipos = collect($calendario->eventos)->pluck('extendedProps.tipo')->unique()->sort()->values()->all();

        $this->assertSame(['comunicado', 'evaluacion', 'hito'], $tipos);
    }

    public function test_el_calendario_abre_en_la_fecha_y_el_evento_de_la_url(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['estudiante'])
            ->get(route('evento-calendario.index', ['fecha' => '2026-10-05', 'evento' => 'tarea-'.$escenario['tarea']->id, 'desde' => 'propuestas']))
            ->assertOk()
            ->assertSee('Mostrando el')
            ->assertSee('Volver a propuestas');

        Livewire::withQueryParams(['fecha' => '2026-10-05', 'evento' => 'tarea-'.$escenario['tarea']->id])
            ->actingAs($escenario['estudiante'])
            ->test(Calendario::class)
            ->assertSet('fechaInicial', '2026-10-05')
            ->assertSet('eventoResaltado', 'tarea-'.$escenario['tarea']->id);
    }

    public function test_el_calendario_descarta_parametros_de_url_invalidos(): void
    {
        $escenario = $this->escenario();

        Livewire::withQueryParams(['fecha' => '2026-02-31', 'evento' => '<script>', 'desde' => 'otra'])
            ->actingAs($escenario['estudiante'])
            ->test(Calendario::class)
            ->assertSet('fechaInicial', '')
            ->assertSet('eventoResaltado', '')
            ->assertSet('origen', '')
            ->assertDontSee('Mostrando el');
    }
}
