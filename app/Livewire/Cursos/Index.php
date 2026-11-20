<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\View\Components\PanelProfesorLayout;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de cursos según el rol (matriz de permisos, sección 5 del ERS):
 * - Supervisor: todos los cursos.
 * - Profesor: solo los cursos que tiene a cargo.
 * - Estudiante: solo los cursos en los que tiene matrícula activa y que están
 *   publicados o archivados (no existe catálogo público, ERS 1.2.2).
 */
class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public string $filtroEstado = '';

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'filtroEstado'], true)) {
            $this->resetPage();
        }
    }

    public function render()
    {
        $usuario = auth()->user();

        // Profesor: su pantalla Cursos rediseñada, con el layout de barra inferior (solo elige la vista).
        if (PanelProfesorLayout::aplicaA($usuario)) {
            return view('livewire.docente.cursos.pagina')->layout('layouts.panel-profesor')->title('Cursos - LearnUp');
        }

        $esEstudiante = $usuario->hasRole('estudiante');

        $cursos = Curso::query()
            ->with(['profesor', 'profesoresAdicionales', 'modulos'])
            ->withCount(['inscripciones', 'inscripcionesActivas'])
            ->when(! $esEstudiante && $this->filtroEstado, fn ($q) => $q->where('estado', $this->filtroEstado))
            ->when($this->busqueda, fn ($q) => $q->where(fn ($q) => $q
                ->where('nombre', 'like', "%{$this->busqueda}%")
                ->orWhere('codigo', 'like', "%{$this->busqueda}%")
            ))
            ->when($usuario->hasRole('profesor'), fn ($q) => $q->dictadosPor($usuario))
            ->when($esEstudiante, fn ($q) => $q
                ->visiblesParaEstudiantes()
                ->whereHas('inscripcionesActivas', fn ($i) => $i->where('estudiante_id', $usuario->id))
            )
            ->orderBy('nombre')
            ->paginate(9);

        $inscritos = $esEstudiante
            ? $usuario->inscripciones()->pluck('curso_id')->flip()
            : collect();

        return view('livewire.cursos.index', [
            'cursos' => $cursos,
            'inscritos' => $inscritos,
            'puedeCrear' => Gate::allows('crear', Curso::class),
        ])->layout('layouts.app')->title('Cursos - LearnUp');
    }
}
