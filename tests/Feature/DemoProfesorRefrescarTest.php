<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * app:demo-profesor-refrescar: simula por defecto, aplica solo en los cursos
 * donde el profesor es el único docente, respalda y se puede revertir.
 */
class DemoProfesorRefrescarTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected User $profesor;

    protected Entrega $pendiente;

    protected Entrega $calificada;

    protected Entrega $compartida;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-13 15:00:00'));
        // Respaldos en una carpeta temporal propia de esta prueba (no en storage/ del proyecto).
        $almacen = sys_get_temp_dir().'/demo-profesor-'.uniqid();
        File::ensureDirectoryExists($almacen.'/app');
        $this->app->useStoragePath($almacen);

        $this->profesor = $this->usuarioConRol('profesor');
        $propio = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'estado' => 'publicado', 'fecha_inicio' => '2026-09-07', 'semanas_duracion' => 16]);
        $compartido = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'estado' => 'publicado', 'fecha_inicio' => '2026-09-07', 'semanas_duracion' => 16]);
        $compartido->profesoresAdicionales()->attach($this->usuarioConRol('profesor')->id);

        $this->pendiente = $this->entregaAntigua($propio, 'entregado');
        $this->calificada = $this->entregaAntigua($propio, 'calificado');
        $this->compartida = $this->entregaAntigua($compartido, 'entregado');
    }

    protected function entregaAntigua(Curso $curso, string $estado): Entrega
    {
        $modulo = Modulo::firstOrCreate(['curso_id' => $curso->id, 'semana' => 7], ['titulo' => 'Semana 7', 'materiales' => []]);
        $tarea = Tarea::firstOrCreate(['curso_id' => $curso->id, 'modulo_id' => $modulo->id], Tarea::factory()->raw([
            'curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_inicio' => '2026-10-19 00:00', 'fecha_entrega' => '2026-10-23 23:59',
        ]));
        $estudiante = $this->usuarioConRol('estudiante');
        $this->matricular($estudiante, $curso);
        $entrega = Entrega::factory()->create(['tarea_id' => $tarea->id, 'estudiante_id' => $estudiante->id, 'estado' => $estado, 'nota' => $estado === 'calificado' ? 15 : null]);
        $entrega->forceFill(['created_at' => '2026-10-20 10:00:00', 'updated_at' => '2026-10-20 10:00:00'])->saveQuietly();

        return $entrega;
    }

    public function test_por_defecto_simula_sin_escribir_y_deja_un_respaldo(): void
    {
        $this->artisan('app:demo-profesor-refrescar', ['--profesor' => $this->profesor->id])
            ->expectsOutputToContain('SIMULACIÓN')
            ->expectsOutputToContain('Prueba de --restore (dentro de la simulación): OK')
            ->assertSuccessful();

        $this->assertSame('2026-10-20 10:00:00', (string) $this->pendiente->fresh()->updated_at);
        $this->assertCount(1, File::directories(storage_path('app/backups-demo')));
    }

    public function test_aplicar_cambia_solo_las_pendientes_del_alcance_y_restore_las_devuelve(): void
    {
        $this->artisan('app:demo-profesor-refrescar', ['--profesor' => $this->profesor->id, '--apply' => true])->assertSuccessful();

        $espera = (int) $this->pendiente->fresh()->updated_at->startOfDay()->diffInDays(now()->startOfDay());
        $this->assertGreaterThanOrEqual(5, $espera);
        $this->assertLessThanOrEqual(10, $espera);
        // Calificadas y cursos con co-docentes no se tocan.
        $this->assertSame('2026-10-20 10:00:00', (string) $this->calificada->fresh()->updated_at);
        $this->assertSame('2026-10-20 10:00:00', (string) $this->compartida->fresh()->updated_at);

        $carpeta = collect(File::directories(storage_path('app/backups-demo')))->first();
        $this->artisan('app:demo-profesor-refrescar', ['--restore' => $carpeta, '--apply' => true])->expectsOutputToContain('RESTAURADO.')->assertSuccessful();

        $this->assertSame('2026-10-20 10:00:00', (string) $this->pendiente->fresh()->updated_at);
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('app:demo-profesor-refrescar', ['--profesor' => $this->profesor->id, '--apply' => true])
            ->expectsOutputToContain('APP_ENV=production')
            ->assertFailed();

        $this->assertSame('2026-10-20 10:00:00', (string) $this->pendiente->fresh()->updated_at);
    }
}
