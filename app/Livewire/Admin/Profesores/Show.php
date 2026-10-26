<?php

namespace App\Livewire\Admin\Profesores;

use App\Enums\Rol;
use App\Livewire\Admin\Profesores\Concerns\GestionaProfesor;
use App\Models\Curso;
use App\Models\User;
use App\Services\Usuarios\GestorCuentas;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Ficha de detalle de un profesor (Supervisor): todos sus datos, CV, cursos
 * a cargo y métricas de calificación. El directorio (Index) se queda
 * compacto; esta pantalla es donde se ve todo.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    use GestionaProfesor, WithFileUploads;

    public User $profesor;

    public function mount(User $profesor): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        abort_unless($profesor->hasRole(Rol::Profesor->value), 404);

        $this->profesor = $profesor;
    }

    /**
     * Sobrescribe la del trait: acá hay que salir de la ficha, no solo
     * refrescar la lista, porque el profesor que se borra es el que se ve.
     */
    public function eliminarProfesor(int $id, GestorCuentas $gestor): void
    {
        $nombre = $this->profesor->name;
        $gestor->eliminar(User::role(Rol::Profesor->value)->findOrFail($id));
        session()->flash('estado', "Profesor \"{$nombre}\" eliminado correctamente.");
        $this->redirect(route('admin.profesores.index'), navigate: true);
    }

    public function render()
    {
        $this->profesor->refresh()->load(['cursosComoProfesor.tareas.entregas', 'cursosComoProfesor.inscripcionesActivas']);

        $cursos = $this->profesor->cursosComoProfesor;
        $cursosActivos = $cursos->reject(fn (Curso $c) => $c->estaFinalizado())->values();
        $cursosArchivados = $cursos->filter(fn (Curso $c) => $c->estaFinalizado())->values();

        $cursosConMetricas = $cursos->map(function (Curso $curso) {
            $entregasCurso = $curso->tareas->flatMap->entregas;
            $totalCurso = $entregasCurso->count();
            $calificadasCurso = $entregasCurso->where('estado', 'calificado')->count();

            return [
                'curso' => $curso,
                'estudiantes' => $curso->inscripcionesActivas->count(),
                'tareas' => $curso->tareas->count(),
                'total_entregas' => $totalCurso,
                'calificadas' => $calificadasCurso,
                'tasa' => $totalCurso > 0 ? (int) round($calificadasCurso / $totalCurso * 100) : 100,
            ];
        })->sortByDesc(fn (array $f) => ! $f['curso']->estaFinalizado())->values();

        $entregas = $cursos->flatMap->tareas->flatMap->entregas;
        $totalEntregas = $entregas->count();
        $calificadas = $entregas->where('estado', 'calificado')->count();

        return view('livewire.admin.profesores.show', [
            'cursos' => $cursos,
            'cursosActivos' => $cursosActivos,
            'cursosArchivados' => $cursosArchivados,
            'cursosConMetricas' => $cursosConMetricas,
            'estudiantesCount' => $cursos->flatMap->inscripcionesActivas->pluck('estudiante_id')->unique()->count(),
            'tareasCount' => $cursos->flatMap->tareas->count(),
            'totalEntregas' => $totalEntregas,
            'calificadas' => $calificadas,
            'pendientes' => $totalEntregas - $calificadas,
            'tasaCalificacion' => $totalEntregas > 0 ? (int) round(($calificadas / $totalEntregas) * 100) : 100,
            'cursosDisponibles' => Curso::where('estado', '!=', 'archivado')->orderBy('nombre')->get(),
            'profesorSeleccionado' => $this->profesorSeleccionadoId ? User::find($this->profesorSeleccionadoId) : null,
            'cursoSeleccionado' => $this->cursoAAsignarId ? Curso::find($this->cursoAAsignarId) : null,
            'profesoresDisponibles' => User::role(Rol::Profesor->value)->where('activo', true)->orderBy('name')->get(),
        ])->title("{$this->profesor->name} - LearnUp");
    }
}
