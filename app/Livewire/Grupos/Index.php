<?php

namespace App\Livewire\Grupos;

use App\Models\Curso;
use App\Models\Grupo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ERS RF-74: crear, renombrar y eliminar grupos del curso.
 * ERS RF-75: agregar y mover estudiantes matriculados entre grupos
 *            (un estudiante pertenece a lo más a un grupo por curso).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Curso $curso;

    public string $nuevoGrupoNombre = '';

    public int $tamanoGrupoAuto = 4;

    /** @var array<int, string> estudiante elegido por cada grupo, llave = grupo_id. */
    public array $seleccion = [];

    #[Locked]
    public ?int $grupoRenombrarId = null;

    public string $nombreRenombrar = '';

    public function mount(Curso $curso): void
    {
        // Consulta: Profesor del curso y Supervisor. Cambios: solo el Profesor (RF-74, RF-75).
        Gate::authorize('supervisar', $curso);
        $this->curso = $curso;
    }

    public function getEditableProperty(): bool
    {
        return Gate::allows('crear', [Grupo::class, $this->curso]) && ! $this->curso->estaFinalizado();
    }

    public function crearGrupo(): void
    {
        $this->autorizar();

        $this->validate([
            'nuevoGrupoNombre' => ['required', 'string', 'max:255', Rule::unique('grupos', 'nombre')->where('curso_id', $this->curso->id)],
        ], [
            'nuevoGrupoNombre.required' => 'Escribe el nombre del grupo.',
            'nuevoGrupoNombre.unique' => 'Ya existe un grupo con ese nombre en el curso.',
        ]);

        $this->curso->grupos()->create(['nombre' => $this->nuevoGrupoNombre]);
        $this->nuevoGrupoNombre = '';
        session()->flash('estado', 'Grupo creado.');
    }

    public function empezarRenombrar(int $grupoId): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        $this->grupoRenombrarId = $grupo->id;
        $this->nombreRenombrar = $grupo->nombre;
    }

    public function renombrar(): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($this->grupoRenombrarId);

        $this->validate([
            'nombreRenombrar' => ['required', 'string', 'max:255', Rule::unique('grupos', 'nombre')->where('curso_id', $this->curso->id)->ignore($grupo->id)],
        ], [
            'nombreRenombrar.unique' => 'Ya existe un grupo con ese nombre en el curso.',
        ]);

        $grupo->update(['nombre' => $this->nombreRenombrar]);
        $this->reset(['grupoRenombrarId', 'nombreRenombrar']);
        session()->flash('estado', 'Grupo renombrado.');
    }

    public function eliminarGrupo(int $grupoId): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $grupo->delete();
        session()->flash('estado', "Grupo \"{$grupo->nombre}\" eliminado. Las entregas de sus integrantes se conservan.");
    }

    /**
     * Agrega o mueve un estudiante matriculado al grupo: antes se le quita de
     * cualquier otro grupo del mismo curso.
     */
    public function asignar(int $grupoId): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $estudianteId = (int) ($this->seleccion[$grupoId] ?? 0);

        if (! $estudianteId) {
            return;
        }

        abort_unless($this->curso->inscripcionesActivas()->where('estudiante_id', $estudianteId)->exists(), 403, 'El estudiante no está matriculado en el curso.');

        DB::transaction(function () use ($grupo, $estudianteId) {
            DB::table('grupo_estudiante')
                ->whereIn('grupo_id', $this->curso->grupos()->select('id'))
                ->where('estudiante_id', $estudianteId)
                ->delete();

            $grupo->estudiantes()->attach($estudianteId);
        });

        unset($this->seleccion[$grupoId]);
    }

    public function quitar(int $grupoId, int $estudianteId): void
    {
        $this->autorizar();
        $grupo = $this->curso->grupos()->findOrFail($grupoId);
        Gate::authorize('gestionar', $grupo);

        $grupo->estudiantes()->detach($estudianteId);
    }

    /**
     * Reparte en grupos nuevos a los estudiantes matriculados que todavía no
     * están en ninguno, en bloques del tamaño indicado.
     */
    public function generarAutomatico(): void
    {
        $this->autorizar();
        $this->validate(['tamanoGrupoAuto' => ['required', 'integer', 'min:2', 'max:20']]);

        $yaAgrupados = DB::table('grupo_estudiante')
            ->whereIn('grupo_id', $this->curso->grupos()->select('id'))
            ->pluck('estudiante_id');

        $sinGrupo = $this->curso->inscripcionesActivas()
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

        session()->flash('estado', 'Grupos generados.');
    }

    protected function autorizar(): void
    {
        Gate::authorize('crear', [Grupo::class, $this->curso]);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    public function render()
    {
        $grupos = $this->curso->grupos()->with('estudiantes')->orderBy('nombre')->get();
        $grupoDe = $grupos->flatMap(fn (Grupo $g) => $g->estudiantes->mapWithKeys(fn ($e) => [$e->id => $g->nombre]));
        $matriculados = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter()->sortBy('name')->values();

        return view('livewire.grupos.index', [
            'grupos' => $grupos,
            'matriculados' => $matriculados,
            'grupoDe' => $grupoDe,
            'sinGrupo' => $matriculados->reject(fn ($estudiante) => $grupoDe->has($estudiante->id))->values(),
        ])->title("Grupos — {$this->curso->nombre}");
    }
}
