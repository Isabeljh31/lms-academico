<?php

namespace Tests\Feature;

use App\Livewire\Supervisor\Configuracion;
use App\Notifications\NuevaTareaAsignada;
use App\Services\ConfiguracionPlataforma;
use App\Services\HojasCalculo\LibroXlsx;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;
use ZipArchive;

/**
 * ERS RF-108: la zona horaria y el formato de fecha elegidos en
 * Configuración se aplican en toda la plataforma.
 */
class ConfiguracionRegionalTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_la_zona_horaria_guardada_se_aplica_a_la_plataforma(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(Configuracion::class)
            ->set('zonaHoraria', 'Europe/Madrid')
            ->call('guardar')
            ->assertHasNoErrors();

        $this->assertSame('Europe/Madrid', config('app.timezone'));
        $this->assertSame('Europe/Madrid', now()->getTimezone()->getName());
        $this->assertDatabaseHas('bitacoras_auditoria', ['tipo_evento' => 'configuracion']);

        date_default_timezone_set('UTC');
        (new ConfiguracionPlataforma)->aplicarZonaHoraria();

        $this->assertSame('Europe/Madrid', date_default_timezone_get(), 'cada solicitud nueva vuelve a aplicar la zona guardada');
    }

    public function test_los_plazos_se_comparan_con_la_hora_de_la_zona_configurada(): void
    {
        $escenario = $this->escenario();
        app(ConfiguracionPlataforma::class)->guardar(['zona_horaria' => 'Asia/Tokyo']);
        app(ConfiguracionPlataforma::class)->aplicarZonaHoraria();
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00', 'UTC'));

        $escenario['tarea']->update(['fecha_entrega' => '2026-10-05 20:00:00']);

        $this->assertTrue($escenario['tarea']->fresh()->estaVencida(), 'a las 12:00 UTC ya son las 21:00 en Tokio');
    }

    public function test_el_formato_de_fecha_elegido_se_usa_en_pantallas_notificaciones_y_excel(): void
    {
        $escenario = $this->escenario();
        app(ConfiguracionPlataforma::class)->guardar(['formato_fecha' => 'Y-m-d']);
        $escenario['tarea']->update(['fecha_entrega' => '2026-10-05 23:59:00']);
        $tarea = $escenario['tarea']->fresh();

        $this->actingAs($escenario['profesor'])
            ->get(route('cursos.tareas.index', $escenario['curso']))
            ->assertOk()
            ->assertSee('Vence 2026-10-05 23:59')
            ->assertDontSee('05/10/2026');

        $mensaje = (new NuevaTareaAsignada($tarea))->toArray($escenario['estudiante'])['mensaje'];
        $this->assertStringContainsString('vence el 2026-10-05', $mensaje);

        $ruta = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($ruta, (new LibroXlsx)->contenido());
        $zip = new ZipArchive;
        $zip->open($ruta);
        $estilos = $zip->getFromName('xl/styles.xml');
        $zip->close();
        unlink($ruta);

        $this->assertStringContainsString('formatCode="yyyy-mm-dd hh:mm"', $estilos);
    }

    public function test_un_formato_o_zona_invalidos_guardados_usan_los_predeterminados(): void
    {
        app(ConfiguracionPlataforma::class)->guardar(['zona_horaria' => 'Marte/Olimpo', 'formato_fecha' => 'Y/d/m']);

        $configuracion = app(ConfiguracionPlataforma::class);

        $this->assertSame('America/Lima', $configuracion->zonaHoraria());
        $this->assertSame('d/m/Y', $configuracion->formatoFecha());
        $this->assertSame('05/10/2026', Carbon::parse('2026-10-05')->formatoFecha());
    }
}
