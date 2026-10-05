<?php

namespace Tests\Feature\Seguridad;

use App\Livewire\Tareas\Detalle;
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
 * Subida de archivos: tipos permitidos, tamaño máximo de 10 MB (límite de
 * Cloudinary), nombre generado por el servidor y error claro en vez de 500.
 */
class SubidaArchivosTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        config(['services.cloudinary.url' => null]);
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
        $this->assertSame([], Storage::disk('public')->allFiles());
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

    public function test_guarda_el_archivo_con_un_nombre_generado_por_el_servidor(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['estudiante'])
            ->test(Detalle::class, ['tarea' => $escenario['tarea']])
            ->set('archivo', UploadedFile::fake()->create('mi trabajo final.pdf', 20, 'application/pdf'))
            ->call('entregar')
            ->assertHasNoErrors();

        $archivos = Storage::disk('public')->allFiles('entregas');
        $this->assertCount(1, $archivos);
        $this->assertMatchesRegularExpression('#^entregas/[0-9a-f-]{36}\.pdf$#', $archivos[0]);
    }

    public function test_un_fallo_del_proveedor_de_archivos_se_informa_como_error_de_validacion(): void
    {
        $escenario = $this->escenario();

        $this->partialMock(SubidorArchivos::class, function ($mock) {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('subirLocal')
                ->andThrow(new RuntimeException('File size too large'));
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
