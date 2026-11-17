<?php

namespace Tests\Feature;

use App\Models\SesionClase;
use App\Services\Asistencia\SolicitudesCambioClase;
use App\Services\Reportes\AsistenciaProfesor;
use App\Services\Reportes\DetalleCursoProfesor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Aula de una clase: se lee de la línea «Aula: X» de su descripción (sola o
 * con más texto) y la próxima clase del detalle del curso la prefiere al horario.
 */
class AulaDeClaseTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_lee_el_aula_de_una_descripcion_exacta_o_de_una_linea_entre_mas_texto(): void
    {
        $this->assertSame('A-201', AsistenciaProfesor::aula(new SesionClase(['descripcion' => 'Aula: A-201'])));
        $this->assertSame('B-12', AsistenciaProfesor::aula(new SesionClase(['descripcion' => "Traer laptop.\nAula: B-12\nLaboratorio 2"])));
        $this->assertNull(AsistenciaProfesor::aula(new SesionClase(['descripcion' => 'Repaso del aula: capítulo 3'])));
        $this->assertNull(AsistenciaProfesor::aula(new SesionClase(['descripcion' => null])));
    }

    public function test_cambiar_el_aula_conserva_el_resto_de_la_descripcion(): void
    {
        $this->assertSame("Aula: C-3\nTraer laptop.", SolicitudesCambioClase::descripcionConAula("Aula: A-201\nTraer laptop.", 'C-3'));
        $this->assertSame("Aula: C-3\nTraer laptop.", SolicitudesCambioClase::descripcionConAula('Traer laptop.', 'C-3'));
        $this->assertSame('Traer laptop.', SolicitudesCambioClase::descripcionConAula("Aula: A-201\nTraer laptop.", null));
        $this->assertNull(SolicitudesCambioClase::descripcionConAula('Aula: A-201', null));
    }

    public function test_la_proxima_clase_prefiere_el_aula_de_la_descripcion_al_horario(): void
    {
        $escenario = $this->escenario();
        $curso = $escenario['curso'];
        $curso->update(['horario_bloques' => [['dia' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '10:00', 'aula' => 'A-201']]]);
        $lunes = now()->next('Monday')->setTime(8, 0);
        $sesion = $curso->sesionesClase()->create(['titulo' => 'Clase', 'fecha' => $lunes, 'duracion_minutos' => 120, 'numero_sesion' => 1]);
        $detalle = app(DetalleCursoProfesor::class);

        $this->assertSame('Aula A-201', $detalle->resumen($curso->fresh(), [])['proxima']['aula']);

        $sesion->update(['descripcion' => "Aula: B-12\nTraer laptop."]);
        $this->assertSame('Aula B-12', $detalle->resumen($curso->fresh(), [])['proxima']['aula']);
    }
}
