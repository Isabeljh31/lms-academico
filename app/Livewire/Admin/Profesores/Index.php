<?php

namespace App\Livewire\Admin\Profesores;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\Inscripcion;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    // Modal de creación de nuevo docente
    public bool $mostrarModalCrear = false;
    public string $nuevoNombre = '';
    public string $nuevoEmail = '';
    public string $nuevoPassword = '';

    // Modal para reasignar / vincular curso
    public bool $mostrarModalAsignar = false;
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

    public function crearProfesor(): void
    {
        $this->validate([
            'nuevoNombre' => ['required', 'string', 'max:255'],
            'nuevoEmail' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'nuevoPassword' => ['required', 'string', 'min:8'],
        ]);

        $profesor = User::create([
            'name' => $this->nuevoNombre,
            'email' => $this->nuevoEmail,
            'password' => Hash::make($this->nuevoPassword),
            'email_verified_at' => now(),
        ]);

        $profesor->assignRole(Rol::Profesor->value);

        $this->reset(['nuevoNombre', 'nuevoEmail', 'nuevoPassword', 'mostrarModalCrear']);
        session()->flash('estado', "Profesor '{$profesor->name}' registrado exitosamente con credenciales activas.");
    }

    public function abrirModalAsignar(int $profesorId): void
    {
        $this->profesorSeleccionadoId = $profesorId;
        $this->cursoAAsignarId = null;
        $this->mostrarModalAsignar = true;
    }

    public function asignarCurso(): void
    {
        $this->validate([
            'cursoAAsignarId' => ['required', 'exists:cursos,id'],
            'profesorSeleccionadoId' => ['required', 'exists:users,id'],
        ]);

        $curso = Curso::findOrFail($this->cursoAAsignarId);
        $profesor = User::findOrFail($this->profesorSeleccionadoId);

        $curso->update(['profesor_id' => $profesor->id]);

        $this->mostrarModalAsignar = false;
        $this->reset(['profesorSeleccionadoId', 'cursoAAsignarId']);
        session()->flash('estado', "Curso '{$curso->nombre}' asignado a {$profesor->name}.");
    }

    public function desasignarCurso(int $cursoId): void
    {
        $curso = Curso::findOrFail($cursoId);
        // Si se desasigna, se transfiere temporalmente al administrador para no dejarlo huérfano
        $curso->update(['profesor_id' => auth()->id()]);
        session()->flash('estado', "Docente desvinculado del curso '{$curso->nombre}'. El curso fue tomado en custodia administrativa.");
    }

    public function render()
    {
        $query = User::role('profesor')
            ->when($this->busqueda, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->busqueda}%")
                ->orWhere('email', 'like', "%{$this->busqueda}%")
            ))
            ->with(['cursosComoProfesor.tareas.entregas', 'cursosComoProfesor.inscripciones'])
            ->orderBy('name');

        $profesores = $query->paginate(8);

        // Transformar métricas por docente
        $profesoresConMetricas = $profesores->through(function (User $prof) {
            $cursos = $prof->cursosComoProfesor;
            $cursoIds = $cursos->pluck('id');

            $estudiantesCount = Inscripcion::whereIn('curso_id', $cursoIds)
                ->distinct('estudiante_id')
                ->count();

            $tareas = $cursos->flatMap->tareas;
            $entregas = $tareas->flatMap->entregas;

            $totalEntregas = $entregas->count();
            $calificadas = $entregas->where('estado', 'calificado')->count();
            $pendientes = $entregas->where('estado', '!=', 'calificado')->count();
            $tasaCalificacion = $totalEntregas > 0 ? round(($calificadas / $totalEntregas) * 100) : 100;

            // Alerta si hay entregas sin calificar con más de 3 días de espera
            $atrasosCalificacion = $entregas
                ->where('estado', '!=', 'calificado')
                ->filter(fn ($e) => $e->created_at < now()->subDays(3))
                ->count();

            return [
                'usuario' => $prof,
                'cursos' => $cursos,
                'estudiantes_count' => $estudiantesCount,
                'tareas_count' => $tareas->count(),
                'total_entregas' => $totalEntregas,
                'calificadas' => $calificadas,
                'pendientes' => $pendientes,
                'tasa_calificacion' => $tasaCalificacion,
                'atrasos_calificacion' => $atrasosCalificacion,
            ];
        });

        // Métricas globales del sistema
        $totalProfesores = User::role('profesor')->count();
        $totalCursos = Curso::count();
        $cursosSinProfesor = Curso::where('profesor_id', auth()->id())->count();
        $entregasTotalesPendientes = Entrega::where('estado', '!=', 'calificado')->count();

        // Cursos disponibles para asignar
        $cursosDisponibles = Curso::orderBy('nombre')->get();

        return view('livewire.admin.profesores.index', [
            'profesoresConMetricas' => $profesoresConMetricas,
            'paginacion' => $profesores,
            'totalProfesores' => $totalProfesores,
            'totalCursos' => $totalCursos,
            'cursosSinProfesor' => $cursosSinProfesor,
            'entregasTotalesPendientes' => $entregasTotalesPendientes,
            'cursosDisponibles' => $cursosDisponibles,
        ]);
    }
}
