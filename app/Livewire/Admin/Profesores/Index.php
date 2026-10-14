<?php

namespace App\Livewire\Admin\Profesores;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Usuarios\GestorCuentas;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Directorio de profesores (Supervisor): alta, carga docente y asignación
 * de cursos. ERS RF-10, RF-23, RF-24, RF-96.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    // Modal de creación de nuevo docente
    public bool $mostrarModalCrear = false;

    public string $nuevoNombre = '';

    public string $nuevoEmail = '';

    // Modal para reasignar / vincular curso
    public bool $mostrarModalAsignar = false;

    #[Locked]
    public ?int $profesorSeleccionadoId = null;

    public ?int $cursoAAsignarId = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    /**
     * ERS RF-03/RF-04/RF-10: el alta de profesores sigue las mismas reglas que
     * cualquier cuenta (contraseña temporal, correo de bienvenida, auditoría).
     */
    public function crearProfesor(GestorCuentas $gestor): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->nuevoEmail = User::normalizarEmail($this->nuevoEmail);

        $this->validate([
            'nuevoNombre' => ['required', 'string', 'max:255'],
            'nuevoEmail' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
        ], [
            'nuevoEmail.unique' => 'Ya existe una cuenta con este correo.',
        ]);

        $resultado = $gestor->crear($this->nuevoNombre, $this->nuevoEmail, Rol::Profesor->value, auth()->user());

        $this->reset(['nuevoNombre', 'nuevoEmail', 'mostrarModalCrear']);
        session()->flash('estado', "Profesor \"{$resultado['usuario']->name}\" registrado. Contraseña temporal: {$resultado['password_temporal']}");
    }

    public function abrirModalAsignar(int $profesorId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->profesorSeleccionadoId = User::role(Rol::Profesor->value)->findOrFail($profesorId)->id;
        $this->cursoAAsignarId = null;
        $this->resetErrorBag();
        $this->mostrarModalAsignar = true;
    }

    /**
     * ERS RF-23/RF-24: asignar o cambiar el profesor de un curso.
     */
    public function asignarCurso(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->validate([
            'cursoAAsignarId' => ['required', 'exists:cursos,id'],
            'profesorSeleccionadoId' => ['required', 'exists:users,id'],
        ], [
            'cursoAAsignarId.required' => 'Selecciona un curso.',
        ]);

        $curso = Curso::findOrFail($this->cursoAAsignarId);
        $profesor = User::role(Rol::Profesor->value)->findOrFail($this->profesorSeleccionadoId);

        if ($curso->estaFinalizado()) {
            $this->addError('cursoAAsignarId', 'El curso está archivado (finalizado) y no admite cambios.');

            return;
        }

        $anterior = $curso->profesor?->name;
        $curso->update(['profesor_id' => $profesor->id]);
        // Si ya era profesor adicional, pasa a titular y deja de figurar como adicional.
        $curso->profesoresAdicionales()->detach($profesor->id);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'asignacion_profesor',
            descripcion: "Profesor asignado al curso \"{$curso->nombre}\".",
            usuarioAfectado: $profesor,
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $profesor->name,
        );

        $this->mostrarModalAsignar = false;
        $this->reset(['profesorSeleccionadoId', 'cursoAAsignarId']);
        session()->flash('estado', "Curso \"{$curso->nombre}\" asignado a {$profesor->name}.");
    }

    /**
     * Retira al profesor titular del curso. Si el curso tiene profesores
     * adicionales (RF-23), el primero pasa a ser titular. Si no, el curso queda
     * a nombre del Supervisor (profesor_id es obligatorio) hasta asignar otro
     * profesor; mientras tanto nadie puede gestionar su contenido ni sus notas.
     */
    public function desasignarCurso(int $cursoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $curso = Curso::findOrFail($cursoId);

        if ($curso->estaFinalizado()) {
            session()->flash('error', "El curso \"{$curso->nombre}\" está archivado y no admite cambios.");

            return;
        }

        $anterior = $curso->profesor?->name;
        $sucesor = $curso->profesoresAdicionales()->orderBy('curso_profesor.id')->first();

        if ($sucesor) {
            $curso->update(['profesor_id' => $sucesor->id]);
            $curso->profesoresAdicionales()->detach($sucesor->id);
        } else {
            $curso->update(['profesor_id' => auth()->id()]);
        }

        $nuevoResponsable = $sucesor ?? auth()->user();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'asignacion_profesor',
            descripcion: $sucesor
                ? "Profesor titular retirado del curso \"{$curso->nombre}\"; {$sucesor->name} pasa de profesor adicional a titular."
                : "Profesor retirado del curso \"{$curso->nombre}\"; el curso queda a cargo del Supervisor hasta asignar otro profesor.",
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $nuevoResponsable->name,
        );

        session()->flash('estado', $sucesor
            ? "Docente desvinculado del curso \"{$curso->nombre}\". {$sucesor->name} queda como profesor titular."
            : "Docente desvinculado del curso \"{$curso->nombre}\". Asigna otro profesor: mientras tanto nadie puede gestionar su contenido ni sus notas.");
    }

    public function render()
    {
        $profesores = User::role(Rol::Profesor->value)
            ->when($this->busqueda, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->busqueda}%")
                ->orWhere('email', 'like', "%{$this->busqueda}%")
            ))
            ->with(['cursosComoProfesor.tareas.entregas'])
            ->orderBy('name')
            ->paginate(8);

        $profesoresConMetricas = $profesores->through(function (User $prof) {
            $cursos = $prof->cursosComoProfesor;
            $entregas = $cursos->flatMap->tareas->flatMap->entregas;
            $totalEntregas = $entregas->count();
            $calificadas = $entregas->where('estado', 'calificado')->count();

            return [
                'usuario' => $prof,
                'cursos' => $cursos,
                'estudiantes_count' => Inscripcion::whereIn('curso_id', $cursos->pluck('id'))
                    ->where('estado', 'activo')
                    ->distinct('estudiante_id')
                    ->count('estudiante_id'),
                'tareas_count' => $cursos->flatMap->tareas->count(),
                'total_entregas' => $totalEntregas,
                'calificadas' => $calificadas,
                'pendientes' => $totalEntregas - $calificadas,
                'tasa_calificacion' => $totalEntregas > 0 ? (int) round(($calificadas / $totalEntregas) * 100) : 100,
                // Entregas sin calificar con más de 3 días de espera
                'atrasos_calificacion' => $entregas
                    ->where('estado', '!=', 'calificado')
                    ->filter(fn ($e) => $e->created_at < now()->subDays(3))
                    ->count(),
            ];
        });

        return view('livewire.admin.profesores.index', [
            'profesoresConMetricas' => $profesoresConMetricas,
            'paginacion' => $profesores,
            'totalProfesores' => User::role(Rol::Profesor->value)->count(),
            'totalCursos' => Curso::count(),
            'cursosSinProfesor' => Curso::whereDoesntHave('profesor', fn ($q) => $q->role(Rol::Profesor->value))->count(),
            'entregasTotalesPendientes' => Entrega::where('estado', '!=', 'calificado')->count(),
            'cursosDisponibles' => Curso::where('estado', '!=', 'archivado')->orderBy('nombre')->get(),
            'profesorSeleccionado' => $this->profesorSeleccionadoId ? User::find($this->profesorSeleccionadoId) : null,
        ])->title('Profesores — LearnUp');
    }
}
