<?php

namespace Tests\Feature\Seguridad;

use App\Enums\Rol;
use App\Livewire\Cursos\LibroCalificaciones;
use App\Livewire\PanelAdmin\Auditoria;
use App\Models\BitacoraAuditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Tests\Concerns\PreparaEscenarioAcademico;
use Tests\TestCase;

/**
 * ERS RF-97 a RF-99: bitácora inmutable, solo lectura y solo para el Supervisor.
 */
class AuditoriaTest extends TestCase
{
    use PreparaEscenarioAcademico, RefreshDatabase;

    protected function registro(): BitacoraAuditoria
    {
        return BitacoraAuditoria::registrarAccionAdmin(null, 'otro_admin', 'Registro de prueba');
    }

    public function test_un_registro_de_auditoria_no_se_puede_modificar(): void
    {
        $registro = $this->registro();

        $this->expectException(LogicException::class);

        $registro->update(['descripcion' => 'Alterado']);
    }

    public function test_un_registro_de_auditoria_no_se_puede_eliminar(): void
    {
        $registro = $this->registro();

        try {
            $registro->delete();
            $this->fail('Se esperaba una excepción al eliminar.');
        } catch (LogicException) {
            $this->assertNotNull(BitacoraAuditoria::find($registro->id));
        }
    }

    public function test_profesor_y_estudiante_no_pueden_consultar_la_bitacora(): void
    {
        $this->crearRoles();

        foreach ([Rol::Profesor, Rol::Estudiante] as $rol) {
            $this->actingAs($this->usuarioConRol($rol->value))->get(route('admin.auditoria'))->assertForbidden();
        }
    }

    public function test_el_supervisor_filtra_por_tipo_usuario_curso_y_fechas(): void
    {
        $escenario = $this->escenario();
        BitacoraAuditoria::registrarAccionAdmin($escenario['supervisor'], 'matricula_manual', 'Matrícula visible', curso: $escenario['curso']);
        BitacoraAuditoria::registrarAccionAdmin($escenario['profesor'], 'cambio_nota', 'Cambio de otro usuario');

        Livewire::actingAs($escenario['supervisor'])->test(Auditoria::class)
            ->set('filtroTipo', 'matricula_manual')
            ->set('filtroCursoId', (string) $escenario['curso']->id)
            ->set('filtroUsuario', $escenario['supervisor']->email)
            ->set('filtroDesde', now()->subDay()->format('Y-m-d'))
            ->set('filtroHasta', now()->format('Y-m-d'))
            ->assertSee('Matrícula visible')
            ->assertDontSee('Cambio de otro usuario');
    }

    public function test_la_pantalla_no_ofrece_acciones_de_edicion_ni_borrado(): void
    {
        $escenario = $this->escenario();
        $this->registro();

        $componente = Livewire::actingAs($escenario['supervisor'])->test(Auditoria::class);

        $this->assertFalse(method_exists($componente->instance(), 'eliminar'));
        $this->assertFalse(method_exists($componente->instance(), 'actualizar'));
    }

    public function test_un_ajuste_de_nota_queda_con_valor_anterior_nuevo_y_motivo(): void
    {
        $escenario = $this->escenario();
        $rubro = $this->esquemaValido($escenario['curso'])->rubros->first();
        $componente = Livewire::actingAs($escenario['profesor'])->test(LibroCalificaciones::class, ['curso' => $escenario['curso']]);

        $componente->call('abrirModalNota', $rubro->id, $escenario['estudiante']->id)->set('notaInput', '12')->call('guardarNotaRubro')->assertHasNoErrors();
        $componente->call('abrirModalNota', $rubro->id, $escenario['estudiante']->id)->set('notaInput', '15')->call('guardarNotaRubro')->assertHasErrors('motivoInput');
        $componente->set('motivoInput', 'Revisión de examen')->call('guardarNotaRubro')->assertHasNoErrors();

        $this->assertDatabaseHas('bitacoras_auditoria', [
            'tipo_evento' => 'cambio_nota',
            'ejecutado_por' => $escenario['profesor']->id,
            'usuario_afectado_id' => $escenario['estudiante']->id,
            'valor_anterior' => '12',
            'valor_nuevo' => '15',
            'motivo' => 'Revisión de examen',
        ]);
    }
}
