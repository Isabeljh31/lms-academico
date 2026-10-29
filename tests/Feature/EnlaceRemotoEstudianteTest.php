<?php

namespace Tests\Feature;

use App\Livewire\Admin\SolicitudesCambioClase\Index as BandejaSupervisor;
use App\Livewire\Compartido\Calendario\Index as Calendario;
use App\Models\SesionClase;
use App\Models\SolicitudCambioClase;
use App\Models\User;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Lado Estudiante y Supervisor del enlace de clases remotas: botón «Unirse a
 * la clase» en la agenda «Tus próximas clases» del calendario y aviso no
 * bloqueante del Supervisor al aprobar una reprogramación remota sin enlace.
 */
class EnlaceRemotoEstudianteTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const ENLACE_CURSO = 'https://meet.google.com/abc-defg-hij';

    private const ENLACE_CLASE = 'https://us02web.zoom.us/j/123456789';

    private const AVISO_SIN_ENLACE = 'El curso aún no tiene enlace de clases remotas; el profesor recibirá un aviso.';

    /** Inicio de la clase de prueba: 10:00 del día fijado. */
    private const INICIO = '2026-11-18 10:00:00';

    /** @var array<string, mixed> */
    private array $escenario;

    private SesionClase $sesion;

    protected function setUp(): void
    {
        parent::setUp();
        $this->escenario = $this->escenario();
        $this->escenario['curso']->update(['modalidad' => 'remoto', 'usa_asistencia' => true, 'estado' => 'publicado', 'enlace_remoto' => self::ENLACE_CURSO]);
        $this->sesion = $this->escenario['curso']->sesionesClase()->create([
            'titulo' => 'Clase en vivo', 'fecha' => self::INICIO, 'duracion_minutos' => 90, 'numero_sesion' => 1,
        ]);
    }

    private function agendaDe(User $usuario, string $ahora): Testable
    {
        $this->travelTo(Carbon::parse($ahora));

        return Livewire::actingAs($usuario)->test(Calendario::class);
    }

    // ----- Botón «Unirse a la clase» -----

    /** @return array<string, array{string}> */
    public static function momentosDentroDeLaVentana(): array
    {
        return [
            '15 minutos antes' => ['2026-11-18 09:45:00'],
            'en curso' => ['2026-11-18 10:30:00'],
            'al terminar' => ['2026-11-18 11:30:00'],
        ];
    }

    #[DataProvider('momentosDentroDeLaVentana')]
    public function test_el_boton_aparece_dentro_de_la_ventana_y_abre_en_otra_pestana(string $ahora): void
    {
        $this->agendaDe($this->escenario['estudiante'], $ahora)
            ->assertSee('Unirse a la clase')
            ->assertSeeHtml('href="'.self::ENLACE_CURSO.'" target="_blank" rel="noopener noreferrer"')
            ->assertDontSee(self::ENLACE_CURSO.'</', false);
    }

    public function test_antes_de_la_ventana_avisa_cuando_estara_disponible_sin_la_url(): void
    {
        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 09:44:00')
            ->assertDontSee('Unirse a la clase')
            ->assertSee('El enlace estará disponible 15 minutos antes (09:45)')
            ->assertDontSee('abc-defg-hij');
    }

    public function test_al_pasar_el_fin_de_la_clase_no_muestra_el_boton_ni_la_url(): void
    {
        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 11:31:00')
            ->assertSee('Clase en vivo')
            ->assertDontSee('Unirse a la clase')
            ->assertDontSee('El enlace estará disponible')
            ->assertDontSee('abc-defg-hij');
    }

    public function test_el_enlace_propio_de_la_clase_tiene_prioridad_sobre_el_del_curso(): void
    {
        $this->sesion->update(['enlace_remoto' => self::ENLACE_CLASE]);

        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00')
            ->assertSeeHtml('href="'.self::ENLACE_CLASE.'"')
            ->assertDontSee('abc-defg-hij');
    }

    public function test_si_el_curso_no_tiene_enlace_avisa_que_el_profesor_no_lo_publico(): void
    {
        $this->escenario['curso']->update(['enlace_remoto' => null]);

        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00')
            ->assertSee('El profesor aún no publicó el enlace')
            ->assertDontSee('Unirse a la clase');
    }

    public function test_un_enlace_guardado_que_ya_no_es_valido_no_se_ofrece(): void
    {
        DB::table('cursos')->where('id', $this->escenario['curso']->id)->update(['enlace_remoto' => 'javascript:alert(1)']);

        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00')
            ->assertSee('El profesor aún no publicó el enlace')
            ->assertDontSeeHtml('javascript:alert');
    }

    public function test_una_clase_presencial_no_muestra_nada_del_enlace(): void
    {
        $this->escenario['curso']->update(['modalidad' => 'presencial']);

        $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00')
            ->assertSee('Clase en vivo')
            ->assertDontSee('Unirse a la clase')
            ->assertDontSee('El profesor aún no publicó el enlace')
            ->assertDontSee('abc-defg-hij');
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function matriculasSinAcceso(): array
    {
        return [
            'matrícula retirada' => [['estado' => 'retirado']],
            'matrícula desactivada localmente' => [['activo_local' => false]],
        ];
    }

    #[DataProvider('matriculasSinAcceso')]
    public function test_un_estudiante_con_matricula_inactiva_no_ve_el_boton_ni_la_url(array $matricula): void
    {
        $estudiante = $this->usuarioConRol('estudiante');
        $this->matricular($estudiante, $this->escenario['curso'], $matricula);

        $this->agendaDe($estudiante, '2026-11-18 10:00:00')
            ->assertDontSee('Clase en vivo')
            ->assertDontSee('Unirse a la clase')
            ->assertDontSee('abc-defg-hij');
    }

    public function test_otro_estudiante_no_matriculado_no_ve_el_boton_ni_la_url(): void
    {
        $this->agendaDe($this->usuarioConRol('estudiante'), '2026-11-18 10:00:00')
            ->assertDontSee('Unirse a la clase')
            ->assertDontSee('abc-defg-hij');
    }

    public function test_la_url_no_queda_en_el_estado_de_livewire(): void
    {
        $agenda = $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00')->assertSee('Unirse a la clase');

        $this->assertStringNotContainsString('abc-defg-hij', json_encode($agenda->snapshot));
    }

    public function test_el_boton_no_agrega_consultas_por_clase(): void
    {
        $consultas = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->agendaDe($this->escenario['estudiante'], '2026-11-18 10:00:00');
            DB::disableQueryLog();

            return count(DB::getQueryLog());
        };

        $consultas(); // Calentamiento: configuración y permisos se leen una sola vez por proceso.
        $conUnaClase = $consultas();
        foreach (range(2, 6) as $numero) {
            $this->escenario['curso']->sesionesClase()->create([
                'titulo' => "Clase {$numero}", 'fecha' => Carbon::parse(self::INICIO)->addDays($numero - 1), 'duracion_minutos' => 90,
                'numero_sesion' => $numero, 'enlace_remoto' => $numero % 2 ? self::ENLACE_CLASE : null,
            ]);
        }

        $this->assertSame($conUnaClase, $consultas());
    }

    // ----- Aviso del Supervisor -----

    private function solicitudPendiente(): SolicitudCambioClase
    {
        $this->travelTo(Carbon::parse('2026-11-11 10:00:00'));
        $this->escenario['curso']->update(['modalidad' => 'presencial', 'fecha_inicio' => '2026-10-19', 'semanas_duracion' => 16]);

        return app(SolicitudesCambioClase::class)->solicitar($this->escenario['profesor'], $this->sesion, [
            'tipo' => 'reprogramar', 'motivo' => 'salud', 'detalle' => 'Cita médica', 'modalidad' => 'presencial',
        ]);
    }

    public function test_el_supervisor_ve_un_aviso_no_bloqueante_si_aprueba_a_remoto_sin_enlace(): void
    {
        $this->escenario['curso']->update(['enlace_remoto' => null]);
        $solicitud = $this->solicitudPendiente();

        $bandeja = Livewire::actingAs($this->escenario['supervisor'])->test(BandejaSupervisor::class)
            ->call('seleccionar', $solicitud->id)
            ->set('fecha', '2026-11-20')->set('horaInicio', '08:00')->set('horaFin', '09:30')
            ->assertDontSee(self::AVISO_SIN_ENLACE)
            ->set('modalidad', 'remoto')
            ->assertSee(self::AVISO_SIN_ENLACE)
            ->call('aprobar')
            ->assertHasNoErrors();

        $this->assertSame('aprobada', $solicitud->fresh()->estado);
        $this->assertSame('remoto', $this->sesion->fresh()->modalidad);
        $bandeja->assertDontSee('enlace_remoto');
    }

    /** @return array<string, array{?string, ?string}> */
    public static function enlacesQueEvitanElAviso(): array
    {
        return [
            'enlace del curso' => [self::ENLACE_CURSO, null],
            'enlace propio de la clase' => [null, self::ENLACE_CLASE],
        ];
    }

    #[DataProvider('enlacesQueEvitanElAviso')]
    public function test_no_hay_aviso_si_la_clase_ya_tiene_enlace(?string $delCurso, ?string $deLaClase): void
    {
        $this->escenario['curso']->update(['enlace_remoto' => $delCurso]);
        $this->sesion->update(['enlace_remoto' => $deLaClase]);
        $solicitud = $this->solicitudPendiente();

        Livewire::actingAs($this->escenario['supervisor'])->test(BandejaSupervisor::class)
            ->call('seleccionar', $solicitud->id)
            ->set('fecha', '2026-11-20')->set('horaInicio', '08:00')->set('horaFin', '09:30')
            ->set('modalidad', 'remoto')
            ->assertDontSee(self::AVISO_SIN_ENLACE)
            ->assertDontSee('abc-defg-hij')
            ->assertDontSee('123456789');
    }

    public function test_ninguna_notificacion_del_flujo_contiene_la_url(): void
    {
        $solicitud = $this->solicitudPendiente();
        app(SolicitudesCambioClase::class)->aprobar($this->escenario['supervisor'], $solicitud, [
            'fecha' => '2026-11-20', 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'modalidad' => 'remoto',
        ]);
        $this->sesion->update(['enlace_remoto' => self::ENLACE_CLASE]);

        $datos = DB::table('notifications')->pluck('data')->implode("\n");

        $this->assertNotSame('', $datos);
        $this->assertStringNotContainsString('meet.google.com', $datos);
        $this->assertStringNotContainsString('zoom.us', $datos);
    }
}
