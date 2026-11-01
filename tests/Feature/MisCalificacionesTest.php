<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Estudiante\MisCalificaciones;
use App\Models\Entrega;
use App\Models\NotaRubro;
use App\Models\ResultadoQuiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Extensión fuera del ERS: resumen de notas de todos los cursos matriculados
 * del estudiante (equivalente a "Mis calificaciones" de Blackboard).
 */
class MisCalificacionesTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_el_estudiante_ve_la_nota_final_de_cada_curso_matriculado(): void
    {
        $escenario = $this->escenario();
        $rubros = $this->esquemaValido($escenario['curso'])->rubros;

        NotaRubro::create(['rubro_id' => $rubros[0]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 15]);
        NotaRubro::create(['rubro_id' => $rubros[1]->id, 'estudiante_id' => $escenario['estudiante']->id, 'nota' => 16]);

        // 15 × 0,4 + 16 × 0,6 = 6 + 9,6 = 15,6
        Livewire::actingAs($escenario['estudiante'])->test(MisCalificaciones::class)
            ->assertSee($escenario['curso']->nombre)
            ->assertSee('15.6')
            ->assertSee('Aprobado');
    }

    public function test_el_estudiante_ve_el_detalle_de_tareas_y_quizzes_calificados(): void
    {
        $escenario = $this->escenario();

        Entrega::factory()->create([
            'tarea_id' => $escenario['tarea']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'estado' => 'calificado',
            'nota' => 17,
        ]);
        ResultadoQuiz::factory()->create([
            'quiz_id' => $escenario['quiz']->id,
            'estudiante_id' => $escenario['estudiante']->id,
            'puntaje' => 14,
        ]);

        Livewire::actingAs($escenario['estudiante'])->test(MisCalificaciones::class)
            ->assertSee($escenario['tarea']->titulo)
            ->assertSee('17.0')
            ->assertSee($escenario['quiz']->tema)
            ->assertSee('14.0');
    }

    public function test_un_curso_sin_notas_se_muestra_como_sin_notas(): void
    {
        $escenario = $this->escenario();
        $this->esquemaValido($escenario['curso']);

        Livewire::actingAs($escenario['estudiante'])->test(MisCalificaciones::class)
            ->assertSee($escenario['curso']->nombre)
            ->assertSee('Sin notas');
    }

    public function test_un_curso_sin_esquema_se_muestra_como_sin_esquema(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])->test(MisCalificaciones::class)
            ->assertSee($escenario['curso']->nombre)
            ->assertSee('Sin esquema');
    }

    public function test_el_estudiante_sin_cursos_matriculados_ve_un_mensaje_vacio(): void
    {
        $this->crearRoles();
        $estudiante = $this->usuarioConRol(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)->test(MisCalificaciones::class)
            ->assertSee('Todavía no estás matriculado en ningún curso.');
    }

    public function test_un_profesor_no_puede_ver_mis_calificaciones(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['profesor'])->test(MisCalificaciones::class)
            ->assertForbidden();
    }

    public function test_la_ruta_requiere_el_rol_estudiante(): void
    {
        $escenario = $this->escenario();

        $this->actingAs($escenario['estudiante'])->get(route('mis-calificaciones'))->assertOk();
        $this->actingAs($escenario['profesor'])->get(route('mis-calificaciones'))->assertForbidden();
    }
}
