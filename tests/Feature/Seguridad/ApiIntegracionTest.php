<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Livewire\Supervisor\Integraciones;
use App\Mail\BienvenidaMail;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\IntegracionExterna;
use App\Models\LogIntegracionApi;
use App\Models\User;
use App\Notifications\MatriculadoEnCurso;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RF-100 a RF-106, RNF-04 y RNF-21: API de integración de matrículas y
 * flujo 9.2 (matrícula vía integración externa).
 */
class ApiIntegracionTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected Curso $curso;

    protected string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        Mail::fake();

        $profesor = $this->usuarioConRol(Rol::Profesor->value);
        $this->curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'codigo' => 'MAT-101']);
        [, $this->apiKey] = IntegracionExterna::generarApiKey('Instituto de prueba');
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(array $cambios = []): array
    {
        return array_replace_recursive([
            'usuario' => ['nombres' => 'Ana', 'apellidos' => 'García', 'correo' => 'ana@instituto.edu.pe', 'rol' => 'estudiante'],
            'curso' => ['id' => $this->curso->id],
            'matricula' => ['estado' => 'activo'],
        ], $cambios);
    }

    public function test_rechaza_con_401_y_registra_una_solicitud_sin_api_key(): void
    {
        $this->postJson('/api/v1/matriculas', $this->payload())->assertStatus(401);

        $this->assertDatabaseHas('logs_integracion_api', ['status_code' => 401, 'integracion_id' => null]);
        $this->assertSame(0, Inscripcion::count());
    }

    public function test_rechaza_con_401_una_api_key_invalida(): void
    {
        $this->withToken('LU_clave_falsa_que_no_existe')->postJson('/api/v1/matriculas', $this->payload())->assertStatus(401);

        $this->assertSame(1, LogIntegracionApi::where('status_code', 401)->count());
    }

    public function test_una_solicitud_valida_crea_la_cuenta_la_matricula_y_notifica(): void
    {
        Notification::fake();

        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())
            ->assertCreated()
            ->assertJson(['ok' => true, 'usuario_creado' => true, 'estado' => 'activo']);

        $usuario = User::where('email', 'ana@instituto.edu.pe')->firstOrFail();
        $this->assertTrue($usuario->hasRole('estudiante'));
        $this->assertTrue($usuario->primer_login);
        $this->assertDatabaseHas('inscripciones', ['curso_id' => $this->curso->id, 'estudiante_id' => $usuario->id, 'origen' => 'api', 'estado' => 'activo']);
        Notification::assertSentTo($usuario, MatriculadoEnCurso::class, fn ($n, $canales) => in_array('mail', $canales, true) && in_array('database', $canales, true));
        Mail::assertSent(BienvenidaMail::class);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'matricula_api', 'ejecutado_por' => null]);
        $this->assertDatabaseHas('logs_integracion_api', ['status_code' => 201]);
    }

    public function test_guarda_el_dni_si_el_sistema_externo_lo_envia(): void
    {
        $this->withToken($this->apiKey)
            ->postJson('/api/v1/matriculas', $this->payload(['usuario' => ['dni' => '07123456']]))
            ->assertCreated();

        $this->assertSame('07123456', User::where('email', 'ana@instituto.edu.pe')->value('dni'));
    }

    public function test_devuelve_422_si_el_dni_o_los_nombres_no_tienen_un_formato_valido(): void
    {
        $this->withToken($this->apiKey)
            ->postJson('/api/v1/matriculas', $this->payload(['usuario' => ['nombres' => 'Ana123', 'dni' => '123']]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario.nombres', 'usuario.dni']);

        $this->assertFalse(User::where('email', 'ana@instituto.edu.pe')->exists());
    }

    public function test_acepta_el_curso_por_codigo(): void
    {
        $this->withToken($this->apiKey)
            ->postJson('/api/v1/matriculas', $this->payload(['curso' => ['id' => null, 'codigo' => 'mat-101']]))
            ->assertCreated();
    }

    public function test_un_payload_incompleto_devuelve_422_y_queda_registrado(): void
    {
        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', ['usuario' => ['correo' => 'no-es-correo']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['usuario.nombres', 'usuario.correo', 'matricula.estado']);

        $this->assertDatabaseHas('logs_integracion_api', ['status_code' => 422]);
    }

    public function test_un_cuerpo_que_no_es_json_devuelve_415(): void
    {
        $this->withToken($this->apiKey)
            ->post('/api/v1/matriculas', $this->payload(), ['Accept' => 'application/json'])
            ->assertStatus(415);
    }

    public function test_no_matricula_como_estudiante_a_una_cuenta_de_profesor(): void
    {
        $profesor = $this->usuarioConRol(Rol::Profesor->value, ['email' => 'profe@instituto.edu.pe']);

        $this->withToken($this->apiKey)
            ->postJson('/api/v1/matriculas', $this->payload(['usuario' => ['correo' => 'profe@instituto.edu.pe']]))
            ->assertStatus(422);

        $this->assertSame(0, $profesor->inscripciones()->count());
    }

    public function test_el_estado_inactivo_del_sistema_externo_da_de_baja_sin_borrar(): void
    {
        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())->assertCreated();
        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload(['matricula' => ['estado' => 'inactivo']]))->assertOk();

        $inscripcion = Inscripcion::firstOrFail();
        $this->assertSame('inactivo', $inscripcion->estado);
        $this->assertSame('api', $inscripcion->origen);
    }

    public function test_el_bloqueo_local_del_supervisor_se_respeta_al_resincronizar(): void
    {
        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())->assertCreated();
        Inscripcion::firstOrFail()->update(['activo_local' => false]);

        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())->assertOk();

        $this->assertFalse(Inscripcion::firstOrFail()->activo_local);
    }

    public function test_una_api_key_revocada_deja_de_funcionar(): void
    {
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        $integracion = IntegracionExterna::firstOrFail();

        Livewire::actingAs($supervisor)->test(Integraciones::class)->call('revocar', $integracion->id);

        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())->assertStatus(401);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'integracion_api']);
    }

    public function test_al_regenerar_la_key_la_anterior_se_invalida_y_la_nueva_funciona(): void
    {
        $supervisor = $this->usuarioConRol(Rol::Supervisor->value);
        $integracion = IntegracionExterna::firstOrFail();

        $componente = Livewire::actingAs($supervisor)->test(Integraciones::class)->call('regenerar', $integracion->id);
        $nuevaKey = $componente->get('apiKeyVisible');

        $this->assertNotSame($this->apiKey, $nuevaKey);
        $this->withToken($this->apiKey)->postJson('/api/v1/matriculas', $this->payload())->assertStatus(401);
        $this->withToken($nuevaKey)->postJson('/api/v1/matriculas', $this->payload())->assertCreated();
    }

    public function test_la_api_key_se_guarda_solo_como_hash(): void
    {
        $integracion = IntegracionExterna::firstOrFail();

        $this->assertNotSame($this->apiKey, $integracion->api_key_hash);
        $this->assertStringNotContainsString($this->apiKey, json_encode($integracion->toArray()));
    }

    public function test_limita_la_cantidad_de_solicitudes_por_minuto(): void
    {
        config(['services.integracion.limite_por_minuto' => 2]);

        $this->postJson('/api/v1/matriculas', [])->assertStatus(401);
        $this->postJson('/api/v1/matriculas', [])->assertStatus(401);
        $this->postJson('/api/v1/matriculas', [])->assertStatus(429);
    }

    public function test_solo_el_supervisor_gestiona_las_api_keys(): void
    {
        $profesor = $this->usuarioConRol(Rol::Profesor->value);

        $this->actingAs($profesor)->get(route('supervisor.integraciones'))->assertForbidden();
        $this->assertSame(0, BitacoraAuditoria::where('tipo_evento', 'integracion_api')->count());
    }
}
