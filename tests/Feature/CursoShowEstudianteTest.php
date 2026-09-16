<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Cursos\Show;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Rediseño visual del rol estudiante en Cursos/Show (pestañas + progreso
 * por unidad). Verifica solo la vista: los métodos de gestión (agregar
 * módulo, subir material, etc.) ya están cubiertos en CursosTest.
 */
class CursoShowEstudianteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_estudiante_ve_las_pestañas_en_vez_del_listado_plano_de_unidades(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        Modulo::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Unidad de prueba']);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Contenido')
            ->assertSee('Materiales')
            ->assertSee('Unidad de prueba')
            ->assertDontSee('Unidades / Módulos');
    }

    public function test_profesor_dueño_sigue_viendo_el_listado_de_gestion_sin_pestañas(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        Modulo::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Unidad del profesor']);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Unidades / Módulos')
            ->assertSee('+ Unidad')
            ->assertDontSee('Contenido');
    }

    public function test_administrador_sigue_viendo_el_listado_de_gestion_sin_pestañas(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $admin = User::factory()->create()->assignRole(Rol::Administrador->value);

        Livewire::actingAs($admin)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Unidades / Módulos')
            ->assertDontSee('Contenido');
    }

    public function test_la_unidad_muestra_su_progreso_de_tareas_para_el_estudiante(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $tareaCompleta = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $tareaCompleta->entregas()->create([
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://example.test/a.pdf',
            'estado' => 'entregado',
        ]);
        Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('1/2 tareas');
    }
}
