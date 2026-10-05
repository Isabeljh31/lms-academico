<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\BotSugerencias\Widget;
use App\Models\ConversacionBot;
use App\Models\Curso;
use App\Models\User;
use App\Services\Bot\ClaudeBotService;
use App\Services\Bot\Contracts\ProveedorIA;
use App\Services\Bot\GroqProveedor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BotFaqTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (Rol::cases() as $rol) {
            Role::firstOrCreate(['name' => $rol->value]);
        }
    }

    public function test_el_proveedor_por_defecto_es_groq_y_corre_en_modo_simulado_sin_api_key(): void
    {
        config(['services.ia.proveedor' => 'groq', 'services.groq.key' => null]);

        $bot = app(ProveedorIA::class);

        $this->assertInstanceOf(GroqProveedor::class, $bot);
        $this->assertFalse($bot->activo());

        $respuesta = $bot->responder('¿Cuándo vence la tarea?', null);

        $this->assertStringContainsString('MODO SIMULADO', $respuesta);
        $this->assertStringContainsString('GROQ_API_KEY', $respuesta);
        $this->assertStringContainsString('¿Cuándo vence la tarea?', $respuesta);
    }

    public function test_ia_proveedor_claude_resuelve_claudebotservice_y_corre_en_modo_simulado_sin_api_key(): void
    {
        config(['services.ia.proveedor' => 'claude', 'services.anthropic.key' => null]);

        $bot = app(ProveedorIA::class);

        $this->assertInstanceOf(ClaudeBotService::class, $bot);
        $this->assertFalse($bot->activo());

        $respuesta = $bot->responder('¿Cuándo vence la tarea?', null);

        $this->assertStringContainsString('MODO SIMULADO', $respuesta);
        $this->assertStringContainsString('ANTHROPIC_API_KEY', $respuesta);
    }

    public function test_los_tres_roles_pueden_usar_el_widget_del_bot(): void
    {
        foreach ([Rol::Supervisor, Rol::Profesor, Rol::Estudiante] as $rol) {
            $usuario = User::factory()->create()->assignRole($rol->value);

            Livewire::actingAs($usuario)
                ->test(Widget::class)
                ->set('mensaje', 'Hola, tengo una duda')
                ->call('enviar')
                ->assertHasNoErrors();

            $this->assertDatabaseHas('conversaciones_bot', ['usuario_id' => $usuario->id]);
        }
    }

    public function test_el_historial_de_la_conversacion_se_guarda_con_pregunta_y_respuesta(): void
    {
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);

        Livewire::actingAs($estudiante)
            ->test(Widget::class)
            ->set('mensaje', '¿Qué quizzes tengo pendientes?')
            ->call('enviar');

        $conversacion = ConversacionBot::where('usuario_id', $estudiante->id)->firstOrFail();

        $this->assertCount(2, $conversacion->mensajes);
        $this->assertEquals('usuario', $conversacion->mensajes[0]['rol']);
        $this->assertEquals('asistente', $conversacion->mensajes[1]['rol']);
    }

    public function test_la_conversacion_se_asocia_al_curso_cuando_hay_contexto(): void
    {
        $profesor = User::factory()->create()->assignRole(Rol::Profesor->value);
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id]);
        $estudiante = User::factory()->create()->assignRole(Rol::Estudiante->value);
        $curso->inscripciones()->create(['estudiante_id' => $estudiante->id, 'fecha_inscripcion' => now()]);

        // El curso de contexto lo fija el servidor (antes el test lo asignaba
        // desde el cliente, lo que permitía consultar cursos ajenos: RF-94).
        Livewire::actingAs($estudiante)
            ->test(Widget::class, ['curso' => $curso])
            ->set('mensaje', '¿De qué trata este curso?')
            ->call('enviar');

        $this->assertDatabaseHas('conversaciones_bot', [
            'usuario_id' => $estudiante->id,
            'curso_id' => $curso->id,
        ]);
    }
}
