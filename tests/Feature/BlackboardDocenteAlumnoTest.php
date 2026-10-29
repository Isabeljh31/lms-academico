<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\Cursos\Show;
use App\Livewire\Profesor\PorCalificar\Index as PorCalificarIndex;
use App\Livewire\Tareas\Detalle;
use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BlackboardDocenteAlumnoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    protected function prepararEscenario(): array
    {
        $profesor = User::factory()->create(['name' => 'Prof. Carlos Perez'])->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create(['name' => 'Ana Martinez'])->assignRole(Rol::Estudiante->value);

        $curso = Curso::factory()->create([
            'profesor_id' => $profesor->id,
            'nombre' => 'Matemática Discreta',
        ]);
        $curso->inscripciones()->create([
            'estudiante_id' => $estudiante->id,
            'fecha_inscripcion' => now(),
        ]);

        $modulo = Modulo::factory()->create(['curso_id' => $curso->id, 'titulo' => 'Unidad 1: Lógica']);
        $tarea = Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $modulo->id,
            'titulo' => 'Laboratorio 1',
            'peso_nota' => 10,
        ]);

        return [$profesor, $estudiante, $curso, $modulo, $tarea];
    }

    public function test_docente_puede_acceder_a_cola_por_calificar_y_evaluar_entrega(): void
    {
        [$profesor, $estudiante, $curso, $modulo, $tarea] = $this->prepararEscenario();

        $entrega = Entrega::create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://lms.test/entregas/lab1.pdf',
            'estado' => 'entregado',
            'comentario' => 'Adjunto solución',
        ]);

        // Docente ve la entrega en la cola centralizada
        Livewire::actingAs($profesor)
            ->test(PorCalificarIndex::class)
            ->assertSee('Por calificar')
            ->assertSee('Ana Martinez')
            ->assertSee('Laboratorio 1')
            ->call('abrirCalificar', $entrega->id)
            ->set('nota', '18.5')
            ->set('feedback', 'Excelente argumentación en la demostración.')
            ->call('guardarCalificacion')
            ->assertHasNoErrors();

        $entrega->refresh();
        $this->assertEquals('calificado', $entrega->estado);
        $this->assertEquals(18.5, (float) $entrega->nota);
        $this->assertEquals('Excelente argumentación en la demostración.', $entrega->feedback);
    }

    public function test_estudiante_no_puede_acceder_a_cola_por_calificar(): void
    {
        [, $estudiante] = $this->prepararEscenario();

        $this->actingAs($estudiante)
            ->get(route('profesor.por-calificar'))
            ->assertStatus(403);
    }

    public function test_docente_puede_ver_y_editar_libro_de_calificaciones(): void
    {
        [$profesor, $estudiante, $curso, $modulo, $tarea] = $this->prepararEscenario();

        // El docente abre el libro de calificaciones del curso
        Livewire::actingAs($profesor)
            ->test(LibroCalificaciones::class, ['curso' => $curso])
            ->assertSee('Libro de calificaciones')
            ->assertSee('Matemática Discreta')
            ->set('vistaActiva', 'actividades')
            ->assertSee('Ana Martinez')
            ->assertSee('Laboratorio 1')
            ->call('abrirEdicionNota', $estudiante->id, $tarea->id)
            ->set('notaTareaInput', '19')
            ->set('feedbackTareaInput', 'Nota asignada en libro')
            ->call('guardarNotaTarea')
            ->assertHasNoErrors();

        $entrega = Entrega::where('tarea_id', $tarea->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        $this->assertNotNull($entrega);
        $this->assertEquals(19, (float) $entrega->nota);
        $this->assertEquals('calificado', $entrega->estado);
    }

    public function test_estudiante_no_puede_acceder_a_libro_de_calificaciones(): void
    {
        [, $estudiante, $curso] = $this->prepararEscenario();

        $this->actingAs($estudiante)
            ->get(route('cursos.libro-calificaciones', $curso))
            ->assertStatus(403);
    }

    public function test_docente_puede_publicar_y_eliminar_anuncio_del_curso(): void
    {
        [$profesor, , $curso] = $this->prepararEscenario();

        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Anuncios del curso')
            ->set('nuevoAnuncioTitulo', 'Suspensión de clase por feriado')
            ->set('nuevoAnuncioTexto', 'La sesión del viernes se reprogramará para el lunes.')
            ->set('nuevoAnuncioPrioridad', 'importante')
            ->call('publicarAnuncio')
            ->assertHasNoErrors();

        $anuncio = Comunicado::where('curso_id', $curso->id)->first();
        $this->assertNotNull($anuncio);
        $this->assertEquals('Suspensión de clase por feriado', $anuncio->titulo);
        $this->assertEquals('importante', $anuncio->prioridad);
        $this->assertEquals($profesor->id, $anuncio->user_id);

        // Docente elimina el anuncio
        Livewire::actingAs($profesor)
            ->test(Show::class, ['curso' => $curso])
            ->call('eliminarAnuncio', $anuncio->id)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('comunicados', ['id' => $anuncio->id]);
    }

    public function test_estudiante_ve_anuncios_del_curso_en_su_pestana(): void
    {
        [$profesor, $estudiante, $curso] = $this->prepararEscenario();

        Comunicado::create([
            'curso_id' => $curso->id,
            'user_id' => $profesor->id,
            'titulo' => 'Bienvenida al curso 2026',
            'contenido' => 'Revisen el sílabo y las fechas de entrega.',
            'prioridad' => 'normal',
            'publicado_en' => now(),
        ]);

        Livewire::actingAs($estudiante)
            ->test(Show::class, ['curso' => $curso])
            ->assertSee('Anuncios')
            ->assertSee('Bienvenida al curso 2026')
            ->assertSee('Revisen el sílabo y las fechas de entrega.');
    }

    public function test_estudiante_ve_recibo_digital_de_entrega(): void
    {
        [, $estudiante, , , $tarea] = $this->prepararEscenario();

        $entrega = Entrega::create([
            'tarea_id' => $tarea->id,
            'estudiante_id' => $estudiante->id,
            'archivo_url' => 'https://lms.test/entregas/recibo.pdf',
            'estado' => 'entregado',
            'comentario' => 'Entrega final',
        ]);

        $codigoEsperado = 'REC-'.str_pad((string) $entrega->id, 6, '0', STR_PAD_LEFT);

        Livewire::actingAs($estudiante)
            ->test(Detalle::class, ['curso' => $tarea->curso, 'tarea' => $tarea])
            ->assertSee('Confirmación de Entrega Oficial')
            ->assertSee($codigoEsperado);
    }
}
