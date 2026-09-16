<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Modulo;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Curso $curso;

    public string $nuevoModuloTitulo = '';

    public ?int $moduloMaterialId = null;

    public ?UploadedFile $material = null;

    public function mount(Curso $curso): void
    {
        Gate::authorize('ver', $curso);

        $this->curso = $curso;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('gestionar', $this->curso);
    }

    public function getEstaInscritoProperty(): bool
    {
        return auth()->user()->hasRole('estudiante')
            && $this->curso->inscripciones()->where('estudiante_id', auth()->id())->exists();
    }

    /**
     * Datos de solo lectura para el rediseño visual del rol estudiante
     * (pestañas de Cursos/Show). No sustituyen ni alteran las propiedades
     * ni los métodos ya existentes/probados de este componente.
     */
    public function getProgresoModulosProperty(): array
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return [];
        }

        $tareas = $this->curso->tareas()
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', auth()->id())])
            ->get();

        $porModulo = [];

        foreach ($tareas as $tarea) {
            $porModulo[$tarea->modulo_id] ??= ['total' => 0, 'completadas' => 0];
            $porModulo[$tarea->modulo_id]['total']++;

            if ($tarea->entregas->isNotEmpty()) {
                $porModulo[$tarea->modulo_id]['completadas']++;
            }
        }

        return $porModulo;
    }

    public function getResumenTareasEstudianteProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return collect();
        }

        return $this->curso->tareas()
            ->with(['entregas' => fn ($q) => $q->where('estudiante_id', auth()->id())])
            ->orderBy('fecha_entrega')
            ->get()
            ->map(fn ($tarea) => [
                'tarea' => $tarea,
                'entrega' => $tarea->entregas->first(),
                'estado' => $tarea->entregas->first()?->estado
                    ?? ($tarea->estaVencida() ? 'atrasado' : 'pendiente'),
            ]);
    }

    public function getResumenQuizzesEstudianteProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante')) {
            return collect();
        }

        return $this->curso->quizzes()
            ->with(['resultados' => fn ($q) => $q->where('estudiante_id', auth()->id())])
            ->get()
            ->map(fn ($quiz) => [
                'quiz' => $quiz,
                'resultado' => $quiz->resultados->first(),
            ]);
    }

    public function inscribirme(): void
    {
        Gate::authorize('inscribirse', $this->curso);

        $this->curso->inscripciones()->firstOrCreate([
            'estudiante_id' => auth()->id(),
        ], [
            'fecha_inscripcion' => now(),
        ]);

        session()->flash('estado', 'Te inscribiste en el curso.');
    }

    public function agregarModulo(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->validate(['nuevoModuloTitulo' => ['required', 'string', 'max:255']]);

        $this->curso->modulos()->create([
            'titulo' => $this->nuevoModuloTitulo,
            'orden' => $this->curso->modulos()->max('orden') + 1,
            'materiales' => [],
        ]);

        $this->nuevoModuloTitulo = '';
        $this->curso->unsetRelation('modulos');
    }

    public function eliminarModulo(int $moduloId): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->curso->modulos()->whereKey($moduloId)->delete();
        $this->curso->unsetRelation('modulos');
    }

    public function subirMaterial(int $moduloId, SubidorArchivos $subidor): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->validate(['material' => ['required', 'file', 'max:10240']]);

        $modulo = Modulo::where('curso_id', $this->curso->id)->findOrFail($moduloId);

        $url = $subidor->subir($this->material, 'cursos/materiales');

        $modulo->update([
            'materiales' => [
                ...($modulo->materiales ?? []),
                ['nombre' => $this->material->getClientOriginalName(), 'url' => $url],
            ],
        ]);

        $this->material = null;
        $this->curso->unsetRelation('modulos');
    }

    public function render()
    {
        return view('livewire.cursos.show', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
        ]);
    }
}
