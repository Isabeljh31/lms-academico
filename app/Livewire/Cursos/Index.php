<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public function render()
    {
        $usuario = auth()->user();

        $cursos = Curso::query()
            ->with(['profesor', 'modulos'])
            ->withCount('inscripciones')
            ->when($this->busqueda, fn ($q) => $q->where('nombre', 'like', "%{$this->busqueda}%"))
            ->when($usuario->hasRole('profesor'), fn ($q) => $q->where('profesor_id', $usuario->id))
            ->orderBy('nombre')
            ->paginate(9);

        $inscritos = $usuario->hasRole('estudiante')
            ? $usuario->inscripciones()->pluck('curso_id')->flip()
            : collect();

        return view('livewire.cursos.index', [
            'cursos' => $cursos,
            'inscritos' => $inscritos,
            'puedeCrear' => Gate::allows('crear', Curso::class),
        ]);
    }
}
