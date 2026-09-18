<?php

namespace App\Livewire\Cursos;

use App\Models\Comunicado;
use App\Models\Curso;
use App\Models\Grupo;
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

    // Anuncios del Curso
    public string $nuevoAnuncioTitulo = '';
    public string $nuevoAnuncioTexto = '';
    public string $nuevoAnuncioPrioridad = 'normal';

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

    public function getAnunciosProperty(): Collection
    {
        return $this->curso->comunicados()->with('autor')->latest()->get();
    }

    public function getAnunciosDelCursoProperty(): Collection
    {
        return $this->anuncios;
    }

    public function publicarAnuncio(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->validate([
            'nuevoAnuncioTitulo' => ['required', 'string', 'max:255'],
            'nuevoAnuncioTexto' => ['required', 'string', 'max:5000'],
            'nuevoAnuncioPrioridad' => ['required', 'in:informativo,normal,importante,urgente'],
        ]);

        $this->curso->comunicados()->create([
            'user_id' => auth()->id(),
            'titulo' => $this->nuevoAnuncioTitulo,
            'contenido' => $this->nuevoAnuncioTexto,
            'prioridad' => $this->nuevoAnuncioPrioridad,
            'destinatario' => 'estudiantes',
            'activo' => true,
        ]);

        $this->reset(['nuevoAnuncioTitulo', 'nuevoAnuncioTexto', 'nuevoAnuncioPrioridad']);
        $this->curso->unsetRelation('comunicados');
        session()->flash('estado', 'Anuncio de curso publicado para los estudiantes inscritos.');
    }

    public function eliminarAnuncio(int $anuncioId): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->curso->comunicados()->whereKey($anuncioId)->delete();
        $this->curso->unsetRelation('comunicados');
        session()->flash('estado', 'Anuncio eliminado.');
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

    /**
     * Compañeros de curso (pestaña nueva, solo para un estudiante ya
     * inscrito — no se muestra a quien todavía no se inscribió, por
     * privacidad de la lista de inscritos).
     */
    public function getCompanerosDelCursoProperty(): Collection
    {
        if (! auth()->user()->hasRole('estudiante') || ! $this->estaInscrito) {
            return collect();
        }

        // Nombre del grupo de cada estudiante (si el curso tiene grupos),
        // para mostrarlo junto al nombre en la pestaña de Compañeros.
        $nombreGrupoPorEstudiante = $this->curso->grupos()
            ->with('estudiantes')
            ->get()
            ->flatMap(fn ($grupo) => $grupo->estudiantes->mapWithKeys(fn ($e) => [$e->id => $grupo->nombre]));

        return $this->curso->inscripciones()
            ->with('estudiante')
            ->get()
            ->pluck('estudiante')
            ->filter()
            ->sortBy('name')
            ->map(fn ($estudiante) => tap($estudiante, fn ($e) => $e->grupo_nombre = $nombreGrupoPorEstudiante[$e->id] ?? null))
            ->values();
    }

    /**
     * El grupo del estudiante en este curso (pestaña "Mi grupo"), o null si
     * todavía no le asignaron uno.
     */
    public function getMiGrupoDelCursoProperty(): ?Grupo
    {
        if (! auth()->user()->hasRole('estudiante') || ! $this->estaInscrito) {
            return null;
        }

        return auth()->user()->grupoEnCurso($this->curso)?->load('estudiantes');
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
