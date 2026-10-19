<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado de cursos según el rol (matriz de permisos, sección 5 del ERS):
 * - Supervisor: todos los cursos.
 * - Profesor: solo los cursos que tiene a cargo.
 * - Estudiante: solo los cursos en los que tiene matrícula activa y que están
 *   publicados o archivados (no existe catálogo público, ERS 1.2.2).
 */
#[Layout('layouts.app')]
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
        ])->title('Cursos — LearnUp');
    }
}
