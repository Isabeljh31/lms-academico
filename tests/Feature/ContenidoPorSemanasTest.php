<?php

namespace Tests\Feature;

use App\Livewire\Cursos\Formulario;
use App\Livewire\Cursos\Show;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Contenido del curso organizado por semanas (Semana 1..N, N definido por el
 * Supervisor) y una sección "Prácticas de campo" fuera de la numeración.
 */
class ContenidoPorSemanasTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_el_profesor_ve_todas_las_semanas_del_curso_y_las_practicas_de_campo(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['semanas_duracion' => 6]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('Contenido por semanas')
            // Sin prácticas de campo no hay bloque, solo la acción para agregarlas.
            ->assertDontSee('Fuera de la numeración semanal')
            ->assertSee('Agregar prácticas de campo')
            ->assertSee('Semana 6')
            ->assertDontSee('Semana 7')
            ->assertSee('Preparar semana')
            ->call('prepararSeccion', null)
            ->assertSet('mostrarModalModulo', true);

        $this->assertDatabaseHas('modulos', ['curso_id' => $escenario['curso']->id, 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'semana' => null]);
    }

    public function test_no_se_prepara_una_semana_fuera_de_la_duracion_del_curso(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['semanas_duracion' => 4]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('prepararSeccion', 5)
            ->assertNotFound();
    }

    public function test_el_estudiante_ve_practicas_primero_semanas_en_orden_y_el_icono_de_cada_recurso(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $escenario['modulo']->update(['semana' => 3, 'titulo' => 'La derivada']);
        $borrador = $curso->modulos()->create(['titulo' => 'Tema oculto', 'semana' => 2, 'estado_contenido' => 'borrador', 'materiales' => []]);
        $practicas = $curso->modulos()->create(['titulo' => 'Prácticas de campo', 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'materiales' => []]);
        $escenario['modulo']->lecciones()->create(['titulo' => 'Dudas de la semana', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'foro']);
        $practicas->lecciones()->create(['titulo' => 'Salida al laboratorio', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'actividad']);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $curso])
            ->assertSeeInOrder(['Prácticas de campo', 'Salida al laboratorio', 'Semana 3', 'La derivada', 'Foro de preguntas', 'Dudas de la semana'])
            ->assertSee('Actividad complementaria')
            // La tarea de la semana aparece dentro de ella.
            ->assertSee($escenario['tarea']->titulo)
            // RF-38: la semana en borrador no es visible.
            ->assertDontSee('Tema oculto')
            ->assertSeeInOrder(['Semana 2', 'Aún no disponible', 'Semana 3']);

        $this->assertSame('borrador', $borrador->estado_contenido);
    }

    public function test_mover_el_contenido_a_otra_semana_conserva_sus_tareas_y_no_pisa_una_semana_ocupada(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $curso->update(['semanas_duracion' => 16]);
        $modulo = $escenario['modulo'];
        $curso->modulos()->create(['titulo' => 'Otro tema', 'semana' => 5, 'materiales' => []]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $curso])
            ->call('abrirEditarModulo', $modulo->id)
            ->set('moduloSemana', '5')
            ->call('guardarModulo')
            ->assertHasErrors('moduloSemana')
            ->set('moduloSemana', '9')
            ->call('guardarModulo')
            ->assertHasNoErrors();

        $this->assertSame(9, $modulo->fresh()->semana);
        $this->assertSame($modulo->id, $escenario['tarea']->fresh()->modulo_id);
    }

    public function test_el_supervisor_define_la_duracion_y_no_puede_dejarla_por_debajo_del_contenido(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $escenario['modulo']->update(['semana' => 12]);

        Livewire::actingAs($escenario['supervisor'])->test(Formulario::class, ['curso' => $curso])
            ->set('semanas_duracion', '')
            ->call('guardar')
            ->assertHasErrors(['semanas_duracion' => 'required'])
            ->set('semanas_duracion', '10')
            ->call('guardar')
            ->assertHasErrors(['semanas_duracion' => 'min'])
            ->set('semanas_duracion', '14')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame(14, $curso->fresh()->semanas_duracion);
        $this->assertSame(14, $curso->fresh()->totalSemanas());
    }

    public function test_la_migracion_ubica_cada_unidad_en_su_tramo_del_ciclo_sin_perder_contenido(): void
    {
        $escenario = $this->escenario();
        $conDuracion = $escenario['curso'];
        $conDuracion->update(['semanas_duracion' => 16]);
        $sinDuracion = $conDuracion->replicate(['codigo'])->fill(['semanas_duracion' => null]);
        $sinDuracion->save();
        Tarea::query()->delete();
        Modulo::query()->delete();

        $migracion = require database_path('migrations/2026_09_28_001808_add_semanas_y_tipo_recurso_a_contenido.php');
        $migracion->down();

        // Esquema anterior: unidades con orden, sin semana.
        foreach ([$conDuracion->id => 4, $sinDuracion->id => 3] as $cursoId => $unidades) {
            foreach (range(1, $unidades) as $orden) {
                $moduloId = DB::table('modulos')->insertGetId(['curso_id' => $cursoId, 'titulo' => "Unidad {$orden}: Tema {$orden}", 'orden' => $orden, 'materiales' => '[]', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('lecciones')->insert(['modulo_id' => $moduloId, 'titulo' => "Lección {$orden}", 'video_url' => $orden === 1 ? 'https://example.test/video' : null, 'estado' => 'publicado', 'orden' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        $migracion->up();

        // 4 unidades en 16 semanas: 1, 5, 9, 13. Sin duración: 1 a 1.
        $this->assertSame([1, 5, 9, 13], Modulo::where('curso_id', $conDuracion->id)->orderBy('orden')->pluck('semana')->all());
        $this->assertSame([1, 2, 3], Modulo::where('curso_id', $sinDuracion->id)->orderBy('orden')->pluck('semana')->all());
        $this->assertSame(7, Leccion::count());
        $this->assertSame('Tema 3', Modulo::where('curso_id', $conDuracion->id)->where('semana', 9)->first()->tema());
        $this->assertSame(['lectura' => 5, 'video' => 2], Leccion::query()->selectRaw('tipo_recurso, count(*) as total')->groupBy('tipo_recurso')->pluck('total', 'tipo_recurso')->map(fn ($n) => (int) $n)->all());
    }

    public function test_las_practicas_de_campo_son_un_bloque_propio_que_solo_aparece_con_contenido_visible(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $practicas = $curso->modulos()->create(['titulo' => 'Prácticas de campo', 'tipo_seccion' => Modulo::TIPO_PRACTICAS, 'materiales' => []]);
        $leccion = $practicas->lecciones()->create(['titulo' => 'Visita a la planta', 'estado' => 'borrador', 'orden' => 1, 'tipo_recurso' => 'actividad']);

        // Solo contenido en borrador: el estudiante no ve el bloque (RF-38); el profesor sí, con su aviso.
        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $curso])
            ->assertDontSee('Fuera de la numeración semanal')
            ->assertDontSee('Visita a la planta');
        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $curso])
            ->assertSeeInOrder(['Fuera de la numeración semanal', 'Prácticas de campo', 'Contenido por semanas', 'Semana 1'])
            ->assertSee('los estudiantes no ven este bloque')
            ->assertDontSee('Agregar prácticas de campo');

        $leccion->update(['estado' => 'publicado']);

        // Publicado: bloque propio, antes de las semanas, sin número de semana ni rango de fechas.
        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $curso])
            ->assertSeeInOrder(['Fuera de la numeración semanal', 'Prácticas de campo', 'Actividad complementaria', 'Visita a la planta', 'Semanas del curso', 'Semana 1']);
        Livewire::actingAs($escenario['supervisor'])->test(Show::class, ['curso' => $curso])
            ->assertSeeInOrder(['Prácticas de campo', 'Contenido por semanas'])
            ->assertDontSee('Quitar');
    }
}
