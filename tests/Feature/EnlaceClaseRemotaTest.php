<?php

namespace Tests\Feature;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Services\Asistencia\EnlacesClaseRemota;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Enlace de reunión de las clases remotas (EnlacesClaseRemota): validación
 * con lista blanca, resolución clase → curso, ventana de «Unirse» y guardado
 * por los docentes del curso con registro en la bitácora sin la URL.
 */
class EnlaceClaseRemotaTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    private const ENLACE_CURSO = 'https://meet.google.com/abc-defg-hij';

    private const ENLACE_CLASE = 'https://us02web.zoom.us/j/123456789?pwd=secreto';

    protected function setUp(): void
    {
        parent::setUp();
        $this->crearRoles();
    }

    private function servicio(): EnlacesClaseRemota
    {
        return app(EnlacesClaseRemota::class);
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

    /** @return array<string, array{string}> */
    public static function enlacesAceptados(): array
    {
        return [
            'Teams' => ['https://teams.microsoft.com/l/meetup-join/19%3ameeting_x'],
            'Meet' => ['https://meet.google.com/abc-defg-hij'],
            'subdominio de Zoom' => ['https://us02web.zoom.us/j/123456789'],
            'Jitsi' => ['https://meet.jit.si/ClaseQuimica'],
            'host en mayúsculas' => ['https://MEET.GOOGLE.COM/abc-defg-hij'],
            'puerto https explícito' => ['https://zoom.us:443/j/1'],
        ];
    }

    #[DataProvider('enlacesAceptados')]
    public function test_acepta_enlaces_https_de_dominios_permitidos(string $enlace): void
    {
        $this->assertSame($enlace, $this->servicio()->validar("  {$enlace}  "));
    }

    /** @return array<string, array{string}> */
    public static function enlacesRechazados(): array
    {
        return [
            'http' => ['http://meet.google.com/abc-defg-hij'],
            'javascript' => ['javascript:alert(1)'],
            'data' => ['data:text/html,<script>alert(1)</script>'],
            'credenciales' => ['https://usuario:clave@meet.google.com/abc'],
            'dominio fuera de la lista' => ['https://example.com/reunion'],
            'sufijo engañoso' => ['https://zoom.us.evil.com/j/1'],
            'dominio que solo termina igual' => ['https://evilzoom.us/j/1'],
            'IP' => ['https://142.250.0.1/abc'],
            'otro puerto' => ['https://meet.google.com:8443/abc'],
            'barra invertida' => ['https://meet.google.com\\@evil.com/'],
            'espacios internos' => ['https://meet.google.com/abc def'],
            'sin esquema' => ['meet.google.com/abc-defg-hij'],
            'demasiado largo' => ['https://meet.google.com/'.str_repeat('a', 480)],
        ];
    }

    #[DataProvider('enlacesRechazados')]
    public function test_rechaza_enlaces_inseguros_o_fuera_de_la_lista(string $enlace): void
    {
        $this->expectException(ValidationException::class);

        $this->servicio()->validar($enlace);
    }

    public function test_un_enlace_vacio_equivale_a_quitarlo(): void
    {
        $this->assertNull($this->servicio()->validar('   '));
    }

    public function test_la_lista_de_dominios_sale_de_la_configuracion(): void
    {
        app(ConfiguracionPlataforma::class)->guardar(['enlace_remoto_dominios' => [' Reuniones.Ejemplo.EDU ']]);

        $this->assertTrue($this->servicio()->esValido('https://aula.reuniones.ejemplo.edu/sala'));
        $this->assertFalse($this->servicio()->esValido(self::ENLACE_CURSO));
    }

    public function test_el_enlace_de_la_clase_prevalece_sobre_el_del_curso(): void
    {
        $curso = Curso::factory()->create(['modalidad' => 'remoto', 'enlace_remoto' => self::ENLACE_CURSO]);
        $propia = $this->clase($curso, ['enlace_remoto' => self::ENLACE_CLASE]);
        $heredada = $this->clase($curso, ['numero_sesion' => 2]);

        $this->assertSame(self::ENLACE_CLASE, $this->servicio()->resolver($propia));
        $this->assertSame(self::ENLACE_CURSO, $this->servicio()->resolver($heredada));
    }

    public function test_sin_enlace_o_con_un_enlace_guardado_que_ya_no_es_valido_no_hay_enlace(): void
    {
        $sinEnlace = $this->clase(Curso::factory()->create(['modalidad' => 'remoto']));
        $invalido = $this->clase(Curso::factory()->create(['modalidad' => 'remoto', 'enlace_remoto' => 'javascript:alert(1)']));

        $this->assertNull($this->servicio()->resolver($sinEnlace));
        $this->assertNull($this->servicio()->resolver($invalido));
    }

    public function test_solo_la_modalidad_efectiva_remota_es_clase_en_linea(): void
    {
        $remoto = Curso::factory()->create(['modalidad' => 'remoto']);
        $presencial = Curso::factory()->create(['modalidad' => 'presencial']);
        $virtual = Curso::factory()->create(['modalidad' => 'virtual']);

        $this->assertTrue($this->servicio()->esRemota($this->clase($remoto)));
        $this->assertTrue($this->servicio()->esRemota($this->clase($presencial, ['modalidad' => 'remoto'])));
        $this->assertFalse($this->servicio()->esRemota($this->clase($remoto, ['modalidad' => 'presencial'])));
        $this->assertFalse($this->servicio()->esRemota($this->clase($virtual)));
    }

    public function test_la_ventana_abre_15_minutos_antes_y_cierra_al_terminar_la_clase(): void
    {
        $sesion = $this->clase(Curso::factory()->create(['modalidad' => 'remoto']), ['fecha' => '2026-12-01 08:00:00', 'duracion_minutos' => 90]);

        $this->assertSame('antes', $this->servicio()->ventana($sesion, now()->parse('2026-12-01 07:44:59')));
        $this->assertSame('abierta', $this->servicio()->ventana($sesion, now()->parse('2026-12-01 07:45:00')));
        $this->assertSame('abierta', $this->servicio()->ventana($sesion, now()->parse('2026-12-01 09:30:00')));
        $this->assertSame('terminada', $this->servicio()->ventana($sesion, now()->parse('2026-12-01 09:30:01')));
    }

    public function test_el_enlace_para_unirse_solo_se_entrega_en_la_ventana_de_una_clase_remota(): void
    {
        $remota = $this->clase(Curso::factory()->create(['modalidad' => 'remoto', 'enlace_remoto' => self::ENLACE_CURSO]), ['fecha' => '2026-12-01 08:00:00']);
        $presencial = $this->clase(Curso::factory()->create(['modalidad' => 'presencial', 'enlace_remoto' => self::ENLACE_CURSO]), ['fecha' => '2026-12-01 08:00:00']);
        $this->travelTo('2026-12-01 07:50:00');

        $this->assertSame(self::ENLACE_CURSO, $this->servicio()->enlaceParaUnirse($remota));
        $this->assertNull($this->servicio()->enlaceParaUnirse($presencial));
        $this->assertNull($this->servicio()->enlaceParaUnirse($remota, now()->parse('2026-12-01 07:00:00')));
    }

    public function test_el_profesor_titular_guarda_el_enlace_y_la_bitacora_no_guarda_la_url(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];

        $this->servicio()->guardarDelCurso($escenario['profesor'], $curso, ' '.self::ENLACE_CLASE.' ');

        $this->assertSame(self::ENLACE_CLASE, $curso->fresh()->enlace_remoto);
        $registro = BitacoraAuditoria::where('tipo_evento', EnlacesClaseRemota::TIPO_BITACORA)->sole();
        $this->assertSame($escenario['profesor']->id, $registro->ejecutado_por);
        $this->assertSame($curso->id, $registro->curso_id);
        $this->assertSame('Enlace de clases remotas del curso agregado.', $registro->descripcion);
        $this->assertNull($registro->valor_anterior);
        $this->assertSame('{"dominio":"us02web.zoom.us"}', $registro->valor_nuevo);
    }

    public function test_un_co_docente_puede_quitar_el_enlace_del_curso(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $curso->update(['enlace_remoto' => self::ENLACE_CURSO]);
        $coDocente = $this->usuarioConRol('profesor');
        $curso->profesoresAdicionales()->attach($coDocente->id);

        $this->servicio()->guardarDelCurso($coDocente, $curso, '');

        $this->assertNull($curso->fresh()->enlace_remoto);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto', 'descripcion' => 'Enlace de clases remotas del curso eliminado.']);
    }

    /** @return array<string, array{string}> */
    public static function usuariosSinPermiso(): array
    {
        return [
            'supervisor' => ['supervisor'],
            'estudiante matriculado' => ['estudiante'],
            'profesor ajeno' => ['ajeno'],
        ];
    }

    #[DataProvider('usuariosSinPermiso')]
    public function test_solo_los_docentes_del_curso_pueden_cambiar_el_enlace(string $quien): void
    {
        $escenario = $this->escenario();
        $usuario = $quien === 'ajeno' ? $this->usuarioConRol('profesor') : $escenario[$quien];

        try {
            $this->servicio()->guardarDelCurso($usuario, $escenario['curso'], self::ENLACE_CURSO);
            $this->fail('Se esperaba AuthorizationException.');
        } catch (AuthorizationException) {
        }

        $this->assertNull($escenario['curso']->fresh()->enlace_remoto);
        $this->assertDatabaseMissing('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto']);
    }

    public function test_un_enlace_invalido_no_se_guarda_ni_se_registra(): void
    {
        $escenario = $this->escenario();

        try {
            $this->servicio()->guardarDelCurso($escenario['profesor'], $escenario['curso'], 'http://meet.google.com/abc');
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enlace_remoto', $e->errors());
        }

        $this->assertNull($escenario['curso']->fresh()->enlace_remoto);
        $this->assertDatabaseMissing('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto']);
    }

    public function test_un_curso_archivado_no_admite_cambios_del_enlace(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['estado' => 'archivado']);

        $this->expectException(ValidationException::class);

        $this->servicio()->guardarDelCurso($escenario['profesor'], $escenario['curso'], self::ENLACE_CURSO);
    }

    public function test_guardar_el_mismo_enlace_no_genera_otro_registro(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['enlace_remoto' => self::ENLACE_CURSO]);

        $this->servicio()->guardarDelCurso($escenario['profesor'], $escenario['curso'], self::ENLACE_CURSO);

        $this->assertDatabaseMissing('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto']);
    }

    public function test_el_profesor_pone_un_enlace_propio_a_una_clase_remota_futura(): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso'], ['modalidad' => 'remoto']);

        $this->servicio()->guardarDeClase($escenario['profesor'], $sesion, self::ENLACE_CLASE);

        $this->assertSame(self::ENLACE_CLASE, $sesion->fresh()->enlace_remoto);
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto', 'descripcion' => "Enlace de la clase {$sesion->id} agregado."]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function clasesSinEnlacePropio(): array
    {
        return [
            'clase presencial' => [['modalidad' => 'presencial']],
            'clase remota ya terminada' => [['modalidad' => 'remoto', 'fecha' => '2026-01-01 08:00:00']],
        ];
    }

    #[DataProvider('clasesSinEnlacePropio')]
    public function test_no_se_pone_enlace_propio_a_una_clase_no_remota_o_terminada(array $atributos): void
    {
        $escenario = $this->escenario();
        $sesion = $this->clase($escenario['curso'], $atributos);

        try {
            $this->servicio()->guardarDeClase($escenario['profesor'], $sesion, self::ENLACE_CLASE);
            $this->fail('Se esperaba ValidationException.');
        } catch (ValidationException) {
        }

        $this->assertNull($sesion->fresh()->enlace_remoto);
        $this->assertDatabaseMissing('bitacoras_auditoria', ['tipo_evento' => 'enlace_remoto']);
    }

    public function test_el_curso_clonado_no_hereda_el_enlace_y_el_original_lo_conserva(): void
    {
        $escenario = $this->escenario();
        $escenario['curso']->update(['modalidad' => 'remoto', 'enlace_remoto' => self::ENLACE_CURSO]);

        $clon = $escenario['curso']->clonar('Curso de prueba (nuevo ciclo)', $this->usuarioConRol('profesor')->id);

        $this->assertNotSame($escenario['curso']->id, $clon->id);
        $this->assertSame('remoto', $clon->fresh()->modalidad);
        $this->assertNull($clon->fresh()->enlace_remoto);
        $this->assertSame(self::ENLACE_CURSO, $escenario['curso']->fresh()->enlace_remoto);
    }
}
