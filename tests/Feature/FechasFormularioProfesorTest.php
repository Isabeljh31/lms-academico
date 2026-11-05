<?php

namespace Tests\Feature;

use App\Livewire\Quizzes\Formulario as FormularioQuiz;
use App\Livewire\Tareas\Formulario as FormularioTarea;
use App\Models\Tarea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * Formularios de tarea y quiz del Profesor: fechas por defecto (inicio =
 * ahora, límite = hoy 23:59 o mañana si ya pasó), validación de la fecha
 * límite, lectura en 12 h, peso del resumen del alumno y formatos.
 */
class FechasFormularioProfesorTest extends TestCase
{
    use PreparaEscenarioAcademico;
    use RefreshDatabase;

    public function test_al_crear_el_inicio_es_ahora_y_el_limite_hoy_a_las_once_y_cincuenta_y_nueve_de_la_noche(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 17:08:42'));
        $e = $this->escenario();

        foreach ([[FormularioTarea::class, 'fecha_entrega'], [FormularioQuiz::class, 'fecha_limite']] as [$formulario, $limite]) {
            Livewire::actingAs($e['profesor'])->test($formulario, ['curso' => $e['curso']])
                ->assertSet('fecha_inicio', '2026-11-03T17:08')
                ->assertSet($limite, '2026-11-03T23:59')
                ->assertSee('Vence hoy a las 11:59 p. m.');
        }
        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->assertSee('Empieza hoy a las 5:08 p. m.');
    }

    public function test_si_ya_pasaron_las_once_y_cincuenta_y_nueve_el_limite_es_manana(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 23:59:20'));
        $e = $this->escenario();

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->assertSet('fecha_entrega', '2026-11-04T23:59')
            ->assertSee('Vence mañana a las 11:59 p. m.');
        Livewire::actingAs($e['profesor'])->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->assertSet('fecha_limite', '2026-11-04T23:59');
    }

    public function test_la_fecha_limite_no_puede_estar_en_el_pasado_ni_antes_del_inicio(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 17:08'));
        $e = $this->escenario();

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->set('titulo', 'Informe')->set('modulo_id', $e['modulo']->id)
            ->set('fecha_inicio', '')->set('fecha_entrega', '2026-11-03T17:00')
            ->call('guardar')
            ->assertHasErrors(['fecha_entrega'])
            ->assertSee('La fecha límite ya pasó. Elige una fecha y hora desde ahora.')
            ->set('fecha_inicio', '2026-11-05T08:00')->set('fecha_entrega', '2026-11-04T08:00')
            ->call('guardar')
            ->assertSee('La fecha límite no puede ser anterior a la fecha de inicio.');

        Livewire::actingAs($e['profesor'])->test(FormularioQuiz::class, ['curso' => $e['curso']])
            ->set('tema', 'Repaso')->set('modulo_id', $e['modulo']->id)
            ->set('preguntas', [['enunciado' => '¿2 + 2?', 'opciones' => ['3', '4'], 'respuesta_correcta' => 1]])
            ->set('fecha_inicio', '')->set('fecha_limite', '2026-11-02T23:59')
            ->call('guardar')
            ->assertHasErrors(['fecha_limite'])
            ->assertSee('La fecha límite ya pasó. Elige una fecha y hora desde ahora.');

        $this->assertFalse(Tarea::where('titulo', 'Informe')->exists());
    }

    public function test_una_tarea_ya_vencida_se_edita_sin_tocar_su_fecha_pero_no_se_mueve_a_otra_fecha_pasada(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 17:08'));
        $e = $this->escenario();
        $e['tarea']->update(['fecha_inicio' => null, 'fecha_entrega' => '2026-10-20 23:59']);

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso'], 'tarea' => $e['tarea']])
            ->assertSet('fecha_entrega', '2026-10-20T23:59')
            ->set('titulo', 'Título corregido')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame('Título corregido', $e['tarea']->fresh()->titulo);

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso'], 'tarea' => $e['tarea']])
            ->set('fecha_entrega', '2026-10-25T23:59')
            ->call('guardar')->assertHasErrors(['fecha_entrega']);
    }

    public function test_la_lectura_en_doce_horas_distingue_manana_y_tarde(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 09:00'));
        $e = $this->escenario();
        $formulario = Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])->instance();

        $this->assertSame('Vence hoy a las 12:00 a. m.', $formulario->lecturaFecha('2026-11-03T00:00', 'Vence'));
        $this->assertSame('Vence hoy a las 12:00 p. m.', $formulario->lecturaFecha('2026-11-03T12:00', 'Vence'));
        $this->assertSame('Empieza mañana a la 1:05 p. m.', $formulario->lecturaFecha('2026-11-04T13:05', 'Empieza'));
        $this->assertSame('Vence el 20/11/2026 a las 9:30 a. m.', $formulario->lecturaFecha('2026-11-20T09:30', 'Vence'));
        $this->assertSame('Sin fecha', $formulario->lecturaFecha('', 'Vence', 'Sin fecha'));
    }

    public function test_el_peso_del_resumen_se_oculta_en_actividades_calificadas(): void
    {
        $e = $this->escenario();
        $rubro = $this->esquemaValido($e['curso'])->rubros->first();

        foreach (['practica', 'examen'] as $tipo) {
            Livewire::withQueryParams(['tipo' => $tipo])->actingAs($e['profesor'])
                ->test(FormularioTarea::class, ['curso' => $e['curso']])
                ->assertDontSee('Peso en el resumen del alumno')->assertSet('peso_nota', 1);
        }
        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso'], 'tarea' => $e['tarea']])
            ->assertSee('Peso en el resumen del alumno');

        $rubro->tareas()->attach($e['tarea']->id, ['peso' => 100]);
        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso'], 'tarea' => $e['tarea']])
            ->assertDontSee('Peso en el resumen del alumno')
            ->call('guardar')->assertHasNoErrors();
        $this->assertSame((float) $e['tarea']->peso_nota, (float) $e['tarea']->fresh()->peso_nota);
    }

    public function test_los_formatos_se_pueden_marcar_o_quitar_todos_y_el_texto_lo_aclara(): void
    {
        $e = $this->escenario();

        Livewire::actingAs($e['profesor'])->test(FormularioTarea::class, ['curso' => $e['curso']])
            ->assertSee('Seleccionar todos')->assertSee('Quitar todos')
            ->assertSee('Sin marcar ninguno se aceptan todos los formatos seguros.')
            ->assertDontSee('.exe')->assertDontSee('.svg')->assertDontSee('.html');
    }
}
