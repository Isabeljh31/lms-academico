<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * app:limpiar-cursos-antiguos: simula por defecto, borra solo los cursos
 * archivados o ya terminados (con sus datos), respalda y se puede restaurar.
 */
class LimpiarCursosAntiguosTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected Curso $archivado;

    protected Curso $terminado;

    protected Curso $actual;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-13 15:00:00'));
        // Respaldos en una carpeta temporal propia de esta prueba (no en storage/ del proyecto).
        $almacen = sys_get_temp_dir().'/limpiar-cursos-'.uniqid();
        File::ensureDirectoryExists($almacen.'/app');
        $this->app->useStoragePath($almacen);

        $profesor = $this->usuarioConRol('profesor');
        $this->archivado = $this->cursoConDatos($profesor, ['estado' => 'archivado', 'fecha_fin' => '2026-12-31']);
        $this->terminado = $this->cursoConDatos($profesor, ['estado' => 'publicado', 'fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-10-19']);
        $this->actual = $this->cursoConDatos($profesor, ['estado' => 'borrador', 'fecha_inicio' => '2027-01-04', 'fecha_fin' => '2027-02-08']);
    }

    protected function cursoConDatos(User $profesor, array $atributos): Curso
    {
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, ...$atributos]);
        $modulo = Modulo::create(['curso_id' => $curso->id, 'semana' => 1, 'titulo' => 'Semana 1', 'materiales' => []]);
        $tarea = Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id]);
        $estudiante = $this->usuarioConRol('estudiante');
        $this->matricular($estudiante, $curso);
        Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'estado' => 'entregado']);

        return $curso;
    }

    public function test_por_defecto_simula_sin_borrar_y_deja_un_respaldo(): void
    {
        $this->artisan('app:limpiar-cursos-antiguos')
            ->expectsOutputToContain('Prueba de --restore (dentro de la simulación): OK')
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame(3, Curso::count());
        $this->assertCount(1, File::directories(storage_path('app/backups-demo')));
    }

    public function test_aplicar_borra_solo_los_antiguos_con_sus_datos_y_restore_los_reconstruye(): void
    {
        $usuarios = User::count();
        $filasDelActual = fn () => [Modulo::where('curso_id', $this->actual->id)->count(), Tarea::where('curso_id', $this->actual->id)->count(), DB::table('inscripciones')->where('curso_id', $this->actual->id)->count()];
        $antes = $filasDelActual();

        $this->artisan('app:limpiar-cursos-antiguos', ['--apply' => true])->assertSuccessful();

        $this->assertSame([$this->actual->id], Curso::pluck('id')->all());
        $this->assertSame(1, Entrega::count());
        $this->assertSame($antes, $filasDelActual());
        $this->assertSame($usuarios, User::count());

        $carpeta = collect(File::directories(storage_path('app/backups-demo')))->first();
        $this->artisan('app:limpiar-cursos-antiguos', ['--restore' => $carpeta, '--apply' => true])->expectsOutputToContain('RESTAURADO.')->assertSuccessful();

        $this->assertEqualsCanonicalizing([$this->archivado->id, $this->terminado->id, $this->actual->id], Curso::pluck('id')->all());
        $this->assertSame(3, Entrega::count());
    }

    public function test_antes_de_y_excluir_curso_cambian_lo_que_se_borra(): void
    {
        // Con "hoy" = 01/10 el curso que termina el 19/10 aún es actual; el archivado se excluye a mano.
        $this->artisan('app:limpiar-cursos-antiguos', ['--apply' => true, '--antes-de' => '2026-10-01', '--excluir-curso' => [(string) $this->archivado->id]])
            ->expectsOutputToContain('No hay cursos antiguos que borrar.')
            ->assertSuccessful();

        $this->assertSame(3, Curso::count());
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:limpiar-cursos-antiguos', ['--apply' => true])->expectsOutputToContain('APP_ENV=production')->assertFailed();

        $this->assertSame(3, Curso::count());
    }
}
