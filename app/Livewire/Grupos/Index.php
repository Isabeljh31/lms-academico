<?php

namespace App\Livewire\Grupos;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Index extends Component
{
    public Curso $curso;

    public string $nuevoGrupoNombre = '';

    public int $tamanoGrupoAuto = 4;

    /** @var array<int, string> estudiante_id elegido por cada grupo (wire:model), llave = grupo_id. */
    public array $seleccion = [];

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);

        $this->curso = $curso;
    }

    public function crearGrupo(): void
    {
        Gate::authorize('crear', [Grupo::class, $this->curso]);

        $this->validate(['nuevoGrupoNombre' => ['required', 'string', 'max:255']]);

        $this->curso->grupos()->create(['nombre' => $this->nuevoGrupoNombre]);

        $this->nuevoGrupoNombre = '';
    }

    public function eliminarGrupo(int $grupoId): void
    {
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $grupo->delete();
    }

    /**
     * Un estudiante solo pertenece a un grupo por curso: antes de sumarlo
     * a uno, se le quita de cualquier otro grupo de este mismo curso.
     */
    public function asignar(int $grupoId): void
    {
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $estudianteId = $this->seleccion[$grupoId] ?? null;

        if (! $estudianteId) {
            return;
        }

        DB::table('grupo_estudiante')
            ->whereIn('grupo_id', $this->curso->grupos()->pluck('id'))
            ->where('estudiante_id', $estudianteId)
            ->delete();

        $grupo->estudiantes()->attach($estudianteId);
        unset($this->seleccion[$grupoId]);
    }

    public function quitar(int $grupoId, int $estudianteId): void
    {
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $grupo->estudiantes()->detach($estudianteId);
    }

    /**
     * Reparte en grupos nuevos a los estudiantes inscritos que todavía no
     * están en ninguno, en bloques del tamaño indicado. No toca a quien ya
     * tiene grupo asignado.
     */
    public function generarAutomatico(): void
    {
        Gate::authorize('crear', [Grupo::class, $this->curso]);

        $this->validate(['tamanoGrupoAuto' => ['required', 'integer', 'min:2', 'max:20']]);

        $yaAgrupados = DB::table('grupo_estudiante')
            ->whereIn('grupo_id', $this->curso->grupos()->pluck('id'))
            ->pluck('estudiante_id');

        $sinGrupo = $this->curso->inscripciones()
            ->whereNotIn('estudiante_id', $yaAgrupados)
            ->pluck('estudiante_id')
            ->shuffle()
            ->values();

        if ($sinGrupo->isEmpty()) {
            $this->addError('tamanoGrupoAuto', 'No hay estudiantes sin grupo para repartir.');

            return;
        }

        $numeroInicial = $this->curso->grupos()->count() + 1;

        $sinGrupo->chunk($this->tamanoGrupoAuto)->values()->each(function ($bloque, $indice) use ($numeroInicial) {
            $grupo = $this->curso->grupos()->create(['nombre' => 'Grupo '.($numeroInicial + $indice)]);
            $grupo->estudiantes()->attach($bloque->all());
        });
    }

    public function render()
    {
        $grupos = $this->curso->grupos()->with('estudiantes')->get();
        $idsAgrupados = $grupos->flatMap(fn (Grupo $g) => $g->estudiantes->pluck('id'));

        $inscritos = $this->curso->inscripciones()->with('estudiante')->get()->pluck('estudiante')->filter();

        return view('livewire.grupos.index', [
            'grupos' => $grupos,
            'sinGrupo' => $inscritos->reject(fn ($estudiante) => $idsAgrupados->contains($estudiante->id))->values(),
        ]);
    }
}
