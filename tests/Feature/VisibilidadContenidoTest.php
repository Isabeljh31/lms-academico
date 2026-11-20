<?php

namespace Tests\Feature;

use App\Livewire\Cursos\Show;
use App\Models\Curso;
use App\Models\Leccion;
use App\Models\Modulo;
use App\Notifications\NuevoContenidoPublicado;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Visibilidad para el estudiante de cada elemento de la semana (detalle del
 * curso del Profesor): "No visible" = borrador y "Visible" = publicado.
 */
class VisibilidadContenidoTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_un_elemento_nuevo_nace_no_visible_y_un_valor_invalido_se_rechaza(): void
    {
        $escenario = $this->escenarioEnSemana1();

        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('agregarLeccion', 'recursos', 1)
            ->assertSet('estado', 'borrador')
            ->assertSee('Visibilidad para el estudiante')
            ->set('titulo', 'Guía')
            ->set('fuente', 'enlace')
            ->set('material_url', 'https://example.com')
            ->call('guardarLeccion')
            ->assertHasNoErrors();

        $this->assertSame('borrador', Leccion::where('titulo', 'Guía')->sole()->estado);

        // Al reabrir tras guardar vuelve a "No visible"; un valor fuera de las opciones no se guarda.
        $componente->call('agregarLeccion', 'recursos', 1)
            ->assertSet('estado', 'borrador')
            ->set('titulo', 'Otra guía')
            ->set('fuente', 'enlace')
            ->set('material_url', 'https://example.com')
            ->set('estado', 'oculto')
            ->call('guardarLeccion')
            ->assertHasErrors('estado');

        $this->assertFalse(Leccion::where('titulo', 'Otra guía')->exists());
    }

    public function test_cambiar_a_visible_y_volver_a_no_visible_persiste(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'borrador');

        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('No visible')
            ->assertSee('Editar contenido')
            ->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado')
            ->assertHasNoErrors();
        $this->assertSame('publicado', $leccion->fresh()->estado);

        $componente->call('cambiarVisibilidadLeccion', $leccion->id, 'borrador');
        $this->assertSame('borrador', $leccion->fresh()->estado);
    }

    public function test_una_leccion_programada_pasa_a_visible_sin_fecha(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'programado', ['fecha_publicacion' => now()->addWeek()]);

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado');

        $this->assertSame('publicado', $leccion->fresh()->estado);
        $this->assertNull($leccion->fresh()->fecha_publicacion);
    }

    public function test_un_valor_fuera_de_las_dos_opciones_se_rechaza(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'borrador');

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('cambiarVisibilidadLeccion', $leccion->id, 'programado')
            ->assertStatus(422);

        $this->assertSame('borrador', $leccion->fresh()->estado);
    }

    public function test_un_profesor_que_ya_no_gestiona_el_curso_recibe_403(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'borrador');

        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']]);
        // El Supervisor le reasigna el curso a otro profesor mientras tiene la pantalla abierta.
        $escenario['curso']->update(['profesor_id' => $this->usuarioConRol('profesor')->id]);

        $componente->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado')->assertForbidden();

        $this->assertSame('borrador', $leccion->fresh()->estado);
    }

    public function test_un_elemento_de_otro_curso_no_se_puede_cambiar(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $ajena = $this->leccion($escenario['modulo'], 'borrador');
        $otro = $this->usuarioConRol('profesor');
        $suyo = Curso::factory()->create(['profesor_id' => $otro->id, 'modalidad' => 'virtual']);

        try {
            Livewire::actingAs($otro)->test(Show::class, ['curso' => $suyo])->call('cambiarVisibilidadLeccion', $ajena->id, 'publicado');
            $this->fail('Se esperaba que no encontrara el elemento de otro curso.');
        } catch (ModelNotFoundException) {
            // Correcto: solo se buscan elementos del curso abierto.
        }

        $this->assertSame('borrador', $ajena->fresh()->estado);
    }

    public function test_pasar_a_visible_notifica_una_sola_vez_aunque_se_oculte_y_vuelva_a_mostrar(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'borrador');
        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']]);

        $componente->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado');
        $avisos = $escenario['estudiante']->notifications()->where('type', NuevoContenidoPublicado::class)->get();
        $this->assertCount(1, $avisos);
        $this->assertSame($leccion->id, $avisos->first()->data['leccion_id']);

        $componente->call('cambiarVisibilidadLeccion', $leccion->id, 'borrador')
            ->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado');
        $this->assertSame(1, $escenario['estudiante']->notifications()->where('type', NuevoContenidoPublicado::class)->count());
    }

    public function test_pasar_a_no_visible_no_notifica(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'publicado');

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('cambiarVisibilidadLeccion', $leccion->id, 'borrador');

        $this->assertSame(0, $escenario['estudiante']->notifications()->count());
    }

    public function test_el_aviso_del_formulario_incluye_la_leccion(): void
    {
        $escenario = $this->escenarioEnSemana1();

        Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']])
            ->call('agregarLeccion', 'recursos', 1)
            ->set('titulo', 'Guía')
            ->set('fuente', 'enlace')
            ->set('material_url', 'https://example.com')
            ->set('estado', 'publicado')
            ->call('guardarLeccion')
            ->assertHasNoErrors();

        $aviso = $escenario['estudiante']->notifications()->where('type', NuevoContenidoPublicado::class)->sole();
        $this->assertSame(Leccion::where('titulo', 'Guía')->sole()->id, $aviso->data['leccion_id']);
    }

    public function test_un_curso_archivado_no_admite_cambiar_la_visibilidad(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $leccion = $this->leccion($escenario['modulo'], 'borrador');
        $componente = Livewire::actingAs($escenario['profesor'])->test(Show::class, ['curso' => $escenario['curso']]);
        $escenario['curso']->update(['estado' => 'archivado']);

        $componente->call('cambiarVisibilidadLeccion', $leccion->id, 'publicado')->assertForbidden();

        $this->assertSame('borrador', $leccion->fresh()->estado);
    }

    public function test_el_estudiante_no_ve_un_elemento_no_visible(): void
    {
        $escenario = $this->escenarioEnSemana1();
        $this->leccion($escenario['modulo'], 'borrador', ['titulo' => 'Guía oculta']);
        $this->leccion($escenario['modulo'], 'publicado', ['titulo' => 'Guía visible', 'orden' => 2]);

        Livewire::actingAs($escenario['estudiante'])->test(Show::class, ['curso' => $escenario['curso']])
            ->assertSee('Guía visible')
            ->assertDontSee('Guía oculta');
    }

    /**
     * @return array<string, mixed>
     */
    private function escenarioEnSemana1(): array
    {
        $escenario = $this->escenario();
        $escenario['modulo']->update(['semana' => 1, 'orden' => 1]);
        $escenario['curso']->update(['fecha_inicio' => null]);

        return $escenario;
    }

    /**
     * @param  array<string, mixed>  $atributos
     */
    private function leccion(Modulo $modulo, string $estado, array $atributos = []): Leccion
    {
        return $modulo->lecciones()->create([
            'titulo' => 'Guía de práctica',
            'estado' => $estado,
            'orden' => 1,
            'tipo_recurso' => 'recursos',
            'material_url' => 'https://example.com',
            ...$atributos,
        ]);
    }
}
