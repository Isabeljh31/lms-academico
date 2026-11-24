<?php

namespace Tests\Feature;

use App\Livewire\Admin\SolicitudesCambioClase\Index;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Notifications\ReprogramacionSustentoRespondido;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Pantalla «Reprogramaciones» del Supervisor: aprobar con un salón de la
 * lista real con disponibilidad, pedir sustento, ver y descargar el
 * sustento (solo Supervisor y Profesor dueño) y revertir.
 */
class ReprogramacionesSupervisorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private SolicitudesCambioClase $servicio;

    /** @var array<string, mixed> */
    private array $escenario;

    private Salon $actual;

    private Salon $libre;

    private Salon $ocupado;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Storage::fake(config('archivos.disco'), ['serve' => true]);

        $this->servicio = app(SolicitudesCambioClase::class);
        $this->escenario = $this->escenario();
        $this->actual = Salon::factory()->create(['codigo' => 'A-201']);
        $this->libre = Salon::factory()->create(['codigo' => 'B-208', 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $this->ocupado = Salon::factory()->create(['codigo' => 'C-302', 'sede_id' => $this->actual->sede_id]);
        Salon::factory()->create(['codigo' => 'OTRA-SEDE']);
        $ajeno = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id]);
        $ajeno->sesionesClase()->create(['titulo' => 'Ajena', 'fecha' => '2026-11-25 10:30', 'duracion_minutos' => 60, 'numero_sesion' => 1, 'salon_id' => $this->ocupado->id]);
        $this->sesion = $this->escenario['curso']->sesionesClase()->create([
            'titulo' => 'Clase', 'fecha' => '2026-11-23 08:00', 'duracion_minutos' => 90, 'numero_sesion' => 3,
            'descripcion' => 'Aula: A-201', 'salon_id' => $this->actual->id,
        ]);
    }

    public function test_muestra_la_razon_y_la_explicacion_y_aprueba_con_un_salon_libre_de_la_lista(): void
    {
        // Sin salón elegido por el profesor: queda en revisión.
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));

        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->assertSee('En revisión')
            ->call('seleccionar', $solicitud->id)
            ->assertSee('No se eligió un salón.')
            ->assertSee('Explicación')
            ->assertSee('Motivo general de prueba.')
            ->assertSet('salonElegido', (string) $this->actual->id)
            ->assertViewHas('opcionesSalon', fn (array $opciones) => array_map('strval', array_keys($opciones)) === [(string) $this->actual->id, (string) $this->libre->id, Index::SIN_SALON])
            ->assertSee('B-208 · ')
            ->assertDontSee('C-302 · ')
            ->assertDontSee('OTRA-SEDE')
            ->set('salonElegido', (string) $this->libre->id)
            ->call('aprobar')
            ->assertHasNoErrors();

        $this->assertSame('aprobada', $solicitud->fresh()->estado);
        $this->assertSame('supervisor', $solicitud->fresh()->origen);
        $this->assertSame($this->libre->id, $this->sesion->fresh()->salon_id);
        $this->assertSame('2026-11-25 10:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
        $this->assertSame('Aula: B-208', $this->sesion->fresh()->descripcion);
    }

    public function test_un_salon_ocupado_no_se_aprueba_y_se_avisa_en_pantalla(): void
    {
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));

        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->call('seleccionar', $solicitud->id)
            ->set('salonElegido', (string) $this->ocupado->id)
            ->assertSee('El salón C-302 no está libre en ese horario: elige otro.')
            ->call('aprobar')
            ->assertHasErrors('horario');

        $this->assertSame('pendiente', $solicitud->fresh()->estado);
    }

    public function test_la_lista_avisa_sin_bloquear_si_el_salon_es_de_otro_tipo_o_le_faltan_plazas(): void
    {
        $this->matricular($this->usuarioConRol('estudiante'), $this->escenario['curso']);
        $otroTipo = Salon::factory()->create(['codigo' => 'LAB-9', 'sede_id' => $this->actual->sede_id]);
        $chico = Salon::factory()->create(['codigo' => 'D-1', 'capacidad' => 1, 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));
        $tipo = $this->actual->tipo->nombre;

        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->call('seleccionar', $solicitud->id)
            ->assertViewHas('opcionesSalon', fn (array $opciones) => str_contains($opciones[$otroTipo->id], "{$otroTipo->tipo->nombre} · 40 plazas · Libre · ⚠ Otro tipo (la clase usa {$tipo})")
                && str_contains($opciones[$chico->id], '1 plazas · Libre · ⚠ Menos plazas que los 2 matriculados')
                && ! str_contains($opciones[$this->libre->id], '⚠'))
            ->set('salonElegido', (string) $otroTipo->id)
            ->assertSee("Salón LAB-9: Otro tipo (la clase usa {$tipo}).")
            ->call('aprobar')
            ->assertHasNoErrors();

        $this->assertSame($otroTipo->id, $this->sesion->fresh()->salon_id);
    }

    public function test_pedir_sustento_y_revertir_desde_la_pantalla(): void
    {
        $enRevision = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));

        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->call('seleccionar', $enRevision->id)
            ->call('pedirSustento')
            ->assertHasErrors('pedido')
            ->set('pedidoSustento', 'Adjunta la constancia.')
            ->call('pedirSustento')
            ->assertHasNoErrors()
            ->assertSee('Sustento solicitado')
            ->assertSee('El profesor aún no responde.');
        $this->assertTrue($enRevision->fresh()->esperaSustento());

        $this->servicio->cancelar($this->escenario['profesor'], $enRevision);
        $aplicada = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion->fresh(), $this->datos());
        $this->assertSame('aprobada', $aplicada->estado);

        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->call('verPestana', 'registro')
            ->assertSee('Aplicada automáticamente')
            ->call('seleccionar', $aplicada->id)
            ->set('comentarioReversion', 'Se liberó el aula.')
            ->call('revertir')
            ->assertHasNoErrors();

        $this->assertSame('revertida', $aplicada->fresh()->estado);
        $this->assertSame('2026-11-23 08:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
        $this->assertSame($this->actual->id, $this->sesion->fresh()->salon_id);
    }

    public function test_el_sustento_se_descarga_con_enlace_firmado_solo_por_el_supervisor_y_el_profesor_dueno(): void
    {
        Notification::fake();
        $solicitud = $this->servicio->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));
        $this->servicio->pedirSustento($this->escenario['supervisor'], $solicitud, 'Adjunta la constancia.');
        $this->servicio->responderSustento($this->escenario['profesor'], $solicitud, 'Adjunto.', UploadedFile::fake()->create('constancia.pdf', 10));
        Notification::assertSentTo($this->escenario['supervisor'], ReprogramacionSustentoRespondido::class);

        $ruta = route('reprogramaciones.sustento', $solicitud);
        Livewire::actingAs($this->escenario['supervisor'])->test(Index::class)
            ->call('seleccionar', $solicitud->id)
            ->assertSee('Ver y descargar sustento')
            ->assertSee($ruta, false);

        foreach ([$this->escenario['supervisor'], $this->escenario['profesor']] as $permitido) {
            $destino = $this->actingAs($permitido)->get($ruta)->assertRedirect()->headers->get('Location');
            // Enlace temporal del disco (firmado en el disco real); el disco falso de pruebas solo marca el vencimiento.
            $this->assertStringContainsString('expiration=', $destino);
            $this->assertStringContainsString(SolicitudesCambioClase::CARPETA_SUSTENTOS.'/', $destino);
        }

        $coDocente = $this->usuarioConRol('profesor');
        $this->escenario['curso']->profesoresAdicionales()->attach($coDocente->id);
        foreach ([$this->escenario['estudiante'], $this->usuarioConRol('profesor'), $coDocente] as $ajeno) {
            $this->actingAs($ajeno)->get($ruta)->assertForbidden();
        }

        $sinArchivo = SolicitudCambioClase::factory()->create();
        $this->actingAs($this->escenario['supervisor'])->get(route('reprogramaciones.sustento', $sinArchivo))->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial',
            'fecha_propuesta' => '2026-11-25 10:00', 'salon_id' => $this->libre->id, ...$cambios,
        ];
    }
}
