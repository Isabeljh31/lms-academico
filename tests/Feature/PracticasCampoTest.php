<?php

namespace Tests\Feature;

use App\Livewire\Cursos\Formulario as FormularioCurso;
use App\Livewire\Cursos\Show;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Prácticas de campo: el Supervisor decide si el curso las tiene; hay una por
 * semana del curso (Práctica 01..N, N = duración). Aparecen como una carpeta
 * encima de las semanas; cada práctica es una tarea con entrega de archivos
 * que prepara el profesor (tareas.practica).
 */
class PracticasCampoTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_el_supervisor_activa_las_practicas_y_hay_una_por_semana_del_curso(): void
    {
        $e = $this->escenario();

        Livewire::actingAs($e['supervisor'])->test(FormularioCurso::class, ['curso' => $e['curso']])
            ->assertSet('tiene_practicas_campo', false)
            ->set('semanas_duracion', '5')
            ->set('tiene_practicas_campo', true)
            ->assertSee('una práctica por semana del curso (5 en total)')
            ->call('guardar')
            ->assertHasNoErrors();

        $curso = $e['curso']->fresh();
        $this->assertTrue($curso->tiene_practicas_campo);
        $this->assertSame(5, $curso->totalPracticasCampo());

        Livewire::actingAs($e['supervisor'])->test(FormularioCurso::class, ['curso' => $curso])
            ->assertSet('tiene_practicas_campo', true)
            ->set('tiene_practicas_campo', false)
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(0, $e['curso']->fresh()->totalPracticasCampo());
    }

    public function test_el_supervisor_no_quita_ni_acorta_practicas_que_ya_tienen_su_entrega(): void
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 4]);
        $this->practica($e['curso'], 3);

        Livewire::actingAs($e['supervisor'])->test(FormularioCurso::class, ['curso' => $e['curso']])
            ->set('semanas_duracion', '2')
            ->call('guardar')
            ->assertHasErrors(['semanas_duracion' => 'min'])
            ->set('semanas_duracion', '4')
            ->set('tiene_practicas_campo', false)
            ->call('guardar')
            ->assertHasErrors('tiene_practicas_campo')
            ->set('tiene_practicas_campo', true)
            ->set('semanas_duracion', '3')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(3, $e['curso']->fresh()->totalPracticasCampo());
    }

    public function test_el_profesor_ve_la_carpeta_encima_de_las_semanas_con_cada_practica(): void
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 3]);
        $preparada = $this->practica($e['curso'], 1, ['titulo' => 'Visita a la planta']);

        Livewire::actingAs($e['profesor'])->test(Show::class, ['curso' => $e['curso']])
            ->assertSeeInOrder(['Prácticas de campo', '3 prácticas · 1 con entrega preparada', 'Práctica 01', 'Visita a la planta', 'Práctica 02', 'Sin entrega', 'Práctica 03', 'Contenido por semanas', 'Semana 1'])
            ->assertSee(e(route('cursos.tareas.editar', [$e['curso'], $preparada])), false)
            ->assertSee(e(route('cursos.tareas.crear', [$e['curso'], 'practica' => 2])), false)
            ->assertDontSee(e(route('cursos.tareas.crear', [$e['curso'], 'practica' => 1])), false);
    }

    public function test_el_profesor_crea_la_entrega_de_una_practica_en_su_carpeta(): void
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 3]);

        Livewire::withQueryParams(['practica' => 2])->actingAs($e['profesor'])
            ->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->assertSet('practica', 2)
            ->assertSet('titulo', 'Práctica 02')
            ->assertSee('Prácticas de campo · Práctica 02')
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertRedirect(route('cursos.show', $e['curso']));

        $tarea = Tarea::where('practica', 2)->sole();
        $this->assertSame('Práctica 02', $tarea->titulo);
        $this->assertSame(Modulo::TIPO_PRACTICAS, $tarea->modulo->tipo_seccion);
    }

    public function test_no_se_prepara_una_practica_que_el_supervisor_no_definio_o_que_ya_tiene_entrega(): void
    {
        $e = $this->escenario();
        $crear = fn (int $numero) => $this->actingAs($e['profesor'])->get(route('cursos.tareas.crear', [$e['curso'], 'practica' => $numero]));

        $crear(1)->assertNotFound();

        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 2]);
        $this->practica($e['curso'], 1);

        $crear(3)->assertNotFound();
        $crear(1)->assertStatus(409);
        $crear(2)->assertOk();
    }

    public function test_una_tarea_de_semana_no_puede_guardarse_en_la_carpeta_de_practicas(): void
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 1]);

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->set('titulo', 'Tarea suelta')
            ->set('modulo_id', $e['curso']->moduloPracticas()->id)
            ->set('fecha_entrega', now()->addWeek()->format('Y-m-d\TH:i'))
            ->call('guardar')
            ->assertHasErrors('modulo_id');
    }

    public function test_el_estudiante_ve_las_practicas_publicadas_y_las_demas_como_no_disponibles(): void
    {
        $e = $this->escenario();
        $e['curso']->update(['tiene_practicas_campo' => true, 'semanas_duracion' => 3]);
        $this->practica($e['curso'], 1, ['titulo' => 'Informe de campo']);
        $this->practica($e['curso'], 2, ['titulo' => 'Borrador oculto', 'visible' => false]);

        Livewire::actingAs($e['estudiante'])->test(Show::class, ['curso' => $e['curso']])
            ->assertSeeInOrder(['Prácticas de campo', '3 prácticas', 'Práctica 01', 'Informe de campo', 'Práctica 02', 'Aún no disponible', 'Práctica 03', 'Aún no disponible', 'Semanas del curso'])
            ->assertDontSee('Borrador oculto');
    }

    public function test_sin_practicas_activadas_el_estudiante_no_ve_la_carpeta(): void
    {
        $e = $this->escenario();

        Livewire::actingAs($e['estudiante'])->test(Show::class, ['curso' => $e['curso']])
            ->assertDontSee('Prácticas de campo')
            ->assertDontSee('Semanas del curso');
    }

    public function test_la_migracion_quita_las_secciones_del_profesor_y_conserva_las_que_tienen_entregas(): void
    {
        $e = $this->escenario();
        $sinHistorial = $e['curso'];
        $conHistorial = Curso::factory()->create(['profesor_id' => $e['profesor']->id]);
        $migracion = require database_path('migrations/2026_11_05_175156_add_practicas_campo_a_cursos_y_tareas.php');
        $migracion->down();

        $vacia = $sinHistorial->modulos()->create(['titulo' => 'Prácticas de campo', 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'materiales' => []]);
        $leccion = $vacia->lecciones()->create(['titulo' => 'Salida', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'actividad']);
        $conEntregas = $conHistorial->modulos()->create(['titulo' => 'Prácticas de campo', 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'materiales' => []]);
        $segunda = Tarea::factory()->create(['curso_id' => $conHistorial->id, 'modulo_id' => $conEntregas->id, 'fecha_entrega' => now()->addWeeks(2)]);
        $primera = Tarea::factory()->create(['curso_id' => $conHistorial->id, 'modulo_id' => $conEntregas->id, 'fecha_entrega' => now()->addWeek()]);
        Entrega::factory()->create(['tarea_id' => $primera->id, 'estudiante_id' => $e['estudiante']->id]);

        $migracion->up();

        $this->assertModelMissing($vacia);
        $this->assertModelMissing($leccion);
        $this->assertFalse($sinHistorial->fresh()->tiene_practicas_campo);
        $this->assertModelExists($conEntregas);
        $this->assertTrue($conHistorial->fresh()->tiene_practicas_campo);
        $this->assertSame([1, 2], [$primera->fresh()->practica, $segunda->fresh()->practica]);
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function practica(Curso $curso, int $numero, array $atributos = []): Tarea
    {
        return Tarea::factory()->create([
            'curso_id' => $curso->id,
            'modulo_id' => $curso->moduloPracticas()->id,
            'practica' => $numero,
            'fecha_entrega' => now()->addWeek(),
            ...$atributos,
        ]);
    }
}
