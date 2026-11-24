<?php

namespace Tests\Feature;

use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\PendingCommand;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * app:vaciar-datos-prueba: simula por defecto; con --apply (y --conservar-usuarios
 * y la palabra VACIAR) borra todos los cursos y los usuarios que no son profesores
 * ni se conservan, respalda y se puede restaurar.
 */
class VaciarDatosPruebaTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    /** @var array{supervisor: User, profesor: User, estudiante: User, curso: Curso} */
    protected array $datos;

    protected User $conservado;

    protected EventoCalendario $eventoDelProfesor;

    protected int $bitacora;

    protected function setUp(): void
    {
        parent::setUp();
        $almacen = sys_get_temp_dir().'/vaciar-datos-'.uniqid();
        File::ensureDirectoryExists($almacen.'/app');
        $this->app->useStoragePath($almacen);
        Storage::fake('local');
        Storage::fake('public');

        $this->datos = $this->escenario();
        $this->conservado = $this->usuarioConRol('estudiante');
        $this->matricular($this->conservado, $this->datos['curso']);

        foreach ([$this->datos['estudiante'], $this->conservado] as $estudiante) {
            Entrega::factory()->create(['tarea_id' => $this->datos['tarea']->id, 'estudiante_id' => $estudiante->id, 'estado' => 'entregado']);
        }

        $this->eventoDelProfesor = EventoCalendario::create(['usuario_id' => $this->datos['profesor']->id, 'titulo' => 'Reunión', 'fecha' => now()->addDay(), 'tipo' => 'evento']);
        EventoCalendario::create(['usuario_id' => $this->datos['estudiante']->id, 'titulo' => 'Estudiar', 'fecha' => now()->addDay(), 'tipo' => 'evento']);
        DB::table('notifications')->insert(['id' => (string) Str::uuid(), 'type' => 'Prueba', 'notifiable_type' => (new User)->getMorphClass(), 'notifiable_id' => $this->datos['estudiante']->id, 'data' => '{}', 'created_at' => now(), 'updated_at' => now()]);
        $this->bitacora = DB::table('bitacoras_auditoria')->insertGetId(['ejecutado_por' => $this->datos['estudiante']->id, 'tipo_evento' => 'otro_admin', 'curso_id' => $this->datos['curso']->id, 'created_at' => now()]);
    }

    protected function aplicar(array $opciones = []): PendingCommand
    {
        return $this->artisan('app:vaciar-datos-prueba', ['--apply' => true, '--conservar-usuarios' => "{$this->datos['supervisor']->id},{$this->conservado->id}", ...$opciones]);
    }

    public function test_por_defecto_simula_sin_borrar_nada(): void
    {
        $usuarios = User::count();

        $this->artisan('app:vaciar-datos-prueba')
            ->expectsOutputToContain('Verificación OK')
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame(1, Curso::count());
        $this->assertSame($usuarios, User::count());
        $this->assertSame(2, Entrega::count());
        $this->assertSame(2, EventoCalendario::count());
        $this->assertDirectoryDoesNotExist(storage_path('app/backups-demo'));
    }

    public function test_aplicar_borra_cursos_y_usuarios_no_conservados_y_deja_intactos_profesores_y_conservados(): void
    {
        $roles = DB::table('roles')->get();

        $this->aplicar()->expectsQuestion('Esto borra los datos de una base COMPARTIDA. Escribe VACIAR para confirmar', 'VACIAR')
            ->expectsOutputToContain('APLICADO.')
            ->assertSuccessful();

        $this->assertSame(0, Curso::count());
        $this->assertSame(0, Entrega::count());
        $this->assertSame(0, DB::table('inscripciones')->count());
        $this->assertEqualsCanonicalizing([$this->datos['supervisor']->id, $this->datos['profesor']->id, $this->conservado->id], User::pluck('id')->all());
        $this->assertTrue($this->datos['profesor']->fresh()->hasRole('profesor'));
        $this->assertTrue($this->datos['supervisor']->fresh()->hasRole('supervisor'));
        $this->assertTrue($this->conservado->fresh()->hasRole('estudiante'));
        $this->assertEquals($roles, DB::table('roles')->get());
        $this->assertSame([$this->eventoDelProfesor->id], EventoCalendario::pluck('id')->all());
        $this->assertSame(0, DB::table('notifications')->count());
        $this->assertSame(0, DB::table('model_has_roles')->where('model_id', $this->datos['estudiante']->id)->count());
        $this->assertDatabaseHas('bitacoras_auditoria', ['id' => $this->bitacora, 'curso_id' => null, 'ejecutado_por' => null]);
    }

    public function test_restore_reconstruye_lo_borrado(): void
    {
        $this->aplicar()->expectsQuestion('Esto borra los datos de una base COMPARTIDA. Escribe VACIAR para confirmar', 'VACIAR')->assertSuccessful();
        $carpeta = collect(File::directories(storage_path('app/backups-demo')))->first();

        $this->artisan('app:vaciar-datos-prueba', ['--restore' => $carpeta, '--apply' => true])->expectsOutputToContain('RESTAURADO.')->assertSuccessful();

        $this->assertSame([$this->datos['curso']->id], Curso::pluck('id')->all());
        $this->assertSame(2, Entrega::count());
        $this->assertTrue($this->datos['estudiante']->fresh()->hasRole('estudiante'));
        $this->assertSame(2, EventoCalendario::count());
    }

    public function test_aplicar_se_niega_sin_conservar_usuarios(): void
    {
        $this->artisan('app:vaciar-datos-prueba', ['--apply' => true])
            ->expectsOutputToContain('--apply exige --conservar-usuarios')
            ->assertFailed();

        $this->assertSame(1, Curso::count());
    }

    public function test_aplicar_se_cancela_si_no_se_escribe_vaciar(): void
    {
        $usuarios = User::count();

        $this->aplicar()->expectsQuestion('Esto borra los datos de una base COMPARTIDA. Escribe VACIAR para confirmar', 'si')
            ->expectsOutputToContain('Cancelado')
            ->assertFailed();

        $this->assertSame(1, Curso::count());
        $this->assertSame($usuarios, User::count());
    }

    public function test_no_corre_en_produccion(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        $this->aplicar()->expectsOutputToContain('APP_ENV=production')->assertFailed();

        $this->assertSame(1, Curso::count());
    }
}
