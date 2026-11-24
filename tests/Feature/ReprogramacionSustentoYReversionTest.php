<?php

namespace Tests\Feature;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Notifications\ClaseReprogramada;
use App\Notifications\ReprogramacionRevertida;
use App\Notifications\ReprogramacionSustentoSolicitado;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Reprogramación: el Supervisor pide sustento (el Profesor responde con
 * texto y, si quiere, un PDF o imagen privada) y revierte una
 * reprogramación aplicada si el horario original sigue libre.
 */
class ReprogramacionSustentoYReversionTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private SolicitudesCambioClase $servicio;

    /** @var array<string, mixed> */
    private array $escenario;

    private Salon $actual;

    private Salon $libre;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Notification::fake();
        Storage::fake(config('archivos.disco'));

        $this->servicio = app(SolicitudesCambioClase::class);
        $this->escenario = $this->escenario();
        $this->actual = Salon::factory()->create(['codigo' => 'A-201']);
        $this->libre = Salon::factory()->create(['codigo' => 'B-208', 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $this->sesion = $this->escenario['curso']->sesionesClase()->create([
            'titulo' => 'Clase', 'fecha' => '2026-11-23 08:00', 'duracion_minutos' => 90, 'numero_sesion' => 1,
            'descripcion' => 'Aula: A-201', 'salon_id' => $this->actual->id,
        ]);
    }

    public function test_pedir_sustento_es_del_supervisor_avisa_al_profesor_y_se_pide_una_vez(): void
    {
        $solicitud = $this->enRevision();

        $this->expectsAuthorization(fn () => $this->servicio->pedirSustento($this->escenario['profesor'], $solicitud, 'Adjunta la constancia.'));

        $this->servicio->pedirSustento($this->escenario['supervisor'], $solicitud, 'Adjunta la constancia.');

        $this->assertTrue($solicitud->fresh()->esperaSustento());
        $this->assertSame('pendiente', $solicitud->fresh()->estado);
        Notification::assertSentTo($this->escenario['profesor'], ReprogramacionSustentoSolicitado::class);
        Notification::assertNotSentTo($this->escenario['estudiante'], ReprogramacionSustentoSolicitado::class);
        $this->assertRechazado(fn () => $this->servicio->pedirSustento($this->escenario['supervisor'], $solicitud, 'Otra vez.'), 'Ya se pidió sustento para esta reprogramación.');
    }

    public function test_el_profesor_responde_con_un_archivo_privado_que_solo_ven_el_supervisor_y_los_docentes(): void
    {
        $solicitud = $this->enRevision();
        $this->assertRechazado(fn () => $this->servicio->responderSustento($this->escenario['profesor'], $solicitud, 'Respuesta'), 'Esta reprogramación no espera un sustento.');
        $this->servicio->pedirSustento($this->escenario['supervisor'], $solicitud, 'Adjunta la constancia.');

        try {
            $this->servicio->responderSustento($this->escenario['profesor'], $solicitud, 'Adjunto.', UploadedFile::fake()->create('script.docx', 10));
            $this->fail('Se esperaba que rechazara un archivo que no es PDF ni imagen.');
        } catch (ValidationException $error) {
            $this->assertSame(['archivo'], array_keys($error->errors()));
        }

        $this->servicio->responderSustento($this->escenario['profesor'], $solicitud, 'Adjunto la constancia.', UploadedFile::fake()->create('constancia.pdf', 10));

        $respondida = $solicitud->fresh();
        $this->assertSame('Adjunto la constancia.', $respondida->sustento_respuesta);
        $this->assertFalse($respondida->esperaSustento());
        Storage::disk(config('archivos.disco'))->assertExists($respondida->sustento_archivo);
        $this->assertStringStartsWith(SolicitudesCambioClase::CARPETA_SUSTENTOS.'/', $respondida->sustento_archivo);

        $otroProfesor = $this->usuarioConRol('profesor');
        $this->assertTrue($this->escenario['supervisor']->can('verSustento', $respondida));
        $this->assertTrue($this->escenario['profesor']->can('verSustento', $respondida));
        $this->assertFalse($this->escenario['estudiante']->can('verSustento', $respondida));
        $this->assertFalse($otroProfesor->can('verSustento', $respondida));
        $this->expectsAuthorization(fn () => $this->servicio->responderSustento($otroProfesor, $respondida, 'Intento'));
    }

    public function test_revertir_devuelve_la_clase_original_avisa_y_queda_en_bitacora(): void
    {
        $aplicada = $this->aplicada();

        $this->expectsAuthorization(fn () => $this->servicio->revertir($this->escenario['profesor'], $aplicada));

        $this->servicio->revertir($this->escenario['supervisor'], $aplicada, 'Se liberó el aula.');

        $clase = $this->sesion->fresh();
        $this->assertSame('2026-11-23 08:00', $clase->fecha->format('Y-m-d H:i'));
        $this->assertSame($this->actual->id, $clase->salon_id);
        $this->assertSame('Aula: A-201', $clase->descripcion);
        $this->assertNull($clase->modalidad);
        $this->assertSame('revertida', $aplicada->fresh()->estado);
        $this->assertSame($this->escenario['supervisor']->id, $aplicada->fresh()->revertida_por);

        Notification::assertSentToTimes($this->escenario['estudiante'], ClaseReprogramada::class, 2);
        Notification::assertSentTo($this->escenario['profesor'], ReprogramacionRevertida::class);
        $this->assertSame(1, BitacoraAuditoria::where('descripcion', 'like', 'Reprogramación revertida%')->count());
        $this->assertRechazado(fn () => $this->servicio->revertir($this->escenario['supervisor'], $aplicada), 'Solo se puede revertir una reprogramación aplicada.');
    }

    public function test_no_se_revierte_si_el_salon_original_ya_esta_ocupado_o_no_es_la_ultima(): void
    {
        $aplicada = $this->aplicada();
        $ajeno = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id]);
        $ocupa = $ajeno->sesionesClase()->create(['titulo' => 'Otra', 'fecha' => '2026-11-23 09:00', 'duracion_minutos' => 60, 'numero_sesion' => 1, 'salon_id' => $this->actual->id]);

        $this->assertRechazado(fn () => $this->servicio->revertir($this->escenario['supervisor'], $aplicada), 'El salón original ya está ocupado en ese horario: no se puede revertir.');
        $this->assertSame('2026-11-25 10:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));

        $ocupa->delete();
        $segunda = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion->fresh(), $this->datos(['fecha_propuesta' => '2026-11-26 10:00', 'salon_id' => $this->actual->id]));
        $this->assertSame('aprobada', $segunda->estado);

        $this->assertRechazado(fn () => $this->servicio->revertir($this->escenario['supervisor'], $aplicada), 'Solo se puede revertir la última reprogramación de la clase.');
    }

    public function test_no_se_revierte_si_la_fecha_original_ya_paso(): void
    {
        $aplicada = $this->aplicada();
        $this->travelTo(Carbon::parse('2026-11-23 09:00:00'));

        $this->assertRechazado(fn () => $this->servicio->revertir($this->escenario['supervisor'], $aplicada), 'La fecha original ya pasó: no se puede revertir.');
    }

    private function enRevision(): SolicitudCambioClase
    {
        return $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));
    }

    private function aplicada(): SolicitudCambioClase
    {
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos());
        $this->assertSame('aprobada', $solicitud->estado);

        return $solicitud;
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo' => 'reprogramar', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial',
            'fecha_propuesta' => '2026-11-25 10:00', 'salon_id' => $this->libre->id, ...$cambios,
        ];
    }

    private function expectsAuthorization(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba un rechazo de autorización.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }

    private function assertRechazado(callable $accion, string $mensaje): void
    {
        try {
            $accion();
            $this->fail("Se esperaba el rechazo: {$mensaje}");
        } catch (ValidationException $error) {
            $this->assertSame($mensaje, $error->errors()['solicitud'][0] ?? null);
        }
    }
}
