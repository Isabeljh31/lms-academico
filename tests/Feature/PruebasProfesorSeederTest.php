<?php

namespace Tests\Feature;

use App\Livewire\Docente\Cursos\ListaCursos;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Quiz;
use App\Models\RubroEvaluacion;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\CursosProfesor;
use Database\Seeders\PruebasProfesorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * El seeder de datos de prueba del Profesor respeta las reglas de la
 * aplicación (cupos, pesos al 100 %, fechas, estados, integridad y correos
 * únicos), no envía nada y es idempotente.
 */
class PruebasProfesorSeederTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected string $manifiesto;

    protected User $profesor;

    protected User $otro;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-04 10:00:00'));

        $this->manifiesto = sys_get_temp_dir().'/pruebas-profesor-manifiesto-'.uniqid().'.json';
        PruebasProfesorSeeder::$rutaManifiesto = $this->manifiesto;

        // Datos "reales" que el seeder no debe tocar.
        $this->usuarioConRol('supervisor');
        $this->profesor = $this->usuarioConRol('profesor', ['name' => PruebasProfesorSeeder::PROFESOR]);
        $this->otro = $this->usuarioConRol('profesor', ['name' => 'Rosaura Quispe Mamani']);
        $this->usuarioConRol('estudiante', ['name' => 'Lucía Quispe', 'email' => 'luciaquispe@lms.com']);
        Curso::factory()->create(['nombre' => 'Matematica 1', 'codigo' => 'MAT1-26-01', 'profesor_id' => $this->profesor->id]);
    }

    protected function tearDown(): void
    {
        File::delete($this->manifiesto);
        PruebasProfesorSeeder::$rutaManifiesto = '';
        parent::tearDown();
    }

    public function test_crea_datos_que_respetan_las_reglas_de_la_aplicacion(): void
    {
        $realesAntes = User::pluck('updated_at', 'id')->map(fn ($fecha) => (string) $fecha)->all();

        $this->seed(PruebasProfesorSeeder::class);

        $cursos = Curso::whereIn('codigo', array_column(PruebasProfesorSeeder::CURSOS, 0))->withCount('inscripcionesActivas')->get();
        $this->assertCount(12, $cursos);
        $this->assertSame(['archivado' => 1, 'borrador' => 2, 'publicado' => 9], $cursos->countBy('estado')->sortKeys()->all());
        $this->assertTrue($cursos->every(fn (Curso $c) => $c->profesor_id === $this->profesor->id));
        $this->assertEqualsCanonicalizing([5, 16], $cursos->pluck('semanas_duracion')->unique()->values()->all());
        $this->assertEqualsCanonicalizing(['presencial', 'virtual'], $cursos->pluck('modalidad')->unique()->values()->all());

        foreach ($cursos as $curso) {
            // Código: formato, prefijo de la misma materia y fecha de fin = inicio + semanas (como el formulario).
            $this->assertMatchesRegularExpression(Curso::FORMATO_CODIGO, $curso->codigo);
            $this->assertNull(Curso::prefijoPerteneceAOtraMateria($curso->codigo, $curso->nombre, $curso->id));
            $this->assertSame($curso->fecha_inicio->copy()->addWeeks($curso->semanas_duracion)->toDateString(), $curso->fecha_fin->toDateString());

            // Cupo: entre 5 y 30 matriculados y nunca por encima del cupo.
            $this->assertGreaterThanOrEqual(5, $curso->inscripciones_activas_count);
            $this->assertLessThanOrEqual(min(30, $curso->cupo_maximo), $curso->inscripciones_activas_count);

            // Esquema del Supervisor: pesos al 100 % y semanas dentro de la duración.
            $rubros = $curso->esquemaEvaluacion->rubros;
            $this->assertEqualsWithDelta(100, $rubros->sum(fn ($r) => (float) $r->peso), 0.001);
            $this->assertTrue($rubros->every(fn ($r) => $r->semana >= 1 && $r->semana <= $curso->semanas_duracion));

            // Un curso publicado o archivado tiene contenido (RF-25).
            if ($curso->estado !== 'borrador') {
                $this->assertTrue($curso->modulos()->exists());
            }

            // Tareas y quizzes dentro de las semanas del curso.
            foreach ($curso->tareas as $tarea) {
                $this->assertTrue($tarea->fecha_entrega->between($curso->rangoSemana(1)[0], $curso->rangoSemana($curso->semanas_duracion)[1]));
                $this->assertTrue($tarea->fecha_inicio->lt($tarea->fecha_entrega));
            }
        }

        // Actividades vinculadas: los pesos dentro de cada evaluación suman 100 %; hay evaluaciones aún sin actividades.
        $rubros = RubroEvaluacion::with(['tareas', 'quizzes'])->whereHas('esquema', fn ($q) => $q->whereIn('curso_id', $cursos->modelKeys()))->get();
        foreach ($rubros->filter(fn ($r) => $r->actividadesVinculadas()->isNotEmpty()) as $rubro) {
            $this->assertSame('lista', $rubro->estadoCalculo(), $rubro->nombre);
        }
        $this->assertTrue($rubros->contains(fn ($r) => $r->actividadesVinculadas()->isEmpty()));

        // Tareas y quizzes con fechas pasadas y futuras; entregas por calificar, calificadas (0 a 20) y grupales.
        $tareas = Tarea::whereIn('curso_id', $cursos->modelKeys())->get();
        $this->assertTrue($tareas->contains(fn ($t) => $t->fecha_entrega->isPast()) && $tareas->contains(fn ($t) => $t->fecha_entrega->isFuture()));
        $this->assertTrue(Quiz::whereIn('curso_id', $cursos->modelKeys())->where('fecha_limite', '>', now())->exists());
        $entregas = Entrega::whereIn('tarea_id', $tareas->modelKeys())->get();
        $this->assertTrue($entregas->where('estado', 'calificado')->every(fn ($e) => $e->nota >= 0 && $e->nota <= 20));
        $this->assertTrue($entregas->whereNotNull('grupo_id')->isNotEmpty());
        $this->assertTrue($entregas->every(fn ($e) => $e->created_at->lte(now())));
        $this->assertTrue(DB::table('resultados_quiz')->whereIn('quiz_id', Quiz::whereIn('curso_id', $cursos->modelKeys())->select('id'))->exists());

        $resumen = app(CursosProfesor::class)->resumen(app(CursosProfesor::class)->cursos($this->profesor), Curso::all());
        $this->assertGreaterThanOrEqual(3, collect($resumen)->filter(fn ($r) => $r['pendientes'] > 0)->count());
        // Hay cursos cuya semana actual está vacía, como pide el escenario.
        $this->assertTrue(collect($resumen)->contains(fn ($r) => collect($r['para_hacer'])->contains('tipo', 'semana_actual')));

        // Estudiantes: correos únicos @lms.com sin números, rol estudiante, un mismo estudiante en varios cursos.
        $nuevos = User::whereIn('id', json_decode(File::get($this->manifiesto), true)['tablas']['users'])->get();
        $this->assertCount(60, $nuevos);
        $this->assertTrue($nuevos->every(fn (User $u) => preg_match('/^[a-z]+@lms\.com$/', $u->email) && $u->hasRole('estudiante') && $u->activo));
        $this->assertSame(User::count(), User::distinct()->count('email'));
        $this->assertTrue(Inscripcion::selectRaw('estudiante_id, count(*) c')->groupBy('estudiante_id')->get()->contains(fn ($f) => $f->c > 1));
        // El correo que ya existía no se tocó: ese estudiante se creó con el segundo apellido.
        $this->assertSame(1, User::where('email', 'luciaquispe@lms.com')->count());
        $this->assertTrue($nuevos->contains(fn (User $u) => str_starts_with($u->email, 'luciaquispe') && $u->email !== 'luciaquispe@lms.com'));

        // Integridad referencial: nada apunta a filas inexistentes.
        $this->assertSame(0, DB::table('inscripciones')->whereNotIn('estudiante_id', DB::table('users')->select('id'))->count());
        $this->assertSame(0, DB::table('entregas')->whereNotIn('tarea_id', DB::table('tareas')->select('id'))->count());
        $this->assertSame(0, DB::table('modulos')->whereNotIn('curso_id', DB::table('cursos')->select('id'))->count());

        // Los usuarios que ya existían no cambiaron; no se envió nada.
        $this->assertSame($realesAntes, User::whereIn('id', array_keys($realesAntes))->pluck('updated_at', 'id')->map(fn ($fecha) => (string) $fecha)->all());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertStringNotContainsString('password', File::get($this->manifiesto));

        // Con estos datos cargan las pantallas del Profesor, del Supervisor y de un estudiante creado.
        $this->actingAs($this->profesor)->get(route('cursos.index'))->assertOk();
        foreach ($cursos as $curso) {
            $this->actingAs($this->profesor)->get(route('cursos.show', $curso))->assertOk();
        }
        $this->actingAs($this->profesor)->get(route('dashboard'))->assertOk();
        $supervisor = User::role('supervisor')->first();
        $this->actingAs($supervisor)->get(route('dashboard'))->assertOk();
        $this->actingAs($supervisor)->get(route('cursos.show', $cursos->first()))->assertOk();
        $estudiante = $nuevos->first(fn (User $u) => $u->inscripciones()->exists());
        // Cuenta nueva: primero debe cambiar su contraseña (RF-05); aquí se simula que ya lo hizo.
        $this->actingAs($estudiante)->get(route('dashboard'))->assertRedirect();
        $estudiante->forceFill(['primer_login' => false])->save();
        $this->actingAs($estudiante)->get(route('dashboard'))->assertOk();
        $this->actingAs($estudiante)->get(route('cursos.index'))->assertOk();
    }

    public function test_pepito_no_ve_los_cursos_de_otro_titular_y_si_el_compartido(): void
    {
        $otroAntes = (string) $this->otro->fresh()->updated_at;

        $this->seed(PruebasProfesorSeeder::class);

        $deOtro = Curso::whereIn('codigo', array_column(PruebasProfesorSeeder::CURSOS_OTRO_PROFESOR, 0))->withCount('inscripcionesActivas')->get();
        $this->assertCount(2, $deOtro);
        $this->assertTrue($deOtro->every(fn (Curso $c) => $c->profesor_id === $this->otro->id && $c->inscripciones_activas_count >= 5));

        $compartido = Curso::where('codigo', PruebasProfesorSeeder::CURSO_COMPARTIDO)->first();
        $this->assertSame($this->profesor->id, $compartido->profesor_id);
        $this->assertSame([$this->otro->id], $compartido->profesoresAdicionales()->pluck('users.id')->all());

        // Pepito: sus 12 cursos (incluido el compartido), ninguno del otro titular.
        $codigosPepito = Curso::dictadosPor($this->profesor)->whereNotNull('codigo')->pluck('codigo');
        $this->assertTrue($codigosPepito->contains(PruebasProfesorSeeder::CURSO_COMPARTIDO));
        $this->assertTrue($codigosPepito->intersect(['GEO-26-01', 'FIL-26-01'])->isEmpty());
        Livewire::actingAs($this->profesor)->test(ListaCursos::class)
            ->assertDontSee('Geografía General')
            ->assertDontSee('Filosofía')
            ->assertSee('Redacción Académica');
        $this->actingAs($this->profesor)->get(route('cursos.show', $deOtro->first()))->assertForbidden();

        // El otro profesor ve sus dos cursos y el compartido como co-docente.
        $this->assertEqualsCanonicalizing(['FIL-26-01', 'GEO-26-01', 'RED-26-01'], Curso::dictadosPor($this->otro)->pluck('codigo')->all());

        // Todo queda en el manifiesto; el otro profesor no se modificó.
        $manifiesto = json_decode(File::get($this->manifiesto), true);
        $this->assertSame($this->otro->id, $manifiesto['otro_profesor_id']);
        $this->assertContains(['curso_id' => $compartido->id, 'profesor_id' => $this->otro->id], $manifiesto['tablas']['curso_profesor']);
        $this->assertEmpty(array_diff([...$deOtro->modelKeys(), $compartido->id], $manifiesto['tablas']['cursos']));
        $this->assertSame($otroAntes, (string) $this->otro->fresh()->updated_at);
    }

    public function test_es_idempotente(): void
    {
        $this->seed(PruebasProfesorSeeder::class);
        $conteos = fn () => collect(['cursos', 'users', 'inscripciones', 'tareas', 'quizzes', 'entregas', 'lecciones', 'modulos', 'rubros_evaluacion', 'grupos', 'resultados_quiz', 'bitacoras_auditoria'])
            ->mapWithKeys(fn (string $tabla) => [$tabla => DB::table($tabla)->count()])->all();
        $despuesDeUna = $conteos();
        $manifiesto = File::get($this->manifiesto);

        $this->seed(PruebasProfesorSeeder::class);

        $this->assertSame($despuesDeUna, $conteos());
        $this->assertSame(json_decode($manifiesto, true)['tablas'], json_decode(File::get($this->manifiesto), true)['tablas']);
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app['env'] = 'production';

        try {
            app(PruebasProfesorSeeder::class)->run();
            $this->fail('El seeder no debe correr en producción.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('no se ejecuta en producción', $e->getMessage());
        }

        $this->assertSame(1, Curso::count());
        $this->assertFileDoesNotExist($this->manifiesto);
    }
}
