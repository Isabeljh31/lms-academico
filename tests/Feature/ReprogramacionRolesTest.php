<?php

namespace Tests\Feature;

use App\Livewire\Admin\SedesSalones\Index as SedesSalones;
use App\Livewire\Admin\SolicitudesCambioClase\Index as Reprogramaciones;
use App\Livewire\Docente\Asistencia\SolicitudCambio;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;
use App\Services\Asistencia\AsignacionSalones;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Matriz final de roles de la reprogramación: profesor dueño, otro
 * profesor, estudiante, supervisor y sin sesión sobre cada ruta, pantalla y
 * acción nueva. Sin sesión redirige al login; sin permiso, 403.
 */
class ReprogramacionRolesTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    /** @var array<string, mixed> */
    private array $escenario;

    private SesionClase $sesion;

    private SolicitudCambioClase $conSustento;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Storage::fake(config('archivos.disco'), ['serve' => true]);

        $this->escenario = $this->escenario();
        $this->escenario['curso']->update(['modalidad' => 'presencial', 'usa_asistencia' => true]);
        $salon = Salon::factory()->create(['codigo' => 'A-201']);
        $this->sesion = $this->escenario['curso']->sesionesClase()->create([
            'titulo' => 'Clase', 'fecha' => '2026-11-23 08:00', 'duracion_minutos' => 90, 'numero_sesion' => 1, 'descripcion' => 'Aula: A-201', 'salon_id' => $salon->id,
        ]);

        $servicio = app(SolicitudesCambioClase::class);
        $this->conSustento = $servicio->solicitar($this->escenario['profesor'], $this->sesion, [
            'tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial', 'fecha_propuesta' => '2026-11-25 10:00',
        ]);
        $servicio->pedirSustento($this->escenario['supervisor'], $this->conSustento, 'Adjunta la constancia.');
        $servicio->responderSustento($this->escenario['profesor'], $this->conSustento, 'Adjunto.', UploadedFile::fake()->create('constancia.pdf', 10));
    }

    public function test_rutas_nuevas_por_rol(): void
    {
        $sustento = route('reprogramaciones.sustento', $this->conSustento);
        $asistencia = route('asistencia.index', $this->escenario['curso']);
        $esperado = [
            // ruta => [sin sesión, estudiante, otro profesor, profesor dueño, supervisor]
            route('admin.sedes-salones.index') => ['login', 403, 403, 403, 200],
            route('admin.solicitudes-cambio-clase.index') => ['login', 403, 403, 403, 200],
            $sustento => ['login', 403, 403, 302, 302],
            $asistencia => ['login', 403, 403, 200, 200],
        ];

        foreach ($esperado as $ruta => [$invitado, $estudiante, $otro, $dueno, $supervisor]) {
            $this->assertSame($invitado, $this->get($ruta)->isRedirect(route('login')) ? 'login' : 'no', "Sin sesión: {$ruta}");

            foreach ([[$this->escenario['estudiante'], $estudiante], [$this->usuarioConRol('profesor'), $otro], [$this->escenario['profesor'], $dueno], [$this->escenario['supervisor'], $supervisor]] as [$usuario, $estado]) {
                $this->actingAs($usuario)->get($ruta)->assertStatus($estado);
            }

            auth()->logout();
        }
    }

    public function test_pantallas_livewire_por_rol(): void
    {
        foreach ([$this->escenario['estudiante'], $this->escenario['profesor']] as $sinPermiso) {
            Livewire::actingAs($sinPermiso)->test(SedesSalones::class)->assertForbidden();
            Livewire::actingAs($sinPermiso)->test(Reprogramaciones::class)->assertForbidden();
        }

        foreach ([$this->escenario['estudiante'], $this->escenario['supervisor'], $this->usuarioConRol('profesor')] as $ajeno) {
            Livewire::actingAs($ajeno)->test(SolicitudCambio::class, ['curso' => $this->escenario['curso']])->assertForbidden();
        }

        Livewire::actingAs($this->escenario['supervisor'])->test(SedesSalones::class)->assertOk();
        Livewire::actingAs($this->escenario['supervisor'])->test(Reprogramaciones::class)->assertOk();
        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->escenario['curso']])->assertOk();
    }

    public function test_acciones_del_servicio_por_rol(): void
    {
        $servicio = app(SolicitudesCambioClase::class);
        $otroProfesor = $this->usuarioConRol('profesor');
        $solicitud = $this->conSustento;
        $aprobar = ['fecha' => '2026-11-25', 'hora_inicio' => '10:00', 'hora_fin' => '11:30', 'modalidad' => 'remoto'];

        $soloSupervisor = [
            'aprobar' => fn (User $usuario) => $servicio->aprobar($usuario, $solicitud, $aprobar),
            'rechazar' => fn (User $usuario) => $servicio->rechazar($usuario, $solicitud, 'No procede.'),
            'pedirSustento' => fn (User $usuario) => $servicio->pedirSustento($usuario, $solicitud, 'Otra vez.'),
            'revertir' => fn (User $usuario) => $servicio->revertir($usuario, $solicitud, 'No.'),
            'asignarSalones' => fn (User $usuario) => app(AsignacionSalones::class)->asignar($usuario, $this->escenario['curso']),
        ];
        foreach ($soloSupervisor as $accion => $ejecutar) {
            foreach ([$this->escenario['estudiante'], $this->escenario['profesor'], $otroProfesor] as $usuario) {
                $this->assertDeniega($ejecutar, $usuario, $accion);
            }
        }

        $soloProfesorDelCurso = [
            'solicitar' => fn (User $usuario) => $servicio->solicitar($usuario, $this->sesion, ['tipo' => 'remoto', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.']),
            'cancelar' => fn (User $usuario) => $servicio->cancelar($usuario, $solicitud),
            'responderSustento' => fn (User $usuario) => $servicio->responderSustento($usuario, $solicitud, 'Respuesta.'),
        ];
        foreach ($soloProfesorDelCurso as $accion => $ejecutar) {
            foreach ([$this->escenario['estudiante'], $this->escenario['supervisor'], $otroProfesor] as $usuario) {
                $this->assertDeniega($ejecutar, $usuario, $accion);
            }
        }

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }

    private function assertDeniega(callable $accion, User $usuario, string $nombre): void
    {
        try {
            $accion($usuario);
            $this->fail("Se esperaba un rechazo de autorización en {$nombre} para {$usuario->getRoleNames()->first()}.");
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
