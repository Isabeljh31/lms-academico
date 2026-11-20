<?php

namespace Tests\Feature;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\EventoCalendario;
use App\Models\Inscripcion;
use App\Models\Quiz;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * app:sembrar-demo-actual: simula por defecto; con --apply siembra cursos y
 * estudiantes actuales sin atrasos, sin tocar a los usuarios existentes, y
 * --limpiar borra exactamente lo sembrado.
 */
class SembrarDemoActualTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        // Domingo: la semana actual de cada curso ya se dictó completa.
        $this->travelTo(Carbon::parse('2026-11-15 15:00:00'));
        // El manifest va a una carpeta temporal propia de esta prueba (no a storage/ del proyecto).
        $almacen = sys_get_temp_dir().'/sembrar-demo-'.uniqid();
        File::ensureDirectoryExists($almacen.'/app');
        $this->app->useStoragePath($almacen);

        $this->usuarioConRol('supervisor');
        foreach (['Pepito Reyes Diaz', 'José Cuya Camara', 'Alan Garcia', 'Gerardo Sarmiento'] as $nombre) {
            $this->usuarioConRol('profesor', ['name' => $nombre]);
        }
        $this->usuarioConRol('estudiante', ['email' => 'geronimo@lms.com']);
    }

    public function test_por_defecto_simula_sin_escribir_en_la_base_ni_en_disco(): void
    {
        $this->artisan('app:sembrar-demo-actual', ['--sin-paginas' => true])
            ->expectsOutputToContain('SIMULACIÓN')
            ->assertSuccessful();

        $this->assertSame(0, Curso::count());
        $this->assertSame(6, User::count());
        $this->assertDirectoryDoesNotExist(storage_path('app/backups-demo'));
    }

    public function test_aplicar_siembra_cursos_actuales_sin_atrasos_ni_tocar_usuarios_existentes(): void
    {
        $existentes = DB::table('users')->orderBy('id')->get()->toJson();
        $geronimo = User::where('email', 'geronimo@lms.com')->first();

        $this->artisan('app:sembrar-demo-actual', ['--apply' => true, '--sin-paginas' => true])->assertSuccessful();

        $this->assertSame(['borrador' => 1, 'publicado' => 11], Curso::pluck('estado')->countBy()->sortKeys()->all());
        $this->assertSame($existentes, DB::table('users')->where('id', '<=', $geronimo->id)->orderBy('id')->get()->toJson());
        $this->assertSame(41, User::role('estudiante')->count());
        $nuevo = User::where('email', 'valentinarios@lms.com')->first();
        $this->assertTrue(Hash::check('password123', $nuevo->password));
        $this->assertFalse($nuevo->primer_login);
        $this->assertSame(3, Inscripcion::where('estudiante_id', $geronimo->id)->where('estado', 'activo')->count());

        // Ninguna entrega se ve «Fuera de plazo» y nadie quedó sin entregar una tarea vencida.
        $tareas = Tarea::with('entregas')->get();
        $this->assertSame(0, $tareas->sum(fn (Tarea $t) => $t->entregas->filter(fn (Entrega $e) => $t->estadoPara($e) === 'fuera_de_plazo')->count()));
        $this->assertSame(0, $tareas->filter(fn (Tarea $t) => $t->fecha_entrega->isPast())
            ->sum(fn (Tarea $t) => Inscripcion::where('curso_id', $t->curso_id)->where('estado', 'activo')->whereNotIn('estudiante_id', $t->entregas->pluck('estudiante_id'))->count()));
        $this->assertSame(0, Quiz::where('fecha_limite', '<', now())->get()
            ->sum(fn (Quiz $q) => Inscripcion::where('curso_id', $q->curso_id)->where('estado', 'activo')->whereNotIn('estudiante_id', $q->resultados()->select('estudiante_id'))->count()));

        // Por calificar: solo entregas de los últimos 6 días, en cantidad moderada.
        $pendientes = Entrega::where('estado', 'entregado')->get();
        $porCalificar = $pendientes->unique(fn (Entrega $e) => $e->grupo_id ? "g{$e->tarea_id}-{$e->grupo_id}" : "e{$e->id}")->count();
        $this->assertSame(0, $pendientes->filter(fn (Entrega $e) => $e->updated_at->lt(now()->subDays(6)))->count());
        $this->assertTrue($porCalificar >= 60 && $porCalificar <= 110, "por calificar: {$porCalificar}");

        // Lista tomada en todas las sesiones ya dictadas.
        $this->assertSame(0, DB::table('sesiones_clase')->where('fecha', '<=', now())
            ->whereNotExists(fn ($q) => $q->from('asistencias')->whereColumn('asistencias.sesion_id', 'sesiones_clase.id'))->count());

        $this->assertSame(0, BitacoraAuditoria::count());
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertCount(1, File::glob(storage_path('app/backups-demo/*_siembra/manifest.json')));
    }

    public function test_se_niega_si_ya_hay_cursos(): void
    {
        Curso::factory()->create(['profesor_id' => User::where('name', 'Pepito Reyes Diaz')->value('id')]);

        $this->artisan('app:sembrar-demo-actual', ['--apply' => true, '--sin-paginas' => true])
            ->expectsOutputToContain('Ya hay 1 cursos en la base')
            ->assertFailed();

        $this->assertSame(1, Curso::count());
        $this->assertSame(6, User::count());
    }

    public function test_se_niega_en_produccion(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('app:sembrar-demo-actual', ['--apply' => true, '--sin-paginas' => true])
            ->expectsOutputToContain('APP_ENV=production')
            ->assertFailed();

        $this->assertSame(0, Curso::count());
    }

    public function test_limpiar_borra_exactamente_lo_sembrado(): void
    {
        $existentes = DB::table('users')->orderBy('id')->get()->toJson();
        $this->artisan('app:sembrar-demo-actual', ['--apply' => true, '--sin-paginas' => true])->assertSuccessful();
        $carpeta = dirname(File::glob(storage_path('app/backups-demo/*_siembra/manifest.json'))[0]);

        $this->artisan('app:sembrar-demo-actual', ['--limpiar' => $carpeta, '--apply' => true])
            ->expectsOutputToContain('LIMPIADO')
            ->assertSuccessful();

        $this->assertSame(0, Curso::count());
        $this->assertSame(0, Entrega::count());
        $this->assertSame(0, EventoCalendario::count());
        $this->assertSame($existentes, DB::table('users')->orderBy('id')->get()->toJson());
    }
}
