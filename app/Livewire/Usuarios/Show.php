<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Livewire\Usuarios\Concerns\GestionaEstudiante;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Reportes\ProgresoAcademicoService;
use App\Services\Usuarios\GestorCuentas;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Ficha de detalle de un estudiante (Supervisor): datos personales, apoderado,
 * avance por curso y riesgo de abandono. El directorio (Index) se queda
 * compacto; esta pantalla es donde se ve todo.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use GestionaEstudiante;

    public User $estudiante;

    public function mount(User $estudiante): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        abort_unless($estudiante->hasRole(Rol::Estudiante->value), 404);

        $this->estudiante = $estudiante;
    }

    /**
     * Sobrescribe la del trait: acá hay que salir de la ficha, no solo
     * refrescar la lista, porque el estudiante que se borra es el que se ve.
     */
    public function eliminarCuenta(int $id, GestorCuentas $gestor): void
    {
        $nombre = $this->estudiante->name;
        $gestor->eliminar(User::role(Rol::Estudiante->value)->findOrFail($id));
        session()->flash('estado', "Estudiante \"{$nombre}\" eliminado correctamente.");
        $this->redirect(route('admin.usuarios.index'), navigate: true);
    }

    public function render(ProgresoAcademicoService $progreso, CalculadoraAsistencia $asistencia, CalculadoraNotas $notas)
    {
        $this->estudiante->refresh()->load(['inscripciones.curso', 'inscripciones.dadoDeBajaPor']);

        $inscripciones = $this->estudiante->inscripciones;

        $inscripcionesActivas = $inscripciones->filter(fn ($i) => $i->curso && $i->estaActiva() && ! $i->curso->estaFinalizado());
        $inscripcionesHistorial = $inscripciones->filter(fn ($i) => $i->curso && ! ($i->estaActiva() && ! $i->curso->estaFinalizado()));

        $cursosConMetricas = $inscripcionesActivas->map(function ($inscripcion) use ($progreso, $asistencia) {
            $curso = $inscripcion->curso;
            $resumenRubros = $progreso->resumenRubros($this->estudiante, $curso);

            return [
                'curso' => $curso,
                'progreso' => $progreso->avance($this->estudiante, $curso),
                'nota_final' => $resumenRubros['nota_final'],
                'condicion' => $resumenRubros['condicion'],
                'tiene_esquema' => $resumenRubros['esquema'] !== null,
                'asistencia' => $curso->usa_asistencia ? $asistencia->porcentajeDe($this->estudiante, $curso) : null,
            ];
        })->values();

        $riesgo = $this->calcularRiesgo($this->estudiante, $inscripcionesActivas->pluck('curso'), $asistencia, $notas);

        $historial = $inscripcionesHistorial->map(fn ($inscripcion) => [
            'curso' => $inscripcion->curso,
            'motivo' => $inscripcion->curso->estaFinalizado() && $inscripcion->estaActiva() ? 'finalizado' : 'baja',
            'fecha' => $inscripcion->estaActiva() ? $inscripcion->curso->fecha_fin : $inscripcion->fecha_baja,
            'dado_de_baja_por' => $inscripcion->dadoDeBajaPor,
        ])->sortByDesc(fn (array $h) => $h['fecha'])->values();

        $dashboard = $progreso->resumenDashboardEstudiante($this->estudiante);

        return view('livewire.usuarios.show', [
            'cursosConMetricas' => $cursosConMetricas,
            'riesgoNivel' => $riesgo['nivel'],
            'riesgoMotivos' => $riesgo['motivos'],
            'historial' => $historial,
            'proximasEntregas' => $dashboard['proximas_entregas'],
            'totalPendientes' => $dashboard['total_pendientes'],
            'usuarioEditar' => $this->estudiante,
        ])->title("{$this->estudiante->name} - LearnUp");
    }
}
