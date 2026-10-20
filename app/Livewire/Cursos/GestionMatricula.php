<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Matricula\GestorMatriculas;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Matrícula de un curso.
 * ERS RF-26: matrícula manual. RF-28: origen. RF-29 a RF-31: baja lógica con
 * confirmación. RF-32: matrículas de API solo se desactivan localmente.
 * RF-33: notificación al estudiante.
 *
 * Matriz de permisos (ERS § 5): matricula solo el Supervisor; dan de baja el
 * Supervisor (todos los cursos) y el Profesor (sus cursos). Una matrícula que
 * llegó por API solo la desactiva el Supervisor (RF-32), porque el sistema
 * externo es la fuente de verdad. En un curso archivado no hay bajas (RNF-14).
 */
#[Layout('layouts.app')]
class GestionMatricula extends Component
{
    use WithPagination;

    public Curso $curso;

    public string $busquedaEstudiante = '';

    public ?int $estudianteSeleccionadoId = null;

    public bool $mostrarModalBaja = false;

    #[Locked]
    public ?int $inscripcionBajaId = null;

    public string $motivoBaja = '';

    public string $filtroEstado = 'activo';

    public function mount(Curso $curso): void
    {
        Gate::authorize('supervisar', $curso);
        $this->curso = $curso;
    }

    public function getPuedeMatricularProperty(): bool
    {
        return Gate::allows('matricular', $this->curso) && ! $this->curso->estaFinalizado();
    }

    /** Hay al menos una baja que este usuario puede hacer en el curso. */
    public function getPuedeDarDeBajaProperty(): bool
    {
        return Gate::allows('darDeBaja', $this->curso) && ! $this->curso->estaFinalizado();
    }

    public function puedeDarDeBajaA(Inscripcion $inscripcion): bool
    {
        return $this->puedeDarDeBaja
            && $inscripcion->estaActiva()
            && (! $inscripcion->esDeApiExterna() || Gate::allows('administrar', $this->curso));
    }

    public function updatingBusquedaEstudiante(): void
    {
        $this->estudianteSeleccionadoId = null;
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function seleccionarEstudiante(int $id): void
    {
        abort_unless($this->puedeMatricular, 403);

        $estudiante = User::role('estudiante')->findOrFail($id);
        $this->estudianteSeleccionadoId = $estudiante->id;
        $this->busquedaEstudiante = $estudiante->name;
    }

    public function matricularEstudiante(GestorMatriculas $gestor): void
    {
        abort_unless($this->puedeMatricular, 403);

        $this->validate(
            ['estudianteSeleccionadoId' => ['required', 'exists:users,id']],
            ['estudianteSeleccionadoId.required' => 'Busca y selecciona un estudiante.']
        );

        $estudiante = User::findOrFail($this->estudianteSeleccionadoId);
        $gestor->matricularManual($this->curso, $estudiante, auth()->user());

        $this->reset(['busquedaEstudiante', 'estudianteSeleccionadoId']);
        session()->flash('estado', "{$estudiante->name} fue matriculado. Se le notificó dentro de la plataforma y por correo.");
    }

    public function confirmarBaja(int $inscripcionId): void
    {
        $inscripcion = $this->curso->inscripciones()->findOrFail($inscripcionId);
        abort_unless($this->puedeDarDeBajaA($inscripcion), 403);

        $this->inscripcionBajaId = $inscripcion->id;
        $this->motivoBaja = '';
        $this->mostrarModalBaja = true;
    }

    public function ejecutarBaja(GestorMatriculas $gestor): void
    {
        $this->validate(['motivoBaja' => ['nullable', 'string', 'max:500']]);

        $inscripcion = $this->curso->inscripciones()->with('estudiante', 'curso')->findOrFail($this->inscripcionBajaId);
        abort_unless($this->puedeDarDeBajaA($inscripcion), 403);
        $gestor->darDeBaja($inscripcion, auth()->user(), $this->motivoBaja ?: null);

        $this->mostrarModalBaja = false;
        $this->reset(['inscripcionBajaId', 'motivoBaja']);
        session()->flash('estado', $inscripcion->esDeApiExterna()
            ? "Se desactivó el acceso local de {$inscripcion->estudiante?->name}. La matrícula del sistema externo no se modificó."
            : "{$inscripcion->estudiante?->name} fue dado de baja del curso. Su historial académico se conserva.");
    }

    public function render()
    {
        $inscripciones = $this->curso->inscripciones()
            ->with('estudiante', 'dadoDeBajaPor')
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo_local', true)->where('estado', 'activo'))
            ->when($this->filtroEstado === 'baja', fn ($q) => $q->where(fn ($q) => $q->where('estado', 'inactivo')->orWhere('activo_local', false)))
            ->when($this->filtroEstado === 'api', fn ($q) => $q->where('origen', 'api'))
            ->orderByDesc('fecha_inscripcion')
            ->paginate(20);

        $estudiantesBusqueda = collect();
        $busqueda = trim($this->busquedaEstudiante);

        if ($this->puedeMatricular && mb_strlen($busqueda) >= 2 && ! $this->estudianteSeleccionadoId) {
            $estudiantesBusqueda = User::role('estudiante')
                ->where('activo', true)
                ->whereNotIn('id', $this->curso->inscripcionesActivas()->select('estudiante_id'))
                ->where(fn ($q) => $q
                    ->whereLike('name', "%{$busqueda}%")
                    ->orWhereLike('email', "%{$busqueda}%")
                    ->orWhereLike('dni', "%{$busqueda}%")
                )
                ->orderBy('name')
                ->limit(8)
                ->get();
        }

        return view('livewire.cursos.gestion-matricula', [
            'inscripciones' => $inscripciones,
            'estudiantesBusqueda' => $estudiantesBusqueda,
            'totalActivos' => $this->curso->inscripcionesActivas()->count(),
            'totalBajas' => $this->curso->inscripciones()->where(fn ($q) => $q->where('estado', 'inactivo')->orWhere('activo_local', false))->count(),
            'totalApi' => $this->curso->inscripciones()->where('origen', 'api')->count(),
            'inscripcionBaja' => $this->inscripcionBajaId ? $this->curso->inscripciones()->with('estudiante')->find($this->inscripcionBajaId) : null,
        ])->title("Matrícula — {$this->curso->nombre}");
    }
}
