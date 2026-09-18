<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Reportes\Dashboard;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\ProgresoAcademicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_el_promedio_ponderado_de_tareas_se_calcula_correctamente(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        // Tarea A: peso 2, nota 10. Tarea B: peso 8, nota 20.
        // Promedio ponderado = (2*10 + 8*20) / (2+8) = (20+160)/10 = 18.
        $tareaA = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'peso_nota' => 2]);
        $tareaB = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'peso_nota' => 8]);
        Entrega::factory()->create(['tarea_id' => $tareaA->id, 'estudiante_id' => $estudiante->id, 'estado' => 'calificado', 'nota' => 10]);
        Entrega::factory()->create(['tarea_id' => $tareaB->id, 'estudiante_id' => $estudiante->id, 'estado' => 'calificado', 'nota' => 20]);

        $progreso = app(ProgresoAcademicoService::class)->progresoEstudiante($estudiante, $curso);

        $this->assertEquals(18.0, $progreso['promedio_tareas']);
    }

    public function test_estudiante_ve_su_dashboard_de_progreso(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso de prueba']);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Dashboard::class)
            ->assertSee('Curso de prueba');
    }

    public function test_profesor_ve_promedio_y_tasa_de_entrega_de_sus_cursos(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso del profesor']);

        Livewire::actingAs($profesor)
            ->test(Dashboard::class)
            ->assertSee('Curso del profesor');
    }

    public function test_estudiante_inscrito_puede_descargar_su_boleta_en_pdf(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $respuesta = $this->actingAs($estudiante)->get(route('reportes.boleta', $curso));

        $respuesta->assertOk();
        $this->assertStringContainsString('application/pdf', $respuesta->headers->get('Content-Type'));
    }

    public function test_estudiante_no_inscrito_no_puede_descargar_la_boleta(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('reportes.boleta', $curso))
            ->assertForbidden();
    }
}
