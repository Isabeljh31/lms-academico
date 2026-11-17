<?php

namespace Tests\Feature;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Notifications\ClaseReprogramada;
use App\Notifications\SolicitudCambioClaseRecibida;
use App\Services\Asistencia\DisponibilidadSalones;
use App\Services\Asistencia\SolicitudesCambioClase;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Reprogramación con salones reales: se aplica sola si cumple todas las
 * reglas de DisponibilidadSalones; si no, queda en revisión del Supervisor.
 */
class ReprogramacionAutomaticaTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const NUEVA = '2026-11-25 10:00';

    private SolicitudesCambioClase $servicio;

    /** @var array<string, mixed> */
    private array $escenario;

    private Salon $actual;

    private Salon $libre;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        // Lunes 16/11/2026: la clase es el lunes siguiente y la nueva fecha, el miércoles.
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Notification::fake();

        $this->servicio = app(SolicitudesCambioClase::class);
        $this->escenario = $this->escenario();
        $this->escenario['curso']->update(['modalidad' => 'presencial']);
        $this->actual = Salon::factory()->create(['codigo' => 'A-201']);
        $this->libre = Salon::factory()->create(['codigo' => 'B-208', 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $this->sesion = $this->clase($this->escenario['curso'], '2026-11-23 08:00', "Aula: A-201\nTraer laptop.", $this->actual);
    }

    public function test_si_cumple_todo_se_aplica_sola_guarda_la_clase_original_y_avisa_solo_a_los_estudiantes(): void
    {
        $solicitud = $this->pedir();

        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertSame('automatica', $solicitud->origen);
        $this->assertNull($solicitud->decidido_por);
        $this->assertNull($solicitud->razon_revision);
        $this->assertSame($this->actual->id, $solicitud->salon_original_id);
        $this->assertSame("Aula: A-201\nTraer laptop.", $solicitud->descripcion_original);
        $this->assertSame($this->libre->id, $solicitud->salon_nuevo_id);
        $this->assertSame('B-208', $solicitud->aula_nueva);

        $movida = $this->sesion->fresh();
        $this->assertSame(self::NUEVA, $movida->fecha->format('Y-m-d H:i'));
        $this->assertSame(90, $movida->duracion_minutos);
        $this->assertSame($this->libre->id, $movida->salon_id);
        $this->assertSame("Aula: B-208\nTraer laptop.", $movida->descripcion);

        Notification::assertSentTo($this->escenario['estudiante'], ClaseReprogramada::class);
        Notification::assertNotSentTo($this->escenario['supervisor'], SolicitudCambioClaseRecibida::class);
        $this->assertSame(1, BitacoraAuditoria::where('tipo_evento', 'cambio_clase')->where('descripcion', 'like', 'Reprogramación aplicada automáticamente%')->count());
    }

    public function test_un_salon_ocupado_por_salon_id_o_por_el_texto_de_una_clase_antigua_no_se_puede_tomar(): void
    {
        $otroCurso = Curso::factory()->create(['usa_asistencia' => true, 'profesor_id' => $this->usuarioConRol('profesor')->id]);
        $choque = $this->clase($otroCurso, '2026-11-25 09:30', null, $this->libre);

        $this->assertMensaje(fn () => $this->pedir(), 'horario', DisponibilidadSalones::MENSAJE_HORARIO_TOMADO);

        $choque->update(['salon_id' => null, 'descripcion' => 'Aula: b-208']);
        $this->assertMensaje(fn () => $this->pedir(), 'horario', DisponibilidadSalones::MENSAJE_HORARIO_TOMADO);

        $this->assertSame(0, SolicitudCambioClase::count());
        $this->assertSame('2026-11-23 08:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_lo_que_no_cumple_queda_en_revision_con_su_razon_y_avisa_al_supervisor(): void
    {
        $otraSede = Salon::factory()->create(['tipo_salon_id' => $this->actual->tipo_salon_id]);
        $otroTipo = Salon::factory()->create(['sede_id' => $this->actual->sede_id]);
        $chico = Salon::factory()->create(['sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id, 'capacidad' => 0]);
        $inactivo = Salon::factory()->inactivo()->create(['sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);

        $casos = [
            'El salón elegido es de otra sede.' => fn () => $this->pedir(['salon_id' => $otraSede->id]),
            'El salón elegido es de otro tipo.' => fn () => $this->pedir(['salon_id' => $otroTipo->id]),
            'El salón elegido no tiene capacidad para 1 matriculados.' => fn () => $this->pedir(['salon_id' => $chico->id]),
            'El salón elegido no está activo.' => fn () => $this->pedir(['salon_id' => $inactivo->id]),
            'No se eligió un salón.' => fn () => $this->pedir(['salon_id' => null]),
            'No se eligió la nueva fecha y hora.' => fn () => $this->pedir(['fecha_propuesta' => null]),
            'Aviso menor a 24 h.' => fn () => $this->pedir(['fecha_propuesta' => '2026-11-16 16:00']),
            'Fuera del horario de reprogramación (lunes a sábado, de 07:00 a 22:00).' => fn () => $this->pedir(['fecha_propuesta' => '2026-11-29 10:00']),
        ];

        foreach ($casos as $razon => $pedir) {
            $solicitud = $pedir();
            $this->assertSame('pendiente', $solicitud->estado, $razon);
            $this->assertSame($razon, $solicitud->razon_revision);
            $this->servicio->cancelar($this->escenario['profesor'], $solicitud);
        }

        $this->assertSame('2026-11-23 08:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
        Notification::assertSentToTimes($this->escenario['supervisor'], SolicitudCambioClaseRecibida::class, count($casos));
        Notification::assertNotSentTo($this->escenario['estudiante'], ClaseReprogramada::class);
    }

    public function test_una_clase_antigua_sin_salon_va_a_revision(): void
    {
        $antigua = $this->clase($this->escenario['curso'], '2026-11-24 08:00', 'Aula: A-201');

        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $antigua, $this->datos());

        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame('La clase no tiene salón asignado.', $solicitud->razon_revision);
    }

    public function test_el_cruce_con_otra_clase_del_docente_en_cualquier_curso_va_a_revision(): void
    {
        $coDocente = $this->usuarioConRol('profesor');
        $this->escenario['curso']->profesoresAdicionales()->attach($coDocente->id);
        $delCoDocente = Curso::factory()->create(['profesor_id' => $coDocente->id]);
        $this->clase($delCoDocente, '2026-11-25 11:00', null);

        $solicitud = $this->pedir();

        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame('Un docente del curso tiene otra clase en ese horario.', $solicitud->razon_revision);
    }

    public function test_el_aviso_minimo_sale_de_la_configuracion(): void
    {
        app(ConfiguracionPlataforma::class)->guardar(['reprogramacion_aviso_minimo_horas' => 200]);

        $this->assertSame('Aviso menor a 200 h.', $this->pedir()->razon_revision);
    }

    public function test_en_un_curso_remoto_reprogramar_no_usa_salon_y_se_aplica_sola(): void
    {
        $curso = $this->escenario['curso'];
        $curso->update(['modalidad' => 'remoto']);
        $remota = $this->clase($curso, '2026-11-24 08:00', null);

        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $remota, $this->datos(['modalidad' => 'remoto', 'salon_id' => $this->libre->id]));

        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertNull($solicitud->salon_nuevo_id);
        $this->assertNull($remota->fresh()->salon_id);
        $this->assertSame(self::NUEVA, $remota->fresh()->fecha->format('Y-m-d H:i'));
        $this->assertSame(SolicitudesCambioClase::LINEA_REMOTA, $remota->fresh()->descripcion);
    }

    public function test_dictarla_en_remoto_en_su_horario_se_aplica_sola_y_quita_el_salon(): void
    {
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, ['tipo' => 'remoto', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.']);

        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertSame('2026-11-23 08:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
        $this->assertNull($this->sesion->fresh()->salon_id);
        $this->assertSame("Modalidad: Remota\nTraer laptop.", $this->sesion->fresh()->descripcion);
    }

    public function test_la_explicacion_es_obligatoria_de_10_a_500_caracteres_y_el_motivo_institucional_existe(): void
    {
        foreach (['', 'corto', str_repeat('a', 501)] as $detalle) {
            try {
                $this->pedir(['detalle' => $detalle]);
                $this->fail('Se esperaba un error en el detalle.');
            } catch (ValidationException $error) {
                $this->assertSame(['detalle'], array_keys($error->errors()));
            }
        }

        $this->assertSame('aprobada', $this->pedir(['motivo' => 'institucional'])->estado);
        $this->assertArrayNotHasKey('academico', SolicitudCambioClase::MOTIVOS);
    }

    public function test_las_horas_y_los_salones_libres_solo_dicen_libre_u_ocupado(): void
    {
        $disponibilidad = app(DisponibilidadSalones::class);
        $otroCurso = Curso::factory()->create(['nombre' => 'Curso ajeno', 'profesor_id' => $this->usuarioConRol('profesor')->id]);
        // Ocupa los dos salones de 10:00 a 11:30: a las 10:00 no hay lugar.
        $this->clase($otroCurso, '2026-11-25 10:00', null, $this->libre);
        $this->clase($otroCurso, '2026-11-25 10:00', 'Aula: A-201');
        Salon::factory()->create(['sede_id' => $this->actual->sede_id, 'codigo' => 'LAB-X']);

        $horas = collect($disponibilidad->horas($this->sesion, Carbon::parse('2026-11-25'), true))->pluck('disponible', 'hora');

        $this->assertSame('07:00', $horas->keys()->first());
        $this->assertSame('20:30', $horas->keys()->last());
        $this->assertFalse($horas['10:00']);
        $this->assertFalse($horas['09:00']);
        $this->assertTrue($horas['11:30']);
        $this->assertSame([], $disponibilidad->horas($this->sesion, Carbon::parse('2026-11-29'), true));

        $libres = $disponibilidad->salonesLibres($this->sesion, Carbon::parse('2026-11-25 12:00'), 90);
        $this->assertSame(['A-201', 'B-208'], $libres->pluck('codigo')->all());
        $this->assertSame([], $disponibilidad->salonesLibres($this->sesion, Carbon::parse('2026-11-25 10:00'), 90)->all());
    }

    public function test_el_supervisor_aprueba_con_un_salon_libre_y_no_con_uno_ocupado(): void
    {
        $antigua = $this->clase($this->escenario['curso'], '2026-11-24 08:00', 'Aula: A-201');
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $antigua, $this->datos());
        $decision = ['fecha' => '2026-11-25', 'hora_inicio' => '10:00', 'hora_fin' => '11:30', 'modalidad' => 'presencial', 'salon_id' => $this->libre->id];
        $choque = $this->clase(Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id]), '2026-11-25 11:00', null, $this->libre);

        $this->assertMensaje(fn () => $this->servicio->aprobar($this->escenario['supervisor'], $solicitud, $decision), 'horario', DisponibilidadSalones::MENSAJE_HORARIO_TOMADO);
        $this->assertSame('pendiente', $solicitud->fresh()->estado);

        $choque->delete();
        $this->servicio->aprobar($this->escenario['supervisor'], $solicitud, $decision);

        $this->assertSame('supervisor', $solicitud->fresh()->origen);
        $this->assertSame($this->libre->id, $antigua->fresh()->salon_id);
        $this->assertSame('Aula: B-208', $antigua->fresh()->descripcion);
    }

    public function test_el_supervisor_ve_el_cruce_real_por_salon(): void
    {
        $antigua = $this->clase($this->escenario['curso'], '2026-11-24 08:00', 'Aula: A-201');
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $antigua, $this->datos());
        $this->clase(Curso::factory()->create(['nombre' => 'Química', 'profesor_id' => $this->usuarioConRol('profesor')->id]), '2026-11-25 10:30', null, $this->libre);

        $avisos = $this->servicio->avisos($solicitud->fresh('curso'), Carbon::parse(self::NUEVA), 90, null, $this->libre);

        $this->assertSame(['Cruce de salón: Química tiene clase en el salón B-208 el 25/11/2026 10:30.'], $avisos);
    }

    /**
     * @param  array<string, mixed>  $cambios
     */
    private function pedir(array $cambios = []): SolicitudCambioClase
    {
        return $this->servicio->solicitar($this->escenario['profesor'], $this->sesion->fresh(), $this->datos($cambios));
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo' => 'reprogramar',
            'motivo' => 'salud',
            'detalle' => 'Motivo general de prueba.',
            'modalidad' => 'presencial',
            'fecha_propuesta' => self::NUEVA,
            'salon_id' => $this->libre->id,
            ...$cambios,
        ];
    }

    private function clase(Curso $curso, string $fecha, ?string $descripcion, ?Salon $salon = null): SesionClase
    {
        return $curso->sesionesClase()->create([
            'titulo' => 'Clase',
            'fecha' => $fecha,
            'duracion_minutos' => 90,
            'numero_sesion' => $curso->sesionesClase()->count() + 1,
            'descripcion' => $descripcion,
            'salon_id' => $salon?->id,
        ]);
    }

    private function assertMensaje(callable $accion, string $campo, string $mensaje): void
    {
        try {
            $accion();
            $this->fail("Se esperaba: {$mensaje}");
        } catch (ValidationException $error) {
            $this->assertSame($mensaje, $error->errors()[$campo][0] ?? null);
        }
    }
}
