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
use PHPUnit\Framework\Attributes\DataProvider;
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

    /**
     * ERS RF-18 y matriz de permisos: solo el Supervisor crea cursos. Esta
     * prueba reemplaza a "profesor_puede_crear_un_curso", que contradecía el ERS.
     */
    public function test_supervisor_crea_un_curso_asignando_un_profesor(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Matemática Básica')
            ->set('descripcion', 'Curso introductorio')
            ->set('profesor_id', $profesor->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', [
            'nombre' => 'Matemática Básica',
            'profesor_id' => $profesor->id,
            'estado' => 'borrador',
        ]);
    }

    public function test_el_codigo_escrito_en_minusculas_se_guarda_en_mayusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Cálculo Diferencial')
            ->set('codigo', ' mat-201 ')
            ->set('profesor_id', $profesor->id)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('cursos', ['nombre' => 'Cálculo Diferencial', 'codigo' => 'MAT-201']);
    }

    public function test_rechaza_un_codigo_que_ya_existe_aunque_se_escriba_en_minusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-201']);

        Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Otro curso')
            ->set('codigo', 'mat-201')
            ->set('profesor_id', $profesor->id)
            ->call('guardar')
            ->assertHasErrors(['codigo' => 'Ya existe un curso con este código.']);

        $this->assertDatabaseMissing('cursos', ['nombre' => 'Otro curso']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function codigosConFormatoInvalido(): array
    {
        return [
            'con espacio' => ['MAT 201'],
            'con tilde' => ['FÍS-150'],
            'con guion bajo' => ['MAT_201'],
            'guion al final' => ['MAT-'],
            'guiones seguidos' => ['MAT--201'],
        ];
    }

    #[DataProvider('codigosConFormatoInvalido')]
    public function test_rechaza_un_codigo_con_caracteres_no_permitidos(string $codigo): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $componente = Livewire::actingAs($supervisor)
            ->test(Formulario::class)
            ->set('nombre', 'Curso con código inválido')
            ->set('codigo', $codigo)
            ->set('profesor_id', $profesor->id)
            ->call('guardar');

        $this->assertSame(
            'El código solo puede tener letras (A-Z), números y guiones, sin espacios ni tildes. Ejemplo: MAT-201.',
            $componente->errors()->first('codigo'),
        );
        $this->assertDatabaseMissing('cursos', ['nombre' => 'Curso con código inválido']);
    }

    public function test_clonar_rechaza_un_codigo_que_ya_existe_aunque_se_escriba_en_minusculas(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-201']);

        Livewire::actingAs($supervisor)
            ->test(Show::class, ['curso' => $curso])
            ->call('abrirClonar')
            ->set('clonCodigo', 'mat-201')
            ->call('clonarCurso')
            ->assertHasErrors(['clonCodigo' => 'Ya existe un curso con este código.']);

        $this->assertSame(1, Curso::count());
    }

    public function test_profesor_no_puede_crear_cursos(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);

        $this->actingAs($profesor)->get(route('cursos.crear'))->assertForbidden();
    }

    /**
     * ERS RF-25: un curso sin módulos no puede publicarse.
     */
    public function test_no_se_puede_publicar_un_curso_sin_modulos(): void
    {
        $supervisor = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $curso = Curso::factory()->borrador()->create();

        Livewire::actingAs($supervisor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('estado', 'publicado')
            ->call('guardar')
            ->assertHasErrors('estado');

        $this->assertSame('borrador', $curso->fresh()->estado);
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
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->actingAs($admin)
            ->get(route('cursos.editar', $curso))
            ->assertOk();
    }

    /**
     * ERS 2.4 y RF-26: no hay autoinscripción. Reemplaza a
     * "estudiante_puede_inscribirse_en_un_curso", que contradecía el ERS.
     */
    public function test_estudiante_no_puede_autoinscribirse_ni_ver_cursos_ajenos(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)->get(route('cursos.show', $curso))->assertForbidden();
        $this->assertDatabaseMissing('inscripciones', ['curso_id' => $curso->id, 'estudiante_id' => $estudiante->id]);
    }

    public function test_estudiante_matriculado_no_puede_invocar_la_autoinscripcion(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->call('inscribirme')
            ->assertForbidden();
    }

    public function test_profesor_prepara_una_semana_de_su_curso_con_su_tema(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'semanas_duracion' => 16]);

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->call('prepararSeccion', 3)
            ->assertSet('mostrarModalModulo', true)
            ->set('moduloTitulo', 'La derivada')
            ->call('guardarModulo')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('modulos', [
            'curso_id' => $curso->id,
            'semana' => 3,
            'tipo_seccion' => 'semana',
            'titulo' => 'La derivada',
        ]);
    }

    public function test_estudiante_no_puede_agregar_unidades(): void
    {
        $curso = Curso::factory()->create();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->call('prepararSeccion', 1)
            ->assertForbidden();

        $this->assertDatabaseMissing('modulos', ['curso_id' => $curso->id]);
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
