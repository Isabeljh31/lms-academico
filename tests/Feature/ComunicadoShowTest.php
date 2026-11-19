<?php

namespace Tests\Feature;

use App\Enums\Rol;
use App\Livewire\Compartido\Comunicados\Show;
use App\Livewire\Supervisor\Comunicados\Index as ComunicadosIndex;
use App\Models\Comunicado;
use App\Notifications\NuevoComunicado;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Extensión fuera del ERS: detalle completo de un comunicado/anuncio (texto
 * largo e imagen), que marca como leída su notificación al abrirse.
 */
class ComunicadoShowTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    public function test_el_estudiante_ve_el_detalle_de_un_comunicado_institucional_y_se_marca_como_leida(): void
    {
        $escenario = $this->escenario();
        $comunicado = Comunicado::create([
            'user_id' => $escenario['supervisor']->id,
            'titulo' => 'Cierre de actas',
            'contenido' => 'El cierre de actas será el próximo viernes.',
            'prioridad' => 'informativo',
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);
        $escenario['estudiante']->notify(new NuevoComunicado($comunicado));
        $notificacion = $escenario['estudiante']->notifications()->first();
        $this->assertNull($notificacion->read_at);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['comunicado' => $comunicado])
            ->assertSee('Cierre de actas')
            ->assertSee('El cierre de actas será el próximo viernes.');

        $this->assertNotNull($notificacion->fresh()->read_at);
    }

    public function test_el_estudiante_ve_el_anuncio_de_su_curso(): void
    {
        $escenario = $this->escenario();
        $anuncio = $escenario['curso']->comunicados()->create([
            'user_id' => $escenario['profesor']->id,
            'titulo' => 'Recordatorio de práctica',
            'contenido' => 'Traer laptop a la próxima clase.',
            'prioridad' => 'normal',
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['comunicado' => $anuncio])
            ->assertSee('Recordatorio de práctica');
    }

    public function test_un_estudiante_no_matriculado_no_puede_ver_el_anuncio_del_curso(): void
    {
        $escenario = $this->escenario();
        $ajeno = $this->usuarioConRol(Rol::Estudiante->value);
        $anuncio = $escenario['curso']->comunicados()->create([
            'user_id' => $escenario['profesor']->id,
            'titulo' => 'Recordatorio',
            'contenido' => 'Contenido.',
            'prioridad' => 'normal',
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);

        Livewire::actingAs($ajeno)->test(Show::class, ['comunicado' => $anuncio])
            ->assertForbidden();
    }

    public function test_un_comunicado_dirigido_a_otro_rol_no_es_visible(): void
    {
        $escenario = $this->escenario();
        $comunicado = Comunicado::create([
            'user_id' => $escenario['supervisor']->id,
            'titulo' => 'Solo para profesores',
            'contenido' => 'Contenido.',
            'prioridad' => 'informativo',
            'destinatario' => 'profesores',
            'activo' => true,
        ]);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['comunicado' => $comunicado])
            ->assertNotFound();
    }

    public function test_el_supervisor_puede_subir_una_imagen_y_el_detalle_la_muestra(): void
    {
        $escenario = $this->escenario();

        Livewire::actingAs($escenario['supervisor'])->test(ComunicadosIndex::class)
            ->set('titulo', 'Con imagen')
            ->set('contenido', 'Comunicado con imagen adjunta.')
            ->set('imagen', UploadedFile::fake()->image('aviso.jpg'))
            ->call('crearComunicado');

        $comunicado = Comunicado::where('titulo', 'Con imagen')->firstOrFail();
        $this->assertNotNull($comunicado->imagen);
        $this->assertNotNull($comunicado->imagenUrl());

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['comunicado' => $comunicado])
            ->assertSeeHtml('<img');
    }
}
