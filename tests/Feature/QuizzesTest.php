<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Quizzes\Formulario;
use App\Livewire\Quizzes\Resolver;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class QuizzesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    protected function cursoConProfesor(): array
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);

        return [$profesor, $curso, $modulo];
    }

    protected function preguntasDePrueba(): array
    {
        return [
            [
                'enunciado' => '¿Cuánto es 2+2?',
                'opciones' => ['3', '4'],
                'respuesta_correcta' => 1,
            ],
            [
                'enunciado' => '¿Capital de Perú?',
                'opciones' => ['Lima', 'Bogotá'],
                'respuesta_correcta' => 0,
            ],
        ];
    }

    public function test_profesor_puede_crear_un_quiz_con_preguntas(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();

        Livewire::actingAs($profesor)
            ->test(Formulario::class, ['curso' => $curso])
            ->set('tema', 'Quiz de prueba')
            ->set('modulo_id', $modulo->id)
            ->set('preguntas', $this->preguntasDePrueba())
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('quizzes', ['curso_id' => $curso->id, 'tema' => 'Quiz de prueba']);
    }

    public function test_estudiante_no_puede_crear_quizzes(): void
    {
        [, $curso] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($estudiante)
            ->get(route('cursos.quizzes.crear', $curso))
            ->assertForbidden();
    }

    public function test_estudiante_inscrito_puede_resolver_y_se_califica_automaticamente(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        // Respuesta correcta a la primera (índice 1), incorrecta a la segunda (elige 1, correcta es 0).
        Livewire::actingAs($estudiante)
            ->test(Resolver::class, ['quiz' => $quiz])
            ->set('respuestas', [1, 1])
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('resultados_quiz', [
            'quiz_id' => $quiz->id,
            'estudiante_id' => $estudiante->id,
            'puntaje' => 10.0,
        ]);
    }

    public function test_estudiante_no_puede_resolver_el_mismo_quiz_dos_veces(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        $quiz->resultados()->create([
            'estudiante_id' => $estudiante->id,
            'respuestas' => [1, 0],
            'puntaje' => 20,
        ]);

        Livewire::actingAs($estudiante)
            ->test(Resolver::class, ['quiz' => $quiz])
            ->set('respuestas', [1, 0])
            ->call('enviar')
            ->assertForbidden();

        $this->assertEquals(1, $quiz->resultados()->count());
    }
}
