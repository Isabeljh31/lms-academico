<?php

namespace Tests\Feature;

use App\Livewire\Cursos\Show;
use App\Livewire\Profesor\Asistencia\EnlaceClase;
use App\Livewire\Profesor\Asistencia\Tablero;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Notifications\EnlaceRemotoFaltante;
use App\Services\Asistencia\EnlacesClaseRemota;
use App\Services\Asistencia\SolicitudesCambioClase;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Lado Profesor del enlace de clases remotas: tarjeta del detalle del curso
 * (Cursos\Show), «Enlace de esta clase» del menú ⋮ (Docente\Asistencia\EnlaceClase)
 * y aviso EnlaceRemotoFaltante al pasar una clase a remota sin enlace.
 */
class EnlaceRemotoProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const ENLACE_CURSO = 'https://meet.google.com/abc-defg-hij';

    private const ENLACE_CLASE = 'https://us02web.zoom.us/j/123456789';

    /** @return array<string, mixed> */
    private function escenarioRemoto(): array
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'remoto', 'usa_asistencia' => true]);

        return $escenario;
    }

    private function clase(Curso $curso, array $atributos = []): SesionClase
    {
        return $curso->sesionesClase()->create([
            'titulo' => 'Clase 1',
            'fecha' => now()->addWeek()->setTime(8, 0),
            'duracion_minutos' => 90,
            'numero_sesion' => 1,
            ...$atributos,
        ]);
    }

    // ----- Tarjeta del detalle del curso -----

    public function test_el_titular_guarda_el_enlace_y_la_tarjeta_muestra_solo_el_dominio(): void
    {
        $escenario = $this->escenarioRemoto();

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('Sin enlace')
            ->set('enlaceRemotoNuevo', self::ENLACE_CURSO)
            ->call('guardarEnlaceRemoto')
            ->assertHasNoErrors()
            ->assertSet('enlaceRemotoNuevo', '')
            ->assertSet('avisoEnlaceRemoto', 'Enlace de clases remotas guardado.')
            ->assertSee('Enlace guardado')
            ->assertSee('meet.google.com')
            ->assertDontSee('abc-defg-hij');

        $this->assertSame(self::ENLACE_CURSO, $escenario['curso']->fresh()->enlace_remoto);
    }

    public function test_un_co_docente_quita_el_enlace_del_curso(): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['enlace_remoto' => self::ENLACE_CURSO]);
        $coDocente = $this->usuarioConRol('profesor');
        $escenario['curso']->profesoresAdicionales()->attach($coDocente->id);

        Livewire::actingAs($coDocente)->test(Show::class, ['curso' => $escenario['curso']])
            ->call('quitarEnlaceRemoto')
            ->assertHasNoErrors()
            ->assertSee('Sin enlace');

        $this->assertNull($escenario['curso']->fresh()->enlace_remoto);
    }

    /** @return array<string, array{string}> */
    public static function rolesQueNoEditan(): array
    {
        return ['estudiante matriculado' => ['estudiante'], 'supervisor' => ['supervisor']];
    }

    #[DataProvider('rolesQueNoEditan')]
    public function test_estudiante_y_supervisor_no_pueden_guardar_ni_quitar_el_enlace(string $rol): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['estado' => 'publicado', 'enlace_remoto' => self::ENLACE_CURSO]);
        $pantalla = fn () => Livewire::actingAs($escenario[$rol])->test(Show::class, ['curso' => $escenario['curso']]);

        $pantalla()->set('enlaceRemotoNuevo', self::ENLACE_CLASE)->call('guardarEnlaceRemoto')->assertForbidden();
        $pantalla()->call('quitarEnlaceRemoto')->assertForbidden();

        $this->assertSame(self::ENLACE_CURSO, $escenario['curso']->fresh()->enlace_remoto);
    }

    public function test_un_profesor_ajeno_no_entra_al_curso(): void
    {
        $escenario = $this->escenarioRemoto();

        Livewire::actingAs($this->usuarioConRol('profesor'))->test(Show::class, ['curso' => $escenario['curso']])->assertForbidden();
    }

    public function test_un_curso_archivado_muestra_el_enlace_en_solo_lectura_y_no_lo_cambia(): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['estado' => 'archivado', 'enlace_remoto' => self::ENLACE_CURSO]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('Curso archivado: el enlace no se puede cambiar.')
            ->assertDontSeeHtml('id="enlace-remoto-nuevo"')
            ->set('enlaceRemotoNuevo', self::ENLACE_CLASE)
            ->call('guardarEnlaceRemoto')
            ->assertHasErrors('enlace_remoto');

        $this->assertSame(self::ENLACE_CURSO, $escenario['curso']->fresh()->enlace_remoto);
    }

    /** @return array<string, array{string}> */
    public static function enlacesInvalidos(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'http' => ['http://meet.google.com/abc'],
            'dominio fuera de la lista' => ['https://example.com/sala'],
        ];
    }

    #[DataProvider('enlacesInvalidos')]
    public function test_un_enlace_invalido_no_se_guarda_y_el_error_lista_los_dominios(string $enlace): void
    {
        $escenario = $this->escenarioRemoto();

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->set('enlaceRemotoNuevo', $enlace)
            ->call('guardarEnlaceRemoto')
            ->assertHasErrors('enlace_remoto')
            ->assertSee('teams.microsoft.com, teams.live.com, meet.google.com');

        $this->assertNull($escenario['curso']->fresh()->enlace_remoto);
    }

    public function test_guardar_vacio_no_borra_el_enlace(): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['enlace_remoto' => self::ENLACE_CURSO]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->set('enlaceRemotoNuevo', '  ')
            ->call('guardarEnlaceRemoto')
            ->assertHasErrors('enlace_remoto');

        $this->assertSame(self::ENLACE_CURSO, $escenario['curso']->fresh()->enlace_remoto);
    }

    public function test_la_tarjeta_aparece_solo_si_el_curso_tiene_clases_remotas(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'presencial']);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertDontSee('Enlace de clases remotas');

        $this->clase($escenario['curso'], ['modalidad' => 'remoto']);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('Enlace de clases remotas');
    }

    /** @return array<string, array{string}> */
    public static function rolesConOtraVista(): array
    {
        return ['estudiante matriculado' => ['estudiante'], 'supervisor' => ['supervisor']];
    }

    #[DataProvider('rolesConOtraVista')]
    public function test_la_tarjeta_y_el_enlace_no_llegan_a_la_vista_ni_al_estado_de_otros_roles(string $rol): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['estado' => 'publicado', 'enlace_remoto' => self::ENLACE_CURSO]);

        $this->actingAs($escenario[$rol])->get(route('cursos.show', $escenario['curso']))
            ->assertOk()
            ->assertDontSee('Enlace de clases remotas')
            ->assertDontSee('meet.google.com');
        $componente = Livewire::actingAs($escenario[$rol])->test(Show::class, ['curso' => $escenario['curso']]);
        $this->assertStringNotContainsString('meet.google.com', json_encode($componente->snapshot));
    }

    public function test_el_enlace_guardado_no_viaja_en_el_estado_del_componente_del_profesor(): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['enlace_remoto' => self::ENLACE_CURSO]);

        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']]);

        $this->assertStringNotContainsString('abc-defg-hij', json_encode($componente->snapshot));
    }

    // ----- «Enlace de esta clase» (menú ⋮) -----

    public function test_la_clase_hereda_el_enlace_del_curso_tiene_uno_propio_y_vuelve_a_heredar(): void
    {
        $escenario = $this->escenarioRemoto();
        $escenario['curso']->update(['enlace_remoto' => self::ENLACE_CURSO]);
        $sesion = $this->clase($escenario['curso']);
        $modal = Livewire::actingAs($escenario['profesor'])->test(EnlaceClase::class, ['curso' => $escenario['curso']]);

        $modal->dispatch('abrir-enlace-clase', sesion: $sesion->id)
            ->assertSee('Esta clase hereda el enlace del curso')
            ->assertSee('meet.google.com')
            ->set('enlaceNuevo', self::ENLACE_CLASE)
            ->call('guardar')
            ->assertHasNoErrors()
            ->assertSet('aviso', 'Enlace de la clase guardado.');
        $this->assertSame(self::ENLACE_CLASE, $sesion->fresh()->enlace_remoto);

        $modal->dispatch('abrir-enlace-clase', sesion: $sesion->id)
            ->assertSee('Esta clase tiene un enlace propio')
            ->assertSee('us02web.zoom.us')
            ->call('heredar')
            ->assertSet('aviso', 'La clase vuelve a usar el enlace del curso.');
        $this->assertNull($sesion->fresh()->enlace_remoto);
    }

    public function test_guardar_vacio_en_la_clase_no_quita_su_enlace(): void
    {
        $escenario = $this->escenarioRemoto();
        $sesion = $this->clase($escenario['curso'], ['enlace_remoto' => self::ENLACE_CLASE]);

        Livewire::actingAs($escenario['profesor'])->test(EnlaceClase::class, ['curso' => $escenario['curso']])
            ->dispatch('abrir-enlace-clase', sesion: $sesion->id)
            ->set('enlaceNuevo', '')
            ->call('guardar')
            ->assertHasErrors('enlace_remoto');

        $this->assertSame(self::ENLACE_CLASE, $sesion->fresh()->enlace_remoto);
    }

    public function test_el_modal_no_abre_una_clase_de_otro_curso(): void
    {
        $escenario = $this->escenarioRemoto();
        $ajena = $this->clase(Curso::factory()->create(['modalidad' => 'remoto']));
        $modal = Livewire::actingAs($escenario['profesor'])->test(EnlaceClase::class, ['curso' => $escenario['curso']]);

        $this->expectException(ModelNotFoundException::class);

        $modal->dispatch('abrir-enlace-clase', sesion: $ajena->id);
    }

    /** @return array<string, array{string}> */
    public static function usuariosSinModal(): array
    {
        return ['estudiante matriculado' => ['estudiante'], 'supervisor' => ['supervisor'], 'profesor ajeno' => ['ajeno']];
    }

    #[DataProvider('usuariosSinModal')]
    public function test_solo_los_docentes_del_curso_montan_el_modal_de_la_clase(string $quien): void
    {
        $escenario = $this->escenarioRemoto();
        $usuario = $quien === 'ajeno' ? $this->usuarioConRol('profesor') : $escenario[$quien];

        Livewire::actingAs($usuario)->test(EnlaceClase::class, ['curso' => $escenario['curso']])->assertForbidden();
    }

    public function test_el_menu_ofrece_el_enlace_solo_en_clases_remotas_que_no_terminaron_aunque_tengan_lista(): void
    {
        $escenario = $this->escenarioRemoto();
        $conLista = $this->clase($escenario['curso'], ['titulo' => 'Remota con lista', 'fecha' => now()->subMinutes(30)]);
        $conLista->asistencias()->create(['estudiante_id' => $escenario['estudiante']->id, 'estado' => 'presente']);
        $this->clase($escenario['curso'], ['titulo' => 'Presencial futura', 'modalidad' => 'presencial', 'numero_sesion' => 2]);
        $this->clase($escenario['curso'], ['titulo' => 'Remota pasada', 'fecha' => now()->subDays(2), 'numero_sesion' => 3]);

        $tablero = Livewire::actingAs($escenario['profesor'])->test(Tablero::class, ['curso' => $escenario['curso']]);

        $tablero->assertSeeHtml("Livewire.dispatch('abrir-enlace-clase', { sesion: {$conLista->id} })");
        $this->assertSame(1, substr_count($tablero->html(), 'abrir-enlace-clase'));
    }

    // ----- Aviso EnlaceRemotoFaltante -----

    public function test_dictarla_en_remoto_sin_enlace_avisa_a_todos_los_docentes(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'presencial']);
        $coDocente = $this->usuarioConRol('profesor');
        $escenario['curso']->profesoresAdicionales()->attach($coDocente->id);
        $sesion = $this->clase($escenario['curso']);

        $solicitud = app(SolicitudesCambioClase::class)->solicitar($escenario['profesor'], $sesion, ['tipo' => 'remoto', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.']);

        $this->assertSame('aprobada', $solicitud->estado);
        Notification::assertSentTo([$escenario['profesor'], $coDocente], EnlaceRemotoFaltante::class,
            fn (EnlaceRemotoFaltante $aviso, array $canales, object $docente) => $canales === ['database']
                && $aviso->toArray($docente)['url'] === route('cursos.show', $escenario['curso']));
        Notification::assertNotSentTo($escenario['estudiante'], EnlaceRemotoFaltante::class);
    }

    public function test_si_el_curso_ya_tiene_enlace_no_se_avisa(): void
    {
        Notification::fake();
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'presencial', 'enlace_remoto' => self::ENLACE_CURSO]);
        $sesion = $this->clase($escenario['curso']);

        app(SolicitudesCambioClase::class)->solicitar($escenario['profesor'], $sesion, ['tipo' => 'remoto', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.']);

        Notification::assertNotSentTo($escenario['profesor'], EnlaceRemotoFaltante::class);
    }

    public function test_la_aprobacion_del_supervisor_a_remoto_sin_enlace_avisa_al_profesor(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'presencial']);
        $sesion = $this->clase($escenario['curso']);
        $servicio = app(SolicitudesCambioClase::class);
        $solicitud = $servicio->solicitar($escenario['profesor'], $sesion, ['tipo' => 'reprogramar', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.', 'modalidad' => 'presencial']);
        Notification::fake();

        $servicio->aprobar($escenario['supervisor'], $solicitud, [
            'fecha' => now()->addDays(9)->format('Y-m-d'), 'hora_inicio' => '08:00', 'hora_fin' => '09:30', 'modalidad' => 'remoto',
        ]);

        Notification::assertSentTo($escenario['profesor'], EnlaceRemotoFaltante::class);
    }

    public function test_el_aviso_no_se_repite_para_la_misma_clase(): void
    {
        $escenario = $this->escenarioRemoto();
        $sesion = $this->clase($escenario['curso']);
        $enlaces = app(EnlacesClaseRemota::class);

        $enlaces->avisarSiFaltaEnlace($sesion);
        $enlaces->avisarSiFaltaEnlace($sesion);
        $enlaces->avisarSiFaltaEnlace($this->clase($escenario['curso'], ['numero_sesion' => 2]));

        $this->assertSame(2, $escenario['profesor']->notifications()->where('type', EnlaceRemotoFaltante::class)->count());
    }

    public function test_ninguna_notificacion_guarda_el_enlace_de_la_reunion(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'presencial', 'enlace_remoto' => self::ENLACE_CURSO]);
        $sesion = $this->clase($escenario['curso']);
        $sinEnlace = $this->escenarioSinEnlace();

        app(SolicitudesCambioClase::class)->solicitar($escenario['profesor'], $sesion, ['tipo' => 'remoto', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.']);
        app(SolicitudesCambioClase::class)->solicitar($sinEnlace['profesor'], $sinEnlace['sesion'], ['tipo' => 'remoto', 'motivo' => 'personal', 'detalle' => 'Motivo general de prueba.']);

        $datos = DB::table('notifications')->pluck('data');
        $this->assertNotEmpty($datos);
        $this->assertFalse($datos->contains(fn (string $data) => str_contains($data, 'meet.google.com') || str_contains($data, 'zoom.us')));
    }

    /** Segundo curso remoto (por reprogramación) sin enlace, para que también se envíe EnlaceRemotoFaltante. */
    private function escenarioSinEnlace(): array
    {
        $profesor = $this->usuarioConRol('profesor');
        $curso = Curso::factory()->create(['profesor_id' => $profesor->id, 'modalidad' => 'presencial']);

        return ['profesor' => $profesor, 'sesion' => $this->clase($curso)];
    }
}
