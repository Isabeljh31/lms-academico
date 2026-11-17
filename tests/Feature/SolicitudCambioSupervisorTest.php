<?php

namespace Tests\Feature;

use App\Livewire\Admin\SolicitudesCambioClase\Index;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;
use App\Notifications\SolicitudCambioClaseRecibida;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Bandeja del Supervisor «Solicitudes de cambio de clase»: acceso, pestañas,
 * aprobar y rechazar desde la pantalla, avisos no bloqueantes y diseño del
 * Supervisor (sin clases ni componentes del panel del Profesor).
 */
class SolicitudCambioSupervisorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    protected User $profesor;

    protected User $supervisor;

    protected User $estudiante;

    protected Curso $curso;

    protected SesionClase $sesion;

    protected SolicitudCambioClase $solicitud;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        $this->profesor = $this->usuarioConRol('profesor', ['name' => 'Pepito Reyes']);
        $this->supervisor = $this->usuarioConRol('supervisor');
        $this->estudiante = $this->usuarioConRol('estudiante');
        $this->curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Física I', 'estado' => 'publicado',
            'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 16, 'modalidad' => 'presencial', 'usa_asistencia' => true]);
        $this->matricular($this->estudiante, $this->curso);
        $this->sesion = SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Teoría', 'fecha' => '2026-11-16 14:00', 'duracion_minutos' => 120, 'descripcion' => 'Aula: C-302', 'numero_sesion' => 21]);
        $this->solicitud = app(SolicitudesCambioClase::class)->solicitar($this->profesor, $this->sesion, [
            'tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Cita médica', 'modalidad' => 'presencial', 'fecha_propuesta' => '2026-11-19 16:00',
        ]);
    }

    public function test_solo_el_supervisor_entra_a_la_bandeja_y_ve_el_enlace_del_menu(): void
    {
        $this->actingAs($this->profesor)->get(route('admin.solicitudes-cambio-clase.index'))->assertForbidden();
        $this->actingAs($this->estudiante)->get(route('admin.solicitudes-cambio-clase.index'))->assertForbidden();

        $this->actingAs($this->supervisor)->get(route('admin.solicitudes-cambio-clase.index'))
            ->assertOk()
            ->assertSee('Reprogramaciones')
            ->assertSee('Física I · Sesión 21')
            ->assertSee('Pepito Reyes')
            ->assertSee('Motivo: Salud')
            ->assertSee('Pidió: Presencial')
            ->assertSee('href="'.route('admin.solicitudes-cambio-clase.index').'"', false)
            ->assertSee('href="'.route('admin.sedes-salones.index').'"', false)
            ->assertSee('Sedes y salones');

        $this->actingAs($this->estudiante)->get(route('dashboard'))->assertDontSee('Reprogramaciones')->assertDontSee('Sedes y salones');
        $this->actingAs($this->profesor)->get(route('dashboard'))->assertDontSee('Reprogramaciones')->assertDontSee('Sedes y salones');
    }

    public function test_la_pantalla_usa_el_diseno_del_supervisor_y_no_el_del_profesor(): void
    {
        $html = Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('seleccionar', $this->solicitud->id)
            ->html();

        foreach (['var(--p900)', 'var(--p700)', 'var(--mint)', '#093B3B', 'tema-profesor', 'desplegable-modalidad-boton'] as $marcaProfesor) {
            $this->assertStringNotContainsString($marcaProfesor, $html);
        }
        $this->assertStringContainsString('border-arena-medio', $html);
        $this->assertStringNotContainsString('<select', $html);
    }

    public function test_el_detalle_muestra_la_solicitud_y_parte_de_la_propuesta_del_profesor(): void
    {
        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('seleccionar', $this->solicitud->id)
            ->assertSet('fecha', '2026-11-19')
            ->assertSet('horaInicio', '16:00')
            ->assertSet('horaFin', '18:00')
            ->assertSet('modalidad', 'presencial')
            ->assertSet('aula', 'C-302')
            ->assertSee('16/11/2026 · 14:00 – 16:00')
            ->assertSee('Aula C-302')
            ->assertSee('Reprogramar a otra fecha')
            ->assertSee('Cita médica');
    }

    public function test_aprobar_desde_la_pantalla_reprograma_la_clase(): void
    {
        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('seleccionar', $this->solicitud->id)
            ->set('fecha', '2026-11-20')
            ->set('horaInicio', '10:00')
            ->set('horaFin', '11:30')
            ->set('salonElegido', Index::SIN_SALON)
            ->set('aula', 'B-12')
            ->call('aprobar')
            ->assertHasNoErrors()
            ->assertSet('seleccionId', null);

        $this->assertSame('aprobada', $this->solicitud->fresh()->estado);
        $this->assertSame('2026-11-20 10:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
        $this->assertSame(90, $this->sesion->fresh()->duracion_minutos);
        $this->assertSame('Aula: B-12', $this->sesion->fresh()->descripcion);

        Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('verPestana', 'registro')
            ->assertSee('Física I · Sesión 21')
            ->call('seleccionar', $this->solicitud->id)
            ->assertSee('20/11/2026 · 10:00 – 11:30 · Presencial')
            ->assertSee('Aula B-12')
            ->assertDontSee('Aprobar y reprogramar');
    }

    public function test_presencial_exige_elegir_salon_o_sin_salon_con_aula_y_rechazar_exige_comentario(): void
    {
        $bandeja = Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('seleccionar', $this->solicitud->id)
            ->assertSet('salonElegido', '')
            ->call('aprobar')
            ->assertHasErrors('salonElegido')
            ->set('salonElegido', Index::SIN_SALON)
            ->assertSee('Sin salón asignado: la clase no queda en ningún salón del catálogo')
            ->set('aula', '')
            ->call('aprobar')
            ->assertHasErrors('aula')
            ->call('rechazar')
            ->assertHasErrors('comentario');

        $this->assertSame('pendiente', $this->solicitud->fresh()->estado);

        $bandeja->set('comentario', 'No hay aulas ese día.')->call('rechazar')->assertHasNoErrors();
        $this->assertSame('rechazada', $this->solicitud->fresh()->estado);
        $this->assertSame('2026-11-16 14:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_avisa_cruces_fecha_fuera_de_semanas_y_hora_original_pasada(): void
    {
        $otroCurso = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id, 'nombre' => 'Química']);
        SesionClase::create(['curso_id' => $otroCurso->id, 'titulo' => 'Lab', 'fecha' => '2026-11-19 16:30', 'duracion_minutos' => 60, 'descripcion' => 'Aula: C-302', 'numero_sesion' => 1]);
        $delDocente = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'nombre' => 'Física II']);
        SesionClase::create(['curso_id' => $delDocente->id, 'titulo' => 'Teoría', 'fecha' => '2026-11-19 17:00', 'duracion_minutos' => 60, 'numero_sesion' => 1]);

        $bandeja = Livewire::actingAs($this->supervisor)->test(Index::class)
            ->call('seleccionar', $this->solicitud->id)
            ->set('salonElegido', Index::SIN_SALON)
            ->assertSee('Posible cruce de aula: Química')
            ->assertSee('Posible cruce de docente: Física II')
            ->assertDontSee(Index::AVISO_HORA_PASADA)
            ->set('fecha', '2027-03-01')
            ->assertSee('La nueva fecha cae fuera de las 16 semanas del curso.');

        // La clase sigue en su hora original y esta ya pasó.
        $this->travelTo(Carbon::parse('2026-11-16 18:00:00'));
        $bandeja->set('fecha', '2026-11-20')->assertSee(Index::AVISO_HORA_PASADA);
    }

    public function test_la_notificacion_del_supervisor_lleva_a_su_bandeja(): void
    {
        $datos = (new SolicitudCambioClaseRecibida($this->solicitud))->toArray($this->supervisor);

        $this->assertSame(route('admin.solicitudes-cambio-clase.index'), $datos['url']);
        $this->assertSame(1, $this->supervisor->notifications()->where('type', SolicitudCambioClaseRecibida::class)->count());
    }
}
