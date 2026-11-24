<?php

namespace Tests\Feature;

use App\Livewire\Compartido\Cursos\Show;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Quiz;
use App\Models\SesionClase;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Detalle del curso del Profesor (/cursos/{id}, maqueta "CursoDetalle"):
 * cabecera, indicadores, semanas 1..N con su estado, semana abierta con sus
 * secciones y evaluaciones, y columna derecha. Supervisor y Estudiante
 * siguen con la vista compartida.
 */
class DetalleCursoProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected User $profesor;

    protected Curso $curso;

    /** @var array<string, mixed> */
    protected array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        // Miércoles 4 de noviembre de 2026: el curso que empezó el 19/10 está en su semana 3 de 5.
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));

        $this->profesor = $this->usuarioConRol('profesor');
        $this->curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Química Orgánica', 'codigo' => 'QUI-210', 'estado' => 'publicado',
            'fecha_inicio' => '2026-10-19', 'fecha_fin' => '2026-11-23', 'semanas_duracion' => 5, 'cupo_maximo' => 30, 'modalidad' => 'presencial',
            'horario_bloques' => [['dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'A-201'], ['dia' => 3, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'A-201']],
            'descripcion' => 'Curso de química para los primeros ciclos.']);
        $estudiantes = collect(range(1, 3))->map(fn () => $this->usuarioConRol('estudiante'));
        $estudiantes->each(fn (User $estudiante) => $this->matricular($estudiante, $this->curso));

        $semana = fn (int $numero, array $atributos = []) => Modulo::create(['curso_id' => $this->curso->id, 'semana' => $numero, 'titulo' => "Semana {$numero}", 'materiales' => [], ...$atributos]);
        $tarea = fn (Modulo $modulo, string $titulo, string $cierre) => Tarea::factory()->create(['curso_id' => $this->curso->id, 'modulo_id' => $modulo->id, 'titulo' => $titulo,
            'fecha_inicio' => $this->curso->rangoSemana($modulo->semana)[0], 'fecha_entrega' => $cierre]);
        $entrega = function (Tarea $tarea, User $estudiante, string $estado, Carbon $fecha) {
            $entrega = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'estado' => $estado, 'nota' => $estado === 'calificado' ? 16 : null]);
            $entrega->forceFill(['created_at' => $fecha, 'updated_at' => $fecha])->saveQuietly();
        };

        // Semana 1: mensaje de inicio y un recurso; su tarea ya está calificada.
        $s1 = $semana(1);
        $s1->lecciones()->create(['titulo' => 'Bienvenida', 'descripcion' => 'Bienvenidos al curso: revisen el sílabo antes de la primera clase.', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'mensaje_inicio']);
        $s1->lecciones()->create(['titulo' => 'Diapositivas de la clase', 'estado' => 'publicado', 'orden' => 2, 'tipo_recurso' => 'recursos', 'material_url' => 'cursos/lecciones/clase1.pdf', 'material_nombre' => 'clase1.pdf']);
        $this->ids['calificada'] = $tarea($s1, 'Informe de laboratorio 1', '2026-10-23 23:59');
        $entrega($this->ids['calificada'], $estudiantes[0], 'calificado', Carbon::parse('2026-10-22 10:00'));

        // Semana 2: una entrega espera hace 9 días (urgencia alta).
        $this->ids['antigua'] = $tarea($semana(2), 'Informe de laboratorio 2', '2026-10-30 23:59');
        $entrega($this->ids['antigua'], $estudiantes[1], 'entregado', Carbon::parse('2026-10-26 08:00'));

        // Semana 3 (actual): una entrega de ayer y un quiz con un resultado.
        $s3 = $semana(3);
        $this->ids['actual'] = $tarea($s3, 'Informe de laboratorio 3', '2026-11-04 23:59');
        $entrega($this->ids['actual'], $estudiantes[2], 'entregado', Carbon::parse('2026-11-03 09:00'));
        $this->ids['quiz'] = Quiz::factory()->create(['curso_id' => $this->curso->id, 'modulo_id' => $s3->id, 'tema' => 'Quiz de nomenclatura', 'fecha_limite' => '2026-11-08 23:59']);
        DB::table('resultados_quiz')->insert(['quiz_id' => $this->ids['quiz']->id, 'estudiante_id' => $estudiantes[0]->id, 'intento' => 1, 'respuestas' => '[]', 'puntaje' => 15, 'created_at' => now(), 'updated_at' => now()]);

        // Semana 4: programada, con una tarea por entregar. Semana 5: sin contenido.
        $tarea($semana(4, ['estado_contenido' => 'programado', 'publicar_en' => '2026-11-09 07:00']), 'Informe de laboratorio 4', '2026-11-13 23:59');

        // Clases: una dictada esta semana con asistencia y la próxima.
        $dictada = SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Clase 5', 'numero_sesion' => 5, 'fecha' => '2026-11-02 08:00', 'duracion_minutos' => 120]);
        DB::table('asistencias')->insert($estudiantes->map(fn (User $estudiante, int $i) => ['sesion_id' => $dictada->id, 'estudiante_id' => $estudiante->id, 'estado' => $i < 2 ? 'presente' : 'ausente', 'registrado_por' => $this->profesor->id, 'created_at' => now(), 'updated_at' => now()])->all());
        SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Clase 6', 'numero_sesion' => 6, 'fecha' => '2026-11-04 08:00', 'duracion_minutos' => 120]);
        SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Clase 7', 'numero_sesion' => 7, 'fecha' => '2026-11-09 08:00', 'duracion_minutos' => 120]);
    }

    protected function pagina(?User $usuario = null): string
    {
        return $this->actingAs($usuario ?? $this->profesor)->get(route('cursos.show', $this->curso))->assertOk()->getContent();
    }

    /** HTML de una semana de la lista (desde su fila hasta la siguiente). */
    protected function semana(string $html, int $numero): string
    {
        $resto = Str::after($html, 'data-semana="'.$numero.'"');

        return str_contains($resto, 'data-semana="'.($numero + 1).'"') ? Str::before($resto, 'data-semana="'.($numero + 1).'"') : Str::before($resto, 'Próxima clase');
    }

    public function test_la_cabecera_y_los_indicadores_muestran_los_datos_reales(): void
    {
        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))
            ->assertOk()
            ->assertSee('Volver a Cursos')
            ->assertSee('QUI-210')
            ->assertSee('Calificar 2 entregas')
            ->assertSee(route('docente.por-calificar', ['curso' => $this->curso->id]), false)
            ->assertSee('La más antigua espera hace 9 días')
            ->assertSee('Lun y Mié · 08:00 – 10:00')
            ->assertSee('Aula A-201')
            ->assertSee('Prof. '.$this->profesor->name)
            ->assertSee('Curso de química para los primeros ciclos.')
            ->assertSeeInOrder(['Avance del curso', 'Semana 3', 'de 5 · 60 %', 'Matriculados', '3', 'de 30 cupos', 'Por calificar', '2', '9 días', 'la más antigua espera', 'Próximo cierre', 'Mié 4 nov', 'hoy'])
            ->assertSeeInOrder(['Contenido', 'Actividades', 'Matrícula', 'Grupos', 'Debates', 'Asistencia', 'Libro de calificaciones', 'Mensajería']);
    }

    public function test_las_semanas_van_de_1_a_n_con_su_estado_y_la_actual_abierta(): void
    {
        $html = $this->pagina();

        $this->assertMatchesRegularExpression('/data-semana="1".*data-semana="2".*data-semana="3".*data-semana="4".*data-semana="5"/s', $html);
        Livewire::actingAs($this->profesor)->test(Show::class, ['curso' => $this->curso])->assertSet('semanasAbiertas', [3]);
        $this->assertDoesNotMatchRegularExpression('/id="semana-3-contenido"[^>]*display: none/', $html);
        $this->assertMatchesRegularExpression('/id="semana-2-contenido"[^>]*display: none/', $html);

        // Chips solo con conteo > 0, y la pastilla de pendientes con el color de su urgencia.
        $semana1 = $this->semana($html, 1);
        $this->assertStringContainsString('2 elementos', $semana1);
        $this->assertStringContainsString('1 tarea', $semana1);
        $this->assertStringNotContainsString('quiz', Str::before($semana1, 'semana-1-contenido'));
        $this->assertStringContainsString('Todo calificado', Str::before($semana1, 'semana-1-contenido'));
        $this->assertMatchesRegularExpression('/bg-\[var\(--err100\)\][^"]*" data-pendientes="1">\s*<span[^>]*><\/span>1 por calificar · hace 9 días/', $this->semana($html, 2));
        $this->assertMatchesRegularExpression('/bg-\[var\(--ok100\)\][^"]*" data-pendientes="1">\s*<span[^>]*><\/span>1 por calificar · hace 1 día/', $this->semana($html, 3));
        $this->assertStringContainsString('Esta semana', $this->semana($html, 3));
        $this->assertStringContainsString('Programada · se publica lun 9 nov', $this->semana($html, 4));
        $this->assertStringContainsString('Entrega 13/11', $this->semana($html, 4));
        $this->assertStringContainsString('Sin contenido', $this->semana($html, 5));
        $this->assertStringContainsString('Agregar contenido', $this->semana($html, 5));
        $this->assertStringNotContainsString('Agregar contenido', $this->semana($html, 1));

        // Filtros con sus contadores calculados en el servidor.
        foreach (['todas' => 5, 'por_calificar' => 2, 'sin_contenido' => 1, 'programadas' => 1] as $filtro => $total) {
            $this->assertMatchesRegularExpression('/data-filtro="'.$filtro.'"[^>]*>[^<]*<span[^>]*>'.$total.'<\/span>/', $html, $filtro);
        }
    }

    public function test_la_semana_abierta_muestra_clases_secciones_con_vista_previa_y_evaluaciones(): void
    {
        $html = $this->pagina();
        $semana1 = $this->semana($html, 1);
        $semana3 = $this->semana($html, 3);

        // Las 4 secciones del sistema con su contador, la vista previa y el estado vacío.
        foreach (['mensaje_inicio' => 'no', 'recursos' => 'no', 'actividades' => 'si', 'grabacion' => 'si'] as $seccion => $vacia) {
            $this->assertStringContainsString('data-seccion="'.$seccion.'" data-vacia="'.$vacia.'"', $semana1);
        }
        $this->assertStringContainsString('“Bienvenidos al curso: revisen el sílabo antes de la primera clase.”', $semana1);
        $this->assertMatchesRegularExpression('/Diapositivas de la clase<\/span>\s*<em[^>]*>PDF<\/em>/', $semana1);
        $this->assertStringContainsString('2 de 4 secciones con contenido', $semana1);
        $this->assertStringContainsString('0 de 4 secciones con contenido', $semana3);

        // Clases de la semana con asistencia registrada.
        $this->assertStringContainsString('Lun 2 nov · 08:00', $semana3);
        $this->assertStringContainsString('2/3', $semana3);
        $this->assertStringContainsString(route('asistencia.index', $this->curso), $semana3);

        // Evaluaciones de la semana: calificar, todo calificado y resultados del quiz.
        $this->assertStringContainsString(route('docente.por-calificar', ['tarea' => $this->ids['actual']->id]), $semana3);
        $this->assertStringContainsString('Calificar 1', $semana3);
        $this->assertStringContainsString('Se califica solo', $semana3);
        $this->assertStringContainsString('1 de 3 resultados · promedio 15,0', $semana3);
        $this->assertStringContainsString(route('cursos.quizzes.estadisticas', [$this->curso, $this->ids['quiz']]), $semana3);
        $this->assertMatchesRegularExpression('/data-tarea="'.$this->ids['calificada']->id.'".*?Todo calificado/s', $semana1);
        $this->assertStringContainsString(route('cursos.actividades', $this->curso), $semana3);
    }

    public function test_la_columna_derecha_muestra_proxima_clase_evaluaciones_y_anuncios(): void
    {
        $this->curso->comunicados()->create(['user_id' => $this->profesor->id, 'titulo' => 'Traer bata', 'contenido' => 'Para el laboratorio.', 'prioridad' => 'normal', 'destinatario' => 'estudiantes', 'activo' => true]);
        $this->curso->comunicados()->create(['user_id' => $this->profesor->id, 'titulo' => 'Cambio de aula', 'contenido' => 'Ya pasó.', 'prioridad' => 'normal', 'destinatario' => 'estudiantes', 'activo' => false]);

        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))
            ->assertSeeInOrder(['Próxima clase', 'Lun 9 nov · 08:00', 'Sesión 7 · Aula A-201 · Presencial', 'Ver sesión', 'Tomar asistencia'])
            ->assertSeeInOrder(['Anuncios del curso', 'Traer bata', 'Activo', 'Cambio de aula', 'Vencido', 'Nuevo anuncio'])
            ->assertSee('Cada anuncio llega como notificación a los estudiantes matriculados.')
            ->assertDontSeeHtml('<select');

        // Sin sesiones futuras no hay "Próxima clase".
        SesionClase::where('fecha', '>', now())->delete();
        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))->assertDontSee('id="titulo-proxima-clase"', false);
    }

    public function test_los_estados_borrador_despublicado_y_archivado(): void
    {
        $this->curso->update(['estado' => 'borrador']);
        Entrega::query()->delete();
        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))
            ->assertSee('No visible para estudiantes · pendiente de publicación por el Supervisor')
            ->assertSee('Estás al día')
            ->assertDontSee(route('docente.por-calificar', ['curso' => $this->curso->id]), false);

        $this->curso->update(['estado' => 'despublicado']);
        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))->assertSee('Despublicado')->assertSee('No visible para estudiantes');

        $this->curso->update(['estado' => 'archivado']);
        $this->actingAs($this->profesor)->get(route('cursos.show', $this->curso))
            ->assertSee('Curso archivado (finalizado)')
            ->assertDontSee('Agregar contenido')
            ->assertDontSee('Nuevo anuncio')
            ->assertDontSee('aria-label="Más acciones de Semana 1"', false);
    }

    public function test_supervisor_y_estudiante_conservan_la_vista_compartida(): void
    {
        Livewire::actingAs($this->usuarioConRol('supervisor'))->test(Show::class, ['curso' => $this->curso])->assertViewIs('livewire.compartido.cursos.show');

        $estudiante = User::role('estudiante')->first();
        Livewire::actingAs($estudiante)->test(Show::class, ['curso' => $this->curso])->assertViewIs('livewire.compartido.cursos.show')->assertDontSee('Contenido por semanas');
    }

    public function test_el_numero_de_consultas_no_crece_con_las_semanas(): void
    {
        $medir = function (Curso $curso): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($this->profesor)->get(route('cursos.show', $curso))->assertOk();
            $total = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $total;
        };

        $largo = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'estado' => 'publicado', 'fecha_inicio' => '2026-09-07', 'semanas_duracion' => 16, 'cupo_maximo' => 30]);
        $this->matricular(User::role('estudiante')->first(), $largo);
        foreach (range(1, 12) as $numero) {
            $modulo = Modulo::create(['curso_id' => $largo->id, 'semana' => $numero, 'titulo' => "Semana {$numero}", 'materiales' => []]);
            $modulo->lecciones()->create(['titulo' => 'Lectura', 'estado' => 'publicado', 'orden' => 1, 'tipo_recurso' => 'recursos']);
            Tarea::factory()->create(['curso_id' => $largo->id, 'modulo_id' => $modulo->id, 'fecha_inicio' => $largo->rangoSemana($numero)[0], 'fecha_entrega' => $largo->rangoSemana($numero)[1]]);
        }

        $medir($this->curso);
        $this->assertLessThanOrEqual($medir($this->curso) + 1, $medir($largo));
    }
}
