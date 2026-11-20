<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use App\Services\Reportes\CursosProfesor;
use App\Services\Reportes\PanelProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * app:reiniciar-cursos-demo: simula por defecto; con --apply borra todos los
 * cursos y siembra los de demostración en una transacción, sin tocar usuarios,
 * y --restore devuelve el estado anterior.
 */
class ReiniciarCursosDemoTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected Curso $anterior;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00'));
        // Respaldos en una carpeta temporal propia de esta prueba (no en storage/ del proyecto).
        $almacen = sys_get_temp_dir().'/reiniciar-cursos-'.uniqid();
        File::ensureDirectoryExists($almacen.'/app');
        $this->app->useStoragePath($almacen);

        $this->usuarioConRol('supervisor');
        foreach (['Pepito Reyes Diaz', 'José Cuya Camara', 'Gerardo Sarmiento'] as $nombre) {
            $this->usuarioConRol('profesor', ['name' => $nombre]);
        }
        foreach (range(1, 45) as $n) {
            $this->usuarioConRol('estudiante');
        }

        $this->anterior = Curso::factory()->create(['profesor_id' => User::where('name', 'Pepito Reyes Diaz')->value('id'), 'nombre' => 'Curso anterior']);
        $modulo = Modulo::create(['curso_id' => $this->anterior->id, 'semana' => 1, 'titulo' => 'Semana 1', 'materiales' => []]);
        Tarea::factory()->create(['curso_id' => $this->anterior->id, 'modulo_id' => $modulo->id]);
    }

    public function test_por_defecto_simula_sin_escribir(): void
    {
        $this->artisan('app:reiniciar-cursos-demo')
            ->expectsOutputToContain('Prueba de --restore (dentro de la simulación): OK')
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame([$this->anterior->id], Curso::pluck('id')->all());
    }

    public function test_aplicar_siembra_cursos_coherentes_sin_tocar_usuarios_y_restore_vuelve_atras(): void
    {
        $usuarios = DB::table('users')->orderBy('id')->get()->toJson();

        $this->artisan('app:reiniciar-cursos-demo', ['--apply' => true])->assertSuccessful();

        $this->assertFalse(Curso::whereKey($this->anterior->id)->exists());
        $this->assertSame(15, Curso::count());
        $this->assertSame($usuarios, DB::table('users')->orderBy('id')->get()->toJson());
        $this->assertEqualsCanonicalizing(['publicado', 'borrador', 'despublicado', 'archivado'], Curso::distinct()->pluck('estado')->all());
        // Ninguna entrega antes de la apertura de su tarea ni en el futuro.
        $this->assertSame(0, Entrega::query()->join('tareas', 'tareas.id', '=', 'entregas.tarea_id')
            ->where(fn ($q) => $q->where('entregas.created_at', '>', now())->orWhereColumn('entregas.created_at', '<', 'tareas.fecha_inicio'))->count());
        // El borrador sin matrícula y el curso con cupo lleno.
        $this->assertSame(0, Inscripcion::where('curso_id', Curso::where('codigo', 'ECO-26-01')->value('id'))->count());
        $this->assertTrue(Curso::where('codigo', 'QUI-26-01')->first()->cupoLleno());
        // Por calificar: la insignia del dock coincide con el hero.
        $pepito = User::where('name', 'Pepito Reyes Diaz')->first();
        $cursos = app(CursosProfesor::class)->cursos($pepito);
        $this->assertSame(app(PanelProfesor::class)->totalPorCalificar($pepito), (int) collect(app(CursosProfesor::class)->resumen($cursos, $cursos))->sum('pendientes'));

        $carpeta = collect(File::directories(storage_path('app/backups-demo')))->first();
        $this->artisan('app:reiniciar-cursos-demo', ['--restore' => $carpeta, '--apply' => true])->expectsOutputToContain('RESTAURADO.')->assertSuccessful();

        $this->assertSame([$this->anterior->id], Curso::pluck('id')->all());
        $this->assertSame(1, Tarea::count());
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:reiniciar-cursos-demo', ['--apply' => true])->expectsOutputToContain('APP_ENV=production')->assertFailed();

        $this->assertSame([$this->anterior->id], Curso::pluck('id')->all());
    }
}
