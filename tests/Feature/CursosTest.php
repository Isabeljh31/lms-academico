<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Cursos\Formulario;
use App\Livewire\Cursos\Index;
use App\Livewire\Cursos\Show;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CursosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_profesor_puede_crear_un_curso(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($profesor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática Básica')
            ->set('descripcion', 'Curso introductorio')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Matemática Básica',
            'profesor_id' => $profesor->id,
        ]);
    }

    public function test_estudiante_no_puede_crear_un_curso(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('cursos.crear'))
            ->assertForbidden();
    }

    public function test_profesor_no_puede_editar_el_curso_de_otro_profesor(): void
    {
        $curso = Curso::factory()->create();
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($otroProfesor)
            ->get(route('cursos.editar', $curso))
            ->assertForbidden();
    }

    public function test_administrador_puede_editar_cualquier_curso(): void
    {
        $curso = Curso::factory()->create();
        $admin = User::factory()->create()->assignRole(Rol::Administrador->value);

        $this->actingAs($admin)
            ->get(route('cursos.editar', $curso))
            ->assertOk();
    }

    public function test_estudiante_puede_inscribirse_en_un_curso(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->call('inscribirme');

        $this->assertDatabaseHas('inscripciones', [
            'curso_id' => $curso->id,
            'estudiante_id' => $estudiante->id,
        ]);
    }

    public function test_profesor_puede_agregar_una_unidad_a_su_curso(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->set('nuevoModuloTitulo', 'Unidad 1: Introducción')
            ->call('agregarModulo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('modulos', [
            'curso_id' => $curso->id,
            'titulo' => 'Unidad 1: Introducción',
        ]);
    }

    public function test_estudiante_no_puede_agregar_unidades(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->set('nuevoModuloTitulo', 'Intento no autorizado')
            ->call('agregarModulo')
            ->assertForbidden();

        $this->assertDatabaseMissing('modulos', ['titulo' => 'Intento no autorizado']);
    }

    public function test_catalogo_de_profesor_solo_muestra_sus_propios_cursos(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $suyo = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $ajeno = Curso::factory()->create(['profesor_id' => $otroProfesor->id]);

        Livewire::actingAs($profesor)
            ->test(Index::class)
            ->assertSee($suyo->nombre)
            ->assertDontSee($ajeno->nombre);
    }
}
