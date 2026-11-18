<?php

namespace Tests\Feature;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Notifications\ClaseReprogramada;
use App\Notifications\SolicitudCambioClaseRecibida;
use App\Notifications\SolicitudCambioClaseResuelta;
use App\Services\Asistencia\SolicitudesCambioClase;
use App\Services\Reportes\AsistenciaProfesor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Solicitud de cambio de clase: la pide el Profesor del curso, la decide el
 * Supervisor y al aprobarse se mueve la misma clase (SolicitudesCambioClase).
 */
class SolicitudCambioClaseTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const PEDIDO = ['tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial'];

    private SolicitudesCambioClase $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = app(SolicitudesCambioClase::class);
    }

    public function test_el_profesor_pide_el_cambio_y_se_avisa_a_los_supervisores(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso']);

        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, [...self::PEDIDO, 'detalle' => 'Cita médica', 'fecha_propuesta' => now()->addDays(10)->format('Y-m-d H:i')]);

        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertTrue($solicitud->fecha_original->equalTo($sesion->fecha));
        $this->assertTrue($sesion->fresh()->fecha->equalTo($solicitud->fecha_original));
        Notification::assertSentTo($escenario['supervisor'], SolicitudCambioClaseRecibida::class);
        Notification::assertNotSentTo($escenario['estudiante'], SolicitudCambioClaseRecibida::class);
    }

    public function test_dictarla_en_remoto_es_en_la_misma_fecha_sin_fecha_propuesta(): void
    {
        $escenario = $this->escenario();

        $solicitud = $this->servicio->solicitar($escenario['profesor'], $this->clase($escenario['curso']), ['tipo' => 'remoto', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.', 'fecha_propuesta' => now()->addDays(3)->format('Y-m-d H:i')]);

        $this->assertSame('remoto', $solicitud->modalidad_solicitada);
        $this->assertNull($solicitud->fecha_propuesta);
    }

    public function test_un_profesor_ajeno_no_puede_pedir_el_cambio(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->usuarioConRol('profesor');

        $this->expectException(AuthorizationException::class);
        $this->servicio->solicitar($ajeno, $this->clase($escenario['curso']), self::PEDIDO);
    }

    public function test_un_co_docente_si_puede_pedir_el_cambio(): void
    {
        $escenario = $this->escenario();
        $coDocente = $this->usuarioConRol('profesor');
        $escenario['curso']->profesoresAdicionales()->attach($coDocente->id);

        $this->assertSame('pendiente', $this->servicio->solicitar($coDocente, $this->clase($escenario['curso']), self::PEDIDO)->estado);
    }

    public function test_no_se_pide_el_cambio_de_una_clase_pasada_con_lista_o_de_curso_archivado(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];

        $pasada = $this->clase($curso, now()->subDay());
        $conLista = $this->clase($curso, now()->addDays(2), 2);
        $conLista->asistencias()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'presente']);

        $this->assertRechazado(fn () => $this->servicio->solicitar($escenario['profesor'], $pasada, self::PEDIDO), 'Solo se puede pedir el cambio de una clase futura.');
        $this->assertRechazado(fn () => $this->servicio->solicitar($escenario['profesor'], $conLista, self::PEDIDO), 'La clase ya tiene asistencia registrada.');

        $futura = $this->clase($curso, now()->addDays(3), 3);
        $curso->update(['estado' => 'archivado']);
        $this->assertRechazado(fn () => $this->servicio->solicitar($escenario['profesor'], $futura->fresh(), self::PEDIDO), 'El curso está archivado y no admite cambios.');

        $this->assertSame(0, SolicitudCambioClase::count());
    }

    public function test_solo_hay_una_solicitud_pendiente_por_clase(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso']);
        $primera = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);

        $this->assertRechazado(fn () => $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO), 'Esta clase ya tiene una reprogramación pendiente.');

        // Cancelada, se puede volver a pedir.
        $this->servicio->cancelar($escenario['profesor'], $primera);
        $this->assertSame('cancelada', $primera->fresh()->estado);
        $this->assertSame('pendiente', $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO)->estado);
    }

    public function test_los_datos_del_pedido_se_validan(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso']);

        try {
            $this->servicio->solicitar($escenario['profesor'], $sesion, ['tipo' => 'reprogramar', 'motivo' => 'vacaciones', 'detalle' => str_repeat('a', 501)]);
            $this->fail('Se esperaba un error de validación.');
        } catch (ValidationException $error) {
            $this->assertEqualsCanonicalizing(['motivo', 'detalle', 'modalidad'], array_keys($error->errors()));
        }
    }

    public function test_solo_el_supervisor_aprueba_o_rechaza(): void
    {
        $escenario = $this->escenario();
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $this->clase($escenario['curso']), self::PEDIDO);

        foreach ([fn () => $this->servicio->aprobar($escenario['profesor'], $solicitud, $this->decision()), fn () => $this->servicio->rechazar($escenario['profesor'], $solicitud, 'No')] as $accion) {
            try {
                $accion();
                $this->fail('Se esperaba que el Profesor no pudiera decidir.');
            } catch (AuthorizationException) {
                // Correcto.
            }
        }

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }

    public function test_aprobar_mueve_la_misma_clase_avisa_y_no_se_aprueba_dos_veces(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $otra = $this->clase($curso, now()->addDays(20), 2);
        $sesion = $this->clase($curso, now()->addWeek(), 5, "Aula: A-201\nTraer laptop.");
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);
        $nuevaFecha = now()->addDays(9)->format('Y-m-d');

        $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['fecha' => $nuevaFecha, 'hora_inicio' => '10:00', 'hora_fin' => '11:30', 'aula' => 'C-3']));

        $movida = $sesion->fresh();
        $this->assertSame($sesion->id, $movida->id);
        $this->assertSame(5, $movida->numero_sesion);
        $this->assertSame("{$nuevaFecha} 10:00", $movida->fecha->format('Y-m-d H:i'));
        $this->assertSame(90, $movida->duracion_minutos);
        $this->assertSame('presencial', $movida->modalidad);
        $this->assertSame("Aula: C-3\nTraer laptop.", $movida->descripcion);
        $this->assertTrue($otra->fresh()->fecha->equalTo($otra->fecha));

        $aprobada = $solicitud->fresh();
        $this->assertSame('aprobada', $aprobada->estado);
        $this->assertSame($escenario['supervisor']->id, $aprobada->decidido_por);
        $this->assertSame('C-3', $aprobada->aula_nueva);

        Notification::assertSentTo($escenario['profesor'], SolicitudCambioClaseResuelta::class);
        Notification::assertSentTo($escenario['estudiante'], ClaseReprogramada::class);

        $this->assertRechazado(fn () => $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision()), 'Esta solicitud ya no está pendiente.');
        $this->assertSame("{$nuevaFecha} 10:00", $sesion->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_al_decidir_se_apaga_el_aviso_del_supervisor_y_profesor_y_estudiantes_ven_la_clase_reprogramada(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $aprobar = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso, now()->addWeek(), 5, 'Aula: A-201'), self::PEDIDO);
        $rechazar = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso, now()->addDays(12), 6, 'Aula: A-201'), self::PEDIDO);
        $this->assertSame(2, $escenario['supervisor']->unreadNotifications()->count());

        $this->servicio->aprobar($escenario['supervisor'], $aprobar, $this->decision(['fecha' => now()->addDays(9)->format('Y-m-d'), 'hora_inicio' => '10:00', 'hora_fin' => '11:30', 'aula' => 'C-3']));

        // Solo se apaga el aviso de la solicitud decidida; la otra sigue en revisión.
        $this->assertSame([$rechazar->id], $escenario['supervisor']->unreadNotifications()->get()->map(fn ($aviso) => $aviso->data['solicitud_id'])->all());
        $this->assertSame('Clase reprogramada', $escenario['profesor']->unreadNotifications()->sole()->data['titulo']);
        $this->assertSame('Clase reprogramada', $escenario['estudiante']->unreadNotifications()->sole()->data['titulo']);

        $this->servicio->rechazar($escenario['supervisor'], $rechazar, 'No hay salón disponible.');
        $this->assertSame(0, $escenario['supervisor']->unreadNotifications()->count());
    }

    public function test_aprobar_es_transaccional(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso']);
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);
        $bitacoraAntes = BitacoraAuditoria::count();
        SolicitudCambioClase::updated(fn () => throw new RuntimeException('Falla simulada'));

        try {
            $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision());
            $this->fail('Se esperaba la falla simulada.');
        } catch (RuntimeException) {
            // La clase ya se había movido dentro de la transacción: debe volver atrás.
        } finally {
            SolicitudCambioClase::flushEventListeners();
        }

        $this->assertTrue($sesion->fresh()->fecha->equalTo($sesion->fecha));
        $this->assertNull($sesion->fresh()->modalidad);
        $this->assertSame('pendiente', $solicitud->fresh()->estado);
        $this->assertSame($bitacoraAntes, BitacoraAuditoria::count());
    }

    public function test_presencial_exige_aula_remota_no_y_la_fecha_debe_ser_futura(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso'], now()->addWeek(), 1, 'Aula: A-201');
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);

        $this->assertErrores(fn () => $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['aula' => ''])), ['aula']);
        $this->assertErrores(fn () => $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['fecha' => now()->subDay()->format('Y-m-d')])), ['fecha']);
        $this->assertErrores(fn () => $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['hora_fin' => '07:00'])), ['hora_fin']);

        $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['modalidad' => 'remoto', 'aula' => '']));
        $this->assertSame('remoto', $sesion->fresh()->modalidad);
        $this->assertSame('Modalidad: Remota', $sesion->fresh()->descripcion);
    }

    public function test_aprobar_en_remoto_reemplaza_el_aula_por_la_modalidad_y_conserva_el_texto(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso'], now()->addWeek(), 1, "Aula: A-201\nTraer laptop.");
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);

        $this->servicio->aprobar($escenario['supervisor'], $solicitud, $this->decision(['modalidad' => 'remoto', 'aula' => 'B-12']));

        $this->assertSame("Modalidad: Remota\nTraer laptop.", $sesion->fresh()->descripcion);
        $this->assertNull(AsistenciaProfesor::aula($sesion->fresh()));
        $this->assertNull($solicitud->fresh()->aula_nueva);

        // Si después vuelve a presencial, la línea remota se reemplaza por el aula.
        $this->assertSame("Aula: C-3\nTraer laptop.", SolicitudesCambioClase::descripcionConAula("Modalidad: Remota\nTraer laptop.", 'C-3'));
    }

    public function test_rechazar_exige_comentario_no_cambia_la_clase_y_avisa_al_profesor(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso']);
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $sesion, self::PEDIDO);

        $this->assertErrores(fn () => $this->servicio->rechazar($escenario['supervisor'], $solicitud, '  '), ['comentario']);

        $this->servicio->rechazar($escenario['supervisor'], $solicitud, 'No hay aulas ese día.');

        $this->assertSame('rechazada', $solicitud->fresh()->estado);
        $this->assertSame('No hay aulas ese día.', $solicitud->fresh()->comentario_decision);
        $this->assertTrue($sesion->fresh()->fecha->equalTo($sesion->fecha));
        Notification::assertSentTo($escenario['profesor'], SolicitudCambioClaseResuelta::class);
        Notification::assertNotSentTo($escenario['estudiante'], ClaseReprogramada::class);
    }

    public function test_la_bitacora_registra_cada_evento(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];

        $cancelada = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso, now()->addWeek(), 1), self::PEDIDO);
        $this->servicio->cancelar($escenario['profesor'], $cancelada);
        $aprobada = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso, now()->addWeek(), 2), self::PEDIDO);
        $this->servicio->aprobar($escenario['supervisor'], $aprobada, $this->decision());
        $rechazada = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso, now()->addWeek(), 3), self::PEDIDO);
        $this->servicio->rechazar($escenario['supervisor'], $rechazada, 'No procede.');

        $registros = BitacoraAuditoria::where('tipo_evento', 'cambio_clase')->orderBy('id')->get();
        $this->assertSame([
            'Reprogramación enviada a revisión',
            'Reprogramación cancelada',
            'Reprogramación enviada a revisión',
            'Reprogramación aprobada: clase reprogramada',
            'Reprogramación enviada a revisión',
            'Reprogramación rechazada',
        ], $registros->map(fn (BitacoraAuditoria $registro) => strstr($registro->descripcion, ' (clase', true))->all());
        $this->assertTrue($registros->every(fn (BitacoraAuditoria $registro) => $registro->curso_id === $curso->id));
        $this->assertSame($escenario['supervisor']->id, $registros[3]->ejecutado_por);
        $this->assertArrayHasKey('cambio_clase', BitacoraAuditoria::TIPOS);
    }

    public function test_avisa_cruces_de_aula_y_de_docente_y_fecha_fuera_de_las_semanas_con_una_consulta_cada_uno(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $curso->update(['fecha_inicio' => now()->startOfWeek(), 'semanas_duracion' => 16]);
        $solicitud = $this->servicio->solicitar($escenario['profesor'], $this->clase($curso), self::PEDIDO);
        $inicio = now()->addDays(5)->setTime(10, 0);

        // Otro curso, otro docente, misma aula a la misma hora; y otra clase del mismo docente que se superpone.
        $otroCurso = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id, 'nombre' => 'Química']);
        $this->clase($otroCurso, $inicio->copy()->subMinutes(30), 1, 'Aula: C-3');
        $delDocente = Curso::factory()->create(['profesor_id' => $escenario['profesor']->id, 'nombre' => 'Física']);
        $this->clase($delDocente, $inicio->copy()->addMinutes(60), 1, 'Aula: D-1');
        $solicitud = $solicitud->fresh('curso');

        DB::enableQueryLog();
        $avisos = $this->servicio->avisos($solicitud, $inicio, 90, 'C-3');
        $consultas = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $consultas);
        $this->assertCount(2, $avisos);
        $this->assertStringContainsString('Posible cruce de aula: Química', $avisos[0]);
        $this->assertStringContainsString('Posible cruce de docente: Física', $avisos[1]);

        $lejana = now()->startOfWeek()->addWeeks(20)->setTime(10, 0);
        $this->assertContains('La nueva fecha cae fuera de las 16 semanas del curso.', $this->servicio->avisos($solicitud->fresh('curso'), $lejana, 90, null));
    }

    private function clase(Curso $curso, mixed $fecha = null, int $numero = 1, ?string $descripcion = null): SesionClase
    {
        return $curso->sesionesClase()->create([
            'titulo' => 'Clase '.$numero,
            'fecha' => $fecha ?? now()->addWeek()->setTime(8, 0),
            'duracion_minutos' => 90,
            'numero_sesion' => $numero,
            'descripcion' => $descripcion,
        ]);
    }

    /**
     * @param  array<string, string>  $cambios
     * @return array<string, string>
     */
    private function decision(array $cambios = []): array
    {
        return [
            'fecha' => now()->addDays(9)->format('Y-m-d'),
            'hora_inicio' => '08:00',
            'hora_fin' => '09:30',
            'modalidad' => 'presencial',
            'aula' => 'B-12',
            ...$cambios,
        ];
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

    /**
     * @param  list<string>  $campos
     */
    private function assertErrores(callable $accion, array $campos): void
    {
        try {
            $accion();
            $this->fail('Se esperaba un error de validación en '.implode(', ', $campos).'.');
        } catch (ValidationException $error) {
            $this->assertSame($campos, array_keys($error->errors()));
        }
    }
}
