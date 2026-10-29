<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\BotSugerencias\Widget;
use App\Livewire\Supervisor\Configuracion;
use App\Livewire\Supervisor\DashboardInstitucional\Index as Metricas;
use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Tarea;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PanelAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_administrador_puede_ver_las_metricas_y_la_configuracion(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->actingAs($admin)->get(route('supervisor.dashboard-institucional'))->assertOk();
        $this->actingAs($admin)->get(route('supervisor.configuracion'))->assertOk();
    }

    public function test_profesor_y_estudiante_no_pueden_ver_el_panel_admin(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        $this->actingAs($profesor)->get(route('supervisor.dashboard-institucional'))->assertForbidden();
        $this->actingAs($estudiante)->get(route('supervisor.configuracion'))->assertForbidden();
    }

    public function test_las_metricas_reflejan_los_cursos_con_atraso(): void
    {
        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'nombre' => 'Curso con atraso']);
        $modulo = Modulo::factory()->create(['curso_id' => $curso->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);
        Tarea::factory()->create(['curso_id' => $curso->id, 'modulo_id' => $modulo->id, 'fecha_entrega' => now()->subDays(2)]);

        Livewire::actingAs($admin)
            ->test(Metricas::class)
            ->assertSee('Curso con atraso')
            ->assertSee('1 entregas atrasadas');
    }

    public function test_administrador_puede_desactivar_el_bot_y_el_widget_deja_de_estar_disponible(): void
    {
        // ConfiguracionPlataforma persiste en un archivo real (ver la clase),
        // no en la base de datos de pruebas: Storage::fake() aísla ese
        // archivo para que el test no dependa de ni deje estado en disco.
        Storage::fake('local');

        $admin = User::factory()->create()->assignRole(Rol::Supervisor->value);

        $this->assertTrue(app(ConfiguracionPlataforma::class)->botActivo(), 'debe empezar activo por defecto');

        Livewire::actingAs($admin)
            ->test(Configuracion::class)
            ->call('alternarBot')
            ->assertSet('botActivo', false);

        $this->assertFalse(app(ConfiguracionPlataforma::class)->botActivo());

        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        // A través de Livewire::test()->call(), la excepción la captura el
        // harness de pruebas de Livewire (no llega a un try/catch normal);
        // se asertar con assertForbidden(), como en los demás módulos.
        Livewire::actingAs($estudiante)
            ->test(Widget::class)
            ->set('mensaje', 'Hola')
            ->call('enviar')
            ->assertForbidden();

        $this->assertDatabaseMissing('conversaciones_bot', ['usuario_id' => $estudiante->id]);
    }
}
