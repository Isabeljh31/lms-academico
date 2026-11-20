<?php

namespace Tests\Feature;

use App\Livewire\Profesor\Asistencia\SolicitudCambio;
use App\Models\Curso;
use App\Models\Salon;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Notifications\ReprogramacionSustentoRespondido;
use App\Services\Asistencia\DisponibilidadSalones;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Modal «Reprogramar clase» del Profesor: horas disponibles, salones libres
 * de la misma sede, tipo y capacidad, aprobación automática o paso a
 * revisión, horario tomado por otro, remoto, respuesta al sustento y que el
 * estudiante nunca ve el motivo ni el sustento.
 */
class ReprogramacionProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const DIA = '2026-11-25';

    private const DETALLE = 'Motivo general de prueba.';

    /** @var array<string, mixed> */
    private array $escenario;

    private Curso $curso;

    private Salon $actual;

    private Salon $libre;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        // Lunes 16/11/2026: la clase es el lunes siguiente y la nueva fecha, el miércoles.
        $this->travelTo(Carbon::parse('2026-11-16 10:00:00'));
        Storage::fake(config('archivos.disco'), ['serve' => true]);

        $this->escenario = $this->escenario();
        $this->curso = $this->escenario['curso'];
        $this->curso->update(['modalidad' => 'presencial']);
        $this->actual = Salon::factory()->create(['codigo' => 'A-201']);
        $this->libre = Salon::factory()->create(['codigo' => 'B-208', 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $this->sesion = $this->clase($this->curso, '2026-11-23 08:00', 'Aula: A-201', $this->actual);
    }

    public function test_otro_profesor_no_puede_abrir_ni_reprogramar_ni_responder(): void
    {
        $otro = $this->usuarioConRol('profesor');

        Livewire::actingAs($otro)->test(SolicitudCambio::class, ['curso' => $this->curso])->assertForbidden();

        $this->assertDeniega(fn () => app(SolicitudesCambioClase::class)->solicitar($otro, $this->sesion, $this->datos()));

        $pendiente = app(SolicitudesCambioClase::class)->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));
        app(SolicitudesCambioClase::class)->pedirSustento($this->escenario['supervisor'], $pendiente, 'Adjunta la constancia.');
        $this->assertDeniega(fn () => app(SolicitudesCambioClase::class)->responderSustento($otro, $pendiente, 'Respuesta ajena.'));
    }

    public function test_la_lista_solo_trae_salones_libres_de_la_misma_sede_tipo_y_capacidad_sin_decir_quien_ocupa(): void
    {
        foreach (range(1, 2) as $_) {
            $this->matricular($this->usuarioConRol('estudiante'), $this->curso);
        }
        $mismo = ['sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id];
        Salon::factory()->create(['codigo' => 'OTRO-TIPO', 'sede_id' => $this->actual->sede_id]);
        Salon::factory()->create(['codigo' => 'OTRA-SEDE', 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        Salon::factory()->create(['codigo' => 'CHICO', 'capacidad' => 2, ...$mismo]);
        Salon::factory()->inactivo()->create(['codigo' => 'APAGADO', ...$mismo]);
        $ocupado = Salon::factory()->create(['codigo' => 'OCUPADO', ...$mismo]);
        $ajeno = Curso::factory()->create(['nombre' => 'Curso Secreto', 'profesor_id' => $this->usuarioConRol('profesor')->id]);
        $this->clase($ajeno, self::DIA.' 10:30', null, $ocupado);

        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->set('fechaPropuesta', self::DIA)
            ->set('horaPropuesta', '10:00')
            ->assertViewHas('salonesLibres', fn ($salones) => $salones->pluck('codigo')->all() === ['A-201', 'B-208'])
            ->assertSee('B-208')
            ->assertSee('40 plazas')
            ->assertSee('Libre')
            ->assertDontSee('OCUPADO')
            ->assertDontSee('Curso Secreto')
            ->set('horaPropuesta', '13:00')
            ->assertViewHas('salonesLibres', fn ($salones) => $salones->pluck('codigo')->all() === ['A-201', 'B-208', 'OCUPADO']);

        // Si ningún salón cumple, mensaje claro.
        $this->clase($ajeno, self::DIA.' 16:00', null, $this->actual);
        $this->clase($ajeno, self::DIA.' 16:00', null, $this->libre);
        $this->clase($ajeno, self::DIA.' 16:00', null, $ocupado);
        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->set('fechaPropuesta', self::DIA)
            ->set('horaPropuesta', '16:00')
            ->assertSee(SolicitudCambio::SIN_SALONES_LIBRES);
    }

    public function test_las_horas_van_cada_30_minutos_y_marcan_las_no_disponibles(): void
    {
        // Otra clase del mismo profesor 10:00–11:00 y los dos salones ocupados 14:00–15:00.
        $otroCurso = Curso::factory()->create(['profesor_id' => $this->escenario['profesor']->id]);
        $this->clase($otroCurso, self::DIA.' 10:00', null, null, 60);
        $ajeno = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id]);
        $this->clase($ajeno, self::DIA.' 14:00', null, $this->actual, 60);
        $this->clase($ajeno, self::DIA.' 14:00', null, $this->libre, 60);

        $modal = Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->set('fechaPropuesta', self::DIA);

        $modal->assertViewHas('horas', function (array $horas) {
            $disponibles = array_column($horas, 'disponible', 'hora');

            return array_key_first($disponibles) === '07:00' && array_key_last($disponibles) === '20:30' && count($disponibles) === 28
                && $disponibles['08:30'] && ! $disponibles['09:00'] && ! $disponibles['10:30'] && $disponibles['11:00']
                && $disponibles['12:30'] && ! $disponibles['13:00'] && ! $disponibles['14:30'] && $disponibles['15:00'];
        });
        $html = $modal->html();
        $this->assertStringContainsString('data-hora="09:30" data-disponible="no"', $html);
        $this->assertStringContainsString('data-hora="11:00" data-disponible="si"', $html);
        $this->assertStringContainsString('No disponible', $html);
        $this->assertStringNotContainsString('<select', $html);

        // Domingo: sin horas.
        $modal->set('fechaPropuesta', '2026-11-29')->assertSee('Los domingos no se reprograman clases.')->assertViewHas('horas', []);
    }

    public function test_si_cumple_todo_se_reprograma_al_instante_con_aviso_flotante(): void
    {
        $html = $this->modal()
            ->assertSee(SolicitudCambio::AYUDA_MOTIVO)
            ->assertDontSee('(opcional)')
            ->set('detalle', 'Corto')
            ->call('enviar')
            ->assertHasErrors('detalle')
            ->set('detalle', self::DETALLE)
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSet('abierto', false)
            ->assertSee('Clase reprogramada')
            ->assertDispatched('asistencia-actualizada')
            ->html();

        $this->assertMatchesRegularExpression('/data-aviso-flotante[^\n]*\n?[^\n]*\n?[^\n]*class="fixed right-4 top-4/', $html);
        $solicitud = SolicitudCambioClase::sole();
        $this->assertSame('aprobada', $solicitud->estado);
        $this->assertSame('automatica', $solicitud->origen);
        $this->assertSame($this->libre->id, $this->sesion->fresh()->salon_id);
        $this->assertSame(self::DIA.' 10:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_la_segunda_persona_que_toma_el_mismo_horario_recibe_el_error(): void
    {
        $otroProfesor = $this->usuarioConRol('profesor');
        $otroCurso = Curso::factory()->create(['profesor_id' => $otroProfesor->id, 'modalidad' => 'presencial']);
        $c302 = Salon::factory()->create(['codigo' => 'C-302', 'sede_id' => $this->actual->sede_id, 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $suya = $this->clase($otroCurso, '2026-11-24 08:00', 'Aula: C-302', $c302);

        $primero = $this->modal();
        $segundo = Livewire::actingAs($otroProfesor)->test(SolicitudCambio::class, ['curso' => $otroCurso])
            ->dispatch('abrir-solicitud-cambio', sesion: $suya->id)
            ->set('fechaPropuesta', self::DIA)->set('horaPropuesta', '10:00')->set('salonId', (string) $this->libre->id)
            ->set('motivo', 'personal')->set('detalle', self::DETALLE);

        // Cada componente actúa como su profesor (actingAs es global en la prueba).
        $this->actingAs($this->escenario['profesor']);
        $primero->call('enviar')->assertHasNoErrors()->assertSee('Clase reprogramada');
        $this->actingAs($otroProfesor);
        $segundo->call('enviar')
            ->assertHasErrors('horario')
            ->assertSee(DisponibilidadSalones::MENSAJE_HORARIO_TOMADO)
            ->assertSet('salonId', '')
            ->assertSet('abierto', true)
            ->assertViewHas('salonesLibres', fn ($salones) => ! $salones->contains('id', $this->libre->id));

        $this->assertSame(1, SolicitudCambioClase::count());
        $this->assertSame('2026-11-24 08:00', $suya->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_dictarla_en_remoto_y_los_cursos_remotos_se_aprueban_sin_salon(): void
    {
        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->set('tipo', 'remoto')->set('motivo', 'salud')->set('detalle', self::DETALLE)
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Clase reprogramada');
        $this->assertSame('remoto', $this->sesion->fresh()->modalidad);
        $this->assertSame('2026-11-23 08:00', $this->sesion->fresh()->fecha->format('Y-m-d H:i'));

        $remoto = Curso::factory()->create(['profesor_id' => $this->escenario['profesor']->id, 'modalidad' => 'remoto']);
        $clase = $this->clase($remoto, '2026-11-23 18:00', null);
        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $remoto])
            ->dispatch('abrir-solicitud-cambio', sesion: $clase->id)
            ->set('fechaPropuesta', self::DIA)
            ->assertSee('Clase remota: la nueva fecha no usa salón.')
            ->set('horaPropuesta', '18:00')
            ->assertViewHas('salonesLibres', fn ($salones) => $salones->isEmpty())
            ->set('motivo', 'otro')->set('detalle', self::DETALLE)
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Clase reprogramada');

        $solicitud = SolicitudCambioClase::where('sesion_id', $clase->id)->sole();
        $this->assertSame(['aprobada', 'remoto', null], [$solicitud->estado, $solicitud->modalidad_nueva, $clase->fresh()->salon_id]);
        $this->assertSame(self::DIA.' 18:00', $clase->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_sin_salon_asignado_avisa_y_pasa_a_revision_sin_lista_de_salones(): void
    {
        $sinSalon = $this->clase($this->curso, '2026-11-23 18:00', 'Aula: 204');

        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $sinSalon->id)
            ->assertSee(SolicitudCambio::AVISO_SIN_SALON)
            ->set('fechaPropuesta', self::DIA)
            ->assertViewHas('horas', fn (array $horas) => collect($horas)->contains('disponible', true))
            ->set('horaPropuesta', '10:00')
            ->assertDontSee('misma sede y tipo, con capacidad suficiente')
            ->set('motivo', 'institucional')->set('detalle', self::DETALLE)
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Enviada al supervisor para revisión');

        $solicitud = SolicitudCambioClase::sole();
        $this->assertSame('pendiente', $solicitud->estado);
        $this->assertSame('La clase no tiene salón asignado.', $solicitud->razon_revision);
        $this->assertSame('2026-11-23 18:00', $sinSalon->fresh()->fecha->format('Y-m-d H:i'));
    }

    public function test_sin_salon_pero_con_sede_del_curso_ofrece_solo_horas_y_salones_libres_de_esa_sede(): void
    {
        $this->curso->update(['sede_id' => $this->actual->sede_id]);
        $antigua = $this->clase($this->curso, '2026-11-23 18:00', 'Aula: 204');
        // Sin salón original no hay tipo que respetar: entra un salón de otro tipo de la misma sede, no uno de otra sede.
        $otroTipo = Salon::factory()->create(['codigo' => 'LAB-1', 'sede_id' => $this->actual->sede_id]);
        Salon::factory()->create(['codigo' => 'OTRA-SEDE', 'tipo_salon_id' => $this->actual->tipo_salon_id]);
        $ajeno = Curso::factory()->create(['profesor_id' => $this->usuarioConRol('profesor')->id]);
        foreach ([$this->actual, $this->libre, $otroTipo] as $salon) {
            $this->clase($ajeno, self::DIA.' 14:00', null, $salon, 60);
        }

        Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $antigua->id)
            ->assertDontSee(SolicitudCambio::AVISO_SIN_SALON)
            ->set('fechaPropuesta', self::DIA)
            // A las 14:00 todos los salones de la sede están ocupados: esa hora no se ofrece.
            ->assertViewHas('horas', fn (array $horas) => collect($horas)->firstWhere('hora', '14:00')['disponible'] === false
                && collect($horas)->firstWhere('hora', '10:00')['disponible'] === true)
            ->set('horaPropuesta', '10:00')
            ->assertViewHas('salonesLibres', fn ($salones) => $salones->pluck('codigo')->all() === ['A-201', 'B-208', 'LAB-1'])
            ->assertSee('de la sede del curso, con capacidad suficiente')
            ->set('salonId', (string) $this->libre->id)
            ->set('motivo', 'institucional')->set('detalle', self::DETALLE)
            ->call('enviar')
            ->assertHasNoErrors()
            ->assertSee('Clase reprogramada');

        $antigua->refresh();
        $this->assertSame(self::DIA.' 10:00', $antigua->fecha->format('Y-m-d H:i'));
        $this->assertSame($this->libre->id, $antigua->salon_id);
    }

    public function test_ver_reprogramacion_muestra_lo_pedido_y_se_responde_el_sustento_una_vez_con_archivo(): void
    {
        Notification::fake();
        $solicitud = app(SolicitudesCambioClase::class)->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['salon_id' => null]));
        app(SolicitudesCambioClase::class)->pedirSustento($this->escenario['supervisor'], $solicitud, 'Adjunta la constancia.');

        $modal = Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->assertSee('Ver reprogramación')
            ->assertSee('Sustento solicitado')
            ->assertSee('Adjunta la constancia.')
            ->assertSee('Cancelar reprogramación')
            ->set('archivoSustento', UploadedFile::fake()->create('virus.exe', 10))
            ->set('respuestaSustento', 'Adjunto la constancia.')
            ->call('responderSustento')
            ->assertHasErrors('archivoSustento')
            ->set('archivoSustento', UploadedFile::fake()->create('constancia.pdf', 10, 'application/pdf'))
            ->call('responderSustento')
            ->assertHasNoErrors()
            ->assertSee('Sustento enviado al supervisor');

        $solicitud->refresh();
        $this->assertSame('Adjunto la constancia.', $solicitud->sustento_respuesta);
        $this->assertStringStartsWith(SolicitudesCambioClase::CARPETA_SUSTENTOS.'/', $solicitud->sustento_archivo);
        Storage::disk(config('archivos.disco'))->assertExists($solicitud->sustento_archivo);
        Notification::assertSentTo($this->escenario['supervisor'], ReprogramacionSustentoRespondido::class);

        // Ya respondido: se muestra la respuesta y no hay formulario para responder otra vez.
        $modal->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->assertSee('Tu respuesta')
            ->assertSee(route('reprogramaciones.sustento', $solicitud), false)
            ->assertDontSee('Enviar respuesta')
            ->set('respuestaSustento', 'Otra vez.')
            ->call('responderSustento')
            ->assertHasErrors('solicitud');
    }

    public function test_el_estudiante_nunca_ve_el_motivo_ni_el_sustento(): void
    {
        $privado = 'Cita médica privada del docente.';
        $aplicada = app(SolicitudesCambioClase::class)->solicitar($this->escenario['profesor'], $this->sesion, $this->datos(['detalle' => $privado]));
        $this->assertSame('aprobada', $aplicada->estado);

        $otra = $this->clase($this->curso, '2026-11-24 08:00', 'Aula: A-201', $this->actual);
        $pendiente = app(SolicitudesCambioClase::class)->solicitar($this->escenario['profesor'], $otra, $this->datos(['salon_id' => null, 'detalle' => $privado]));
        app(SolicitudesCambioClase::class)->pedirSustento($this->escenario['supervisor'], $pendiente, 'Adjunta la constancia médica.');
        app(SolicitudesCambioClase::class)->responderSustento($this->escenario['profesor'], $pendiente, 'Respuesta privada del docente.', UploadedFile::fake()->create('constancia.pdf', 10));

        $estudiante = $this->escenario['estudiante'];
        $avisos = $estudiante->notifications()->pluck('data')->map(fn ($datos) => json_encode($datos, JSON_UNESCAPED_UNICODE))->join(' ');
        $this->assertStringContainsString('Clase reprogramada', $avisos);

        foreach ([$privado, 'Adjunta la constancia médica.', 'Respuesta privada del docente.', 'Salud', SolicitudesCambioClase::CARPETA_SUSTENTOS] as $texto) {
            $this->assertStringNotContainsString($texto, $avisos);
        }

        $pagina = $this->actingAs($estudiante)->get(route('cursos.show', $this->curso))->assertOk();
        $pagina->assertDontSee($privado)->assertDontSee('Respuesta privada del docente.')->assertDontSee('Adjunta la constancia médica.');
        $this->actingAs($estudiante)->get(route('reprogramaciones.sustento', $pendiente))->assertForbidden();
        Livewire::actingAs($estudiante)->test(SolicitudCambio::class, ['curso' => $this->curso])->assertForbidden();
    }

    /** Modal abierto con fecha, hora, salón libre y motivo elegidos. */
    private function modal(): mixed
    {
        return Livewire::actingAs($this->escenario['profesor'])->test(SolicitudCambio::class, ['curso' => $this->curso])
            ->dispatch('abrir-solicitud-cambio', sesion: $this->sesion->id)
            ->set('fechaPropuesta', self::DIA)
            ->set('horaPropuesta', '10:00')
            ->set('salonId', (string) $this->libre->id)
            ->set('motivo', 'salud')
            ->set('detalle', self::DETALLE);
    }

    /**
     * @param  array<string, mixed>  $cambios
     * @return array<string, mixed>
     */
    private function datos(array $cambios = []): array
    {
        return [
            'tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => self::DETALLE, 'modalidad' => 'presencial',
            'fecha_propuesta' => self::DIA.' 10:00', 'salon_id' => $this->libre->id, ...$cambios,
        ];
    }

    private function clase(Curso $curso, string $fecha, ?string $descripcion, ?Salon $salon = null, int $duracion = 90): SesionClase
    {
        return $curso->sesionesClase()->create([
            'titulo' => 'Clase', 'fecha' => $fecha, 'duracion_minutos' => $duracion,
            'numero_sesion' => $curso->sesionesClase()->count() + 1, 'descripcion' => $descripcion, 'salon_id' => $salon?->id,
        ]);
    }

    private function assertDeniega(callable $accion): void
    {
        try {
            $accion();
            $this->fail('Se esperaba un rechazo de autorización.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }
    }
}
