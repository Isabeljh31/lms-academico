<?php

namespace Tests\Feature\Seguridad;

use App\Livewire\Tareas\Detalle;
use App\Models\Entrega;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Subida de archivos: tipos permitidos, tamaño máximo de 10 MB, nombre
 * generado por el servidor, error claro en vez de 500 y, por ERS RNF-06,
 * almacenamiento privado con enlaces temporales.
 */
class SubidaArchivosTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local', ['serve' => true]);
    }

    public function test_rechaza_archivos_ejecutables_en_una_entrega(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('shell.php', 1, 'application/x-php'))
            ->call('entregar')
            ->assertHasErrors(['archivo' => 'extensions']);

        $this->assertSame(0, $escenario['tarea']->entregas()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('entregas'));
    }

    public function test_rechaza_archivos_de_mas_de_diez_megas_con_un_mensaje_claro(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('trabajo.pdf', 10241, 'application/pdf'))
            ->call('entregar')
            ->assertHasErrors(['archivo' => 'max'])
            ->assertSee('supera el tamaño máximo permitido de 10 MB');
    }

    public function test_respeta_los_formatos_restringidos_por_la_tarea(): void
    {
        $escenario = $this->escenario();
        $escenario['tarea']->update(['formatos_permitidos' => ['pdf']]);

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']->fresh()])
            ->set('archivo', UploadedFile::fake()->create('trabajo.docx', 10))
            ->call('entregar')
            ->assertHasErrors(['archivo' => 'extensions']);
    }

    public function test_guarda_en_el_almacenamiento_privado_la_ruta_con_un_nombre_generado_por_el_servidor(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('mi trabajo final.pdf', 20, 'application/pdf'))
            ->call('entregar')
            ->assertHasNoErrors();

        $ruta = Entrega::firstOrFail()->archivo_url;

        $this->assertMatchesRegularExpression('#^entregas/[0-9a-f-]{36}\.pdf$#', $ruta);
        Storage::disk('local')->assertExists($ruta);
    }

    public function test_el_archivo_se_abre_con_un_enlace_firmado_que_vence(): void
    {
        // Disco local real (sin simular): es el que firma los enlaces temporales.
        Storage::forgetDisk('local');
        $escenario = $this->escenario();
        $ruta = app(SubidorArchivos::class)->subir(UploadedFile::fake()->create('trabajo.pdf', 20, 'application/pdf'), 'pruebas-enlace');

        try {
            $enlace = SubidorArchivos::enlace($ruta);

            $this->assertStringContainsString('signature=', $enlace);
            $this->actingAs($escenario['estudiante'])->get($enlace)->assertOk();

            $this->travel(config('archivos.minutos_enlace') + 1)->minutes();

            $this->get($enlace)->assertForbidden();
        } finally {
            Storage::disk('local')->deleteDirectory('pruebas-enlace');
        }
    }

    public function test_un_enlace_externo_se_muestra_tal_cual(): void
    {
        $this->assertSame('https://drive.google.com/file/d/abc', SubidorArchivos::enlace('https://drive.google.com/file/d/abc'));
        $this->assertNull(SubidorArchivos::enlace(null));
    }

    public function test_la_pantalla_de_la_tarea_muestra_un_enlace_temporal_y_no_la_ruta_guardada(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('trabajo.pdf', 20, 'application/pdf'))
            ->call('entregar');

        $ruta = Entrega::firstOrFail()->archivo_url;

        $this->actingAs($escenario['estudiante'])
            ->get(route('cursos.tareas.show', [$escenario['curso'], $escenario['tarea']]))
            ->assertSee($ruta.'?expiration=', false)
            ->assertDontSee('href="'.$ruta.'"', false);
    }

    public function test_un_fallo_del_almacenamiento_se_informa_como_error_de_validacion(): void
    {
        $escenario = $this->escenario();

        $this->partialMock(SubidorArchivos::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('guardar')
                ->andThrow(new RuntimeException('Bucket no disponible'));
        });

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('trabajo.pdf', 20, 'application/pdf'))
            ->call('entregar')
            ->assertHasErrors('archivo')
            ->assertSee('No se pudo subir el archivo');

        $this->assertSame(0, $escenario['tarea']->entregas()->count());
    }

    public function test_el_servicio_rechaza_extensiones_fuera_de_la_lista_blanca(): void
    {
        $this->expectException(ValidationException::class);

        app(SubidorArchivos::class)->subir(UploadedFile::fake()->create('pagina.html', 1), 'pruebas');
    }
}
