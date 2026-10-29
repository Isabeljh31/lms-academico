<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Quizzes\Formulario;
use App\Livewire\Compartido\Quizzes\Index;
use App\Livewire\Compartido\Quizzes\Resolver;
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

    public function test_calcularpuntaje_compara_por_valor_no_por_tipo(): void
    {
        [, $curso, $modulo] = $this->cursoConProfesor();

        // respuesta_correcta guardado como int (típico de seeders/factories),
        // respuestas del estudiante como string (típico de un <input
        // type="radio">, que siempre entrega texto). Antes del fix, la
        // comparación === estricta contaba esto como incorrecto.
        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        $puntaje = $quiz->calcularPuntaje(['1', '0']);

        $this->assertEquals(20.0, $puntaje);
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

    public function test_un_quiz_oculto_no_es_visible_para_el_estudiante(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
            'visible' => false,
        ]);

        $this->actingAs($estudiante)
            ->get(route('cursos.quizzes.show', [$curso, $quiz]))
            ->assertForbidden();

        $this->actingAs($profesor)
            ->get(route('cursos.quizzes.show', [$curso, $quiz]))
            ->assertOk();
    }

    public function test_profesor_puede_eliminar_un_quiz_sin_resultados(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        Livewire::actingAs($profesor)
            ->test(Index::class, ['curso' => $curso])
            ->call('eliminar', $quiz->id);

        $this->assertDatabaseMissing('quizzes', ['id' => $quiz->id]);
    }

    public function test_profesor_no_puede_eliminar_un_quiz_con_resultados(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);
        $quiz->resultados()->create(['estudiante_id' => $estudiante->id, 'respuestas' => [1, 0], 'puntaje' => 20]);

        Livewire::actingAs($profesor)
            ->test(Index::class, ['curso' => $curso])
            ->call('eliminar', $quiz->id)
            ->assertForbidden();

        $this->assertDatabaseHas('quizzes', ['id' => $quiz->id]);
    }

    public function test_las_estadisticas_por_pregunta_identifican_la_mas_fallada(): void
    {
        [, $curso, $modulo] = $this->cursoConProfesor();
        $estudiante1 = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $estudiante2 = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        // Pregunta 0 correcta = índice 1, pregunta 1 correcta = índice 0.
        $quiz->resultados()->create(['estudiante_id' => $estudiante1->id, 'respuestas' => [1, 0], 'puntaje' => 20]);
        // estudiante2 falla la pregunta 0 (responde 0 en vez de 1).
        $quiz->resultados()->create(['estudiante_id' => $estudiante2->id, 'respuestas' => [0, 0], 'puntaje' => 10]);

        $estadisticas = $quiz->fresh()->estadisticasPorPregunta();

        // Ordenadas por tasa de error descendente: la pregunta 0 (50% de error) va primero.
        $this->assertSame(0, $estadisticas[0]['indice']);
        $this->assertEquals(50.0, $estadisticas[0]['tasa_error']);
        $this->assertEquals(1, $estadisticas[0]['correctas']);
        $this->assertEquals(1, $estadisticas[0]['incorrectas']);

        $this->assertSame(1, $estadisticas[1]['indice']);
        $this->assertEquals(0.0, $estadisticas[1]['tasa_error']);
        $this->assertEquals(2, $estadisticas[1]['correctas']);
    }

    public function test_solo_el_profesor_dueno_puede_ver_las_estadisticas_del_quiz(): void
    {
        [$profesor, $curso, $modulo] = $this->cursoConProfesor();
        $otroProfesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $quiz = Quiz::create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'tema' => 'Quiz',
            'preguntas' => $this->preguntasDePrueba(),
        ]);

        $this->actingAs($profesor)
            ->get(route('cursos.quizzes.estadisticas', [$curso, $quiz]))
            ->assertOk();

        $this->actingAs($estudiante)
            ->get(route('cursos.quizzes.estadisticas', [$curso, $quiz]))
            ->assertForbidden();

        $this->actingAs($otroProfesor)
            ->get(route('cursos.quizzes.estadisticas', [$curso, $quiz]))
            ->assertForbidden();
    }
}
