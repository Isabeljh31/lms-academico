<?php

namespace Tests\Feature;

use App\Livewire\Compartido\Asistencia\Index;
use App\Livewire\Profesor\Asistencia\SolicitudCambio;
use App\Livewire\Profesor\Asistencia\Tablero;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Reprogramación de clase en Asistencia del Profesor: menú ⋮
 * («Reprogramar clase» / «Ver reprogramación» / «Reprogramar de nuevo»),
 * modal, marca «Reprogramación en revisión» y bloqueo de «Eliminar clase»
 * mientras haya una en revisión.
 */
class SolicitudCambioProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    protected User $profesor;

    protected User $supervisor;

    protected User $estudiante;

    protected Curso $curso;

    protected SesionClase $futura;

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));

        $this->profesor = $this->usuarioConRol('profesor');
        $this->supervisor = $this->usuarioConRol('supervisor');
        $this->estudiante = $this->usuarioConRol('estudiante');
        $this->curso = Curso::factory()->create(['profesor_id' => $this->profesor->id, 'estado' => 'publicado', 'fecha_inicio' => '2026-10-19',
            'semanas_duracion' => 6, 'modalidad' => 'presencial', 'usa_asistencia' => true]);
        $this->matricular($this->estudiante, $this->curso);
        $this->futura = SesionClase::create(['curso_id' => $this->curso->id, 'titulo' => 'Teoría 4', 'fecha' => '2026-11-12 08:00', 'duracion_minutos' => 120, 'descripcion' => 'Aula: 204', 'numero_sesion' => 4]);
    }

    public function test_el_menu_ofrece_solicitar_y_tras_enviar_marca_la_clase_y_ofrece_ver_la_solicitud(): void
    {
        $tablero = Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSee('Reprogramar clase')
            ->assertDontSee('Reprogramación en revisión');

        Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id)
            ->assertSet('abierto', true)
            ->assertSee('Sesión 4')
            ->set('tipo', 'reprogramar')
            ->set('motivo', 'salud')
            ->set('detalle', 'Motivo general de prueba.')
            ->set('fechaPropuesta', '2026-11-19')
            ->set('horaPropuesta', '16:00')
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSet('abierto', false)
            ->assertDispatched('asistencia-actualizada');

        $solicitud = SolicitudCambioClase::sole();
        $this->assertSame('presencial', $solicitud->modalidad_solicitada);
        $this->assertSame('2026-11-19 16:00', $solicitud->fecha_propuesta->format('Y-m-d H:i'));

        $tablero->call('$refresh')
            ->assertSee('Reprogramación en revisión')
            ->assertSee('Ver reprogramación')
            ->assertDontSee('Reprogramar clase');
    }

    public function test_ver_solicitud_muestra_la_decision_y_cancelar_solo_mientras_este_pendiente(): void
    {
        $servicio = app(SolicitudesCambioClase::class);
        $solicitud = $servicio->solicitar($this->profesor, $this->futura, ['tipo' => 'reprogramar', 'motivo' => 'institucional', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial']);

        $modal = Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id)
            ->assertSee('Reprogramación en revisión')
            ->assertSee('Cancelar reprogramación')
            ->call('cancelarSolicitud');
        $this->assertSame('cancelada', $solicitud->fresh()->estado);
        $modal->assertSet('abierto', false);

        $otra = $servicio->solicitar($this->profesor, $this->futura, ['tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial']);
        $servicio->aprobar($this->supervisor, $otra, ['fecha' => '2026-11-20', 'hora_inicio' => '10:00', 'hora_fin' => '12:00', 'modalidad' => 'presencial', 'aula' => 'C-3']);

        Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id)
            ->assertSee('Reprogramada')
            ->assertSee('Aula C-3')
            ->assertDontSee('Cancelar reprogramación');
    }

    public function test_tras_un_rechazo_se_puede_solicitar_otro_cambio(): void
    {
        $servicio = app(SolicitudesCambioClase::class);
        $rechazada = $servicio->solicitar($this->profesor, $this->futura, ['tipo' => 'remoto', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.']);
        $servicio->rechazar($this->supervisor, $rechazada, 'No procede.');

        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSee('Ver reprogramación')
            ->assertSee('Reprogramar de nuevo');

        Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id, nueva: true)
            ->assertSee('Reprogramar clase')
            ->assertDontSee('No procede.')
            ->set('tipo', 'remoto')
            ->set('motivo', 'personal')
            ->set('detalle', 'Motivo general de prueba.')
            ->call('enviar')
            ->assertHasNoErrors();

        $this->assertSame(['rechazada', 'pendiente'], SolicitudCambioClase::orderBy('id')->pluck('estado')->all());
    }

    public function test_la_confirmacion_es_un_aviso_flotante_visible_sin_scroll(): void
    {
        $html = Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id)
            ->set('tipo', 'remoto')
            ->set('motivo', 'salud')
            ->set('detalle', 'Motivo general de prueba.')
            ->call('enviar')
            ->assertSee('Enviada al supervisor para revisión. Tu clase sigue en su horario hasta que decida.')
            ->html();

        $this->assertMatchesRegularExpression('/data-aviso-flotante[^\n]*\n?[^\n]*\n?[^\n]*class="fixed right-4 top-4/', $html);
    }

    public function test_solo_una_solicitud_pendiente_por_clase(): void
    {
        $pedir = fn () => Livewire::actingAs($this->profesor)->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->futura->id)
            ->set('tipo', 'remoto')
            ->set('motivo', 'personal')
            ->set('detalle', 'Motivo general de prueba.')
            ->call('enviar');

        $pedir()->assertHasNoErrors();
        // Un segundo pedido (p. ej. desde otra pestaña con el formulario aún abierto) se rechaza.
        $pedir()->assertHasErrors('solicitud');

        $this->assertSame(1, SolicitudCambioClase::where('estado', 'pendiente')->count());
    }

    public function test_otro_profesor_no_puede_pedir_ni_abrir_clases_ajenas(): void
    {
        $otro = $this->usuarioConRol('profesor');

        Livewire::actingAs($otro)->test(SolicitudCambio::class, ['curso' => $this->curso])->assertForbidden();

        $suyo = Curso::factory()->create(['profesor_id' => $otro->id, 'usa_asistencia' => true]);
        $this->expectException(ModelNotFoundException::class);
        Livewire::actingAs($otro)->test(SolicitudCambio::class, ['curso' => $suyo])->call('abrir', $this->futura->id);
    }

    public function test_no_se_elimina_una_clase_con_solicitud_pendiente(): void
    {
        $solicitud = app(SolicitudesCambioClase::class)->solicitar($this->profesor, $this->futura, ['tipo' => 'remoto', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.']);

        Livewire::actingAs($this->profesor)->test(Tablero::class, ['curso' => $this->curso])
            ->assertSee('Tiene una reprogramación pendiente: cancélala para poder eliminar la clase.');

        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('eliminarSesion', $this->futura->id);
        $this->assertNotNull($this->futura->fresh());
        $this->assertSame('pendiente', $solicitud->fresh()->estado);

        // Cancelada, ya se puede eliminar.
        app(SolicitudesCambioClase::class)->cancelar($this->profesor, $solicitud);
        Livewire::actingAs($this->profesor)->test(Index::class, ['curso' => $this->curso])
            ->call('eliminarSesion', $this->futura->id);
        $this->assertNull($this->futura->fresh());
    }

    public function test_el_supervisor_y_el_estudiante_no_ven_la_variante_del_profesor(): void
    {
        $this->actingAs($this->profesor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertSee('Reprogramar clase');

        $this->actingAs($this->supervisor)->get(route('asistencia.index', $this->curso))
            ->assertOk()
            ->assertDontSee('Reprogramar clase')
            ->assertDontSee('Reprogramación en revisión');

        $this->actingAs($this->estudiante)->get(route('asistencia.index', $this->curso))->assertForbidden();
        Livewire::actingAs($this->estudiante)->test(SolicitudCambio::class, ['curso' => $this->curso])->assertForbidden();
        Livewire::actingAs($this->supervisor)->test(SolicitudCambio::class, ['curso' => $this->curso])->assertForbidden();
    }
}
