<?php

namespace App\Livewire\Supervisor\Profesores;

use App\Enums\Rol;
use App\Livewire\Supervisor\Profesores\Concerns\GestionaProfesor;
use App\Models\Curso;
use App\Models\Entrega;
use App\Models\User;
use App\Services\Multimedia\SubidorArchivos;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Directorio de profesores (Supervisor): alta, carga docente y asignación
 * de cursos. ERS RF-10, RF-23, RF-24, RF-96.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use GestionaProfesor, WithFileUploads, WithPagination;

    public string $busqueda = '';

    // nombre | atrasos | cursos | reciente
    public string $orden = 'nombre';

    // '' (todos) | activo | inactivo
    public string $filtroEstado = '';

    public bool $mostrarCursosSinProfesor = false;

    // Modal de creación de nuevo docente
    public bool $mostrarModalCrear = false;

    public string $nuevoNombres = '';

    public string $nuevoApellidos = '';

    public string $nuevoDni = '';

    public string $nuevoEmail = '';

    public string $nuevaEspecialidad = '';

    public string $nuevoCelular = '';

    public string $nuevoAnioNacimiento = '';

    public string $nuevoCorreoPersonal = '';

    public string $nuevoGradoAcademico = '';

    public string $nuevaColegiatura = '';

    public string $nuevaFechaInicioLabores = '';

    public $nuevoCv = null;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updatingBusqueda(): void
    {
        $this->resetPage();
    }

    public function updatingFiltroEstado(): void
    {
        $this->resetPage();
    }

    public function updatingOrden(): void
    {
        $this->resetPage();
    }

    public function toggleCursosSinProfesor(): void
    {
        $this->mostrarCursosSinProfesor = ! $this->mostrarCursosSinProfesor;
    }

    /**
     * ERS RF-03/RF-04/RF-10: el alta de profesores sigue las mismas reglas que
     * cualquier cuenta (contraseña temporal, correo de bienvenida, auditoría).
     */
    public function crearProfesor(GestorCuentas $gestor, SubidorArchivos $subidor): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->nuevoEmail = User::normalizarEmail($this->nuevoEmail);
        $this->nuevoDni = trim($this->nuevoDni);
        $anioActual = (int) now()->format('Y');

        $this->validate(
            [
                ...GestorCuentas::reglasIdentidad('nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail'),
                'nuevaEspecialidad' => ['nullable', 'string', 'max:120'],
                'nuevoCelular' => ['nullable', 'regex:/^9\d{8}$/'],
                'nuevoAnioNacimiento' => ['required', 'integer', 'min:'.($anioActual - 100), 'max:'.($anioActual - 18)],
                'nuevoCorreoPersonal' => ['nullable', 'email', 'max:255'],
                'nuevoGradoAcademico' => ['nullable', 'string', 'max:120'],
                'nuevaColegiatura' => ['nullable', 'string', 'max:60'],
                'nuevaFechaInicioLabores' => ['nullable', 'date'],
                'nuevoCv' => SubidorArchivos::reglas(['pdf', 'doc', 'docx'], requerido: false),
            ],
            [
                ...GestorCuentas::mensajesIdentidad('nuevoDni', 'nuevoEmail'),
                'nuevoCelular.regex' => 'El celular debe tener 9 dígitos y empezar en 9.',
                'nuevoAnioNacimiento.required' => 'Indica el año de nacimiento.',
                'nuevoAnioNacimiento.min' => 'Revisa el año de nacimiento.',
                'nuevoAnioNacimiento.max' => 'El profesor debe ser mayor de edad.',
                ...SubidorArchivos::mensajes('nuevoCv'),
            ],
            ['nuevoNombres' => 'nombres', 'nuevoApellidos' => 'apellidos', 'nuevoDni' => 'DNI', 'nuevoEmail' => 'correo institucional', 'nuevaEspecialidad' => 'especialidad'],
        );

        $resultado = $gestor->crear(
            nombres: $this->nuevoNombres,
            apellidos: $this->nuevoApellidos,
            email: $this->nuevoEmail,
            rol: Rol::Profesor->value,
            ejecutor: auth()->user(),
            dni: $this->nuevoDni,
        );

        $cvPath = $this->nuevoCv ? $subidor->subir($this->nuevoCv, 'profesores/cv', 'nuevoCv', ['pdf', 'doc', 'docx']) : null;

        $resultado['usuario']->update([
            'especialidad' => $this->nuevaEspecialidad ?: null,
            'celular' => $this->nuevoCelular ?: null,
            'anio_nacimiento' => $this->nuevoAnioNacimiento ?: null,
            'correo_personal' => $this->nuevoCorreoPersonal ?: null,
            'grado_academico' => $this->nuevoGradoAcademico ?: null,
            'colegiatura' => $this->nuevaColegiatura ?: null,
            'fecha_inicio_labores' => $this->nuevaFechaInicioLabores ?: null,
            'cv_path' => $cvPath,
        ]);

        $this->reset([
            'nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail', 'nuevaEspecialidad',
            'nuevoCelular', 'nuevoAnioNacimiento', 'nuevoCorreoPersonal', 'nuevoGradoAcademico',
            'nuevaColegiatura', 'nuevaFechaInicioLabores', 'nuevoCv', 'mostrarModalCrear',
        ]);
        session()->flash('estado', "Profesor \"{$resultado['usuario']->name}\" registrado. Contraseña temporal: {$resultado['password_temporal']}");
    }

    public function abrirCrear(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $this->resetErrorBag();
        $this->reset([
            'nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail', 'nuevaEspecialidad',
            'nuevoCelular', 'nuevoAnioNacimiento', 'nuevoCorreoPersonal', 'nuevoGradoAcademico',
            'nuevaColegiatura', 'nuevaFechaInicioLabores', 'nuevoCv',
        ]);
        $this->mostrarModalCrear = true;
    }

    /**
     * Normaliza a minúsculas sin tildes para que la búsqueda no distinga
     * mayúsculas ni acentos (ej. "matematicas" encuentra "Matemática").
     */
    private function normalizar(?string $valor): string
    {
        return Str::of((string) $valor)->ascii()->lower()->toString();
    }

    /**
     * Compara en ambos sentidos para tolerar plurales simples
     * (ej. buscar "matematicas" encuentra el campo "Matemática").
     */
    private function coincide(?string $campo, string $busqueda): bool
    {
        $campo = $this->normalizar($campo);

        return $campo !== '' && (str_contains($campo, $busqueda) || str_contains($busqueda, $campo));
    }

    public function render()
    {
        $busqueda = $this->normalizar(trim($this->busqueda));

        $profesores = User::role(Rol::Profesor->value)
            ->when($this->filtroEstado !== '', fn ($q) => $q->where('activo', $this->filtroEstado === 'activo'))
            ->with(['cursosComoProfesor.tareas.entregas', 'cursosComoProfesor.inscripcionesActivas'])
            ->get()
            ->when($busqueda !== '', fn ($coll) => $coll->filter(fn (User $prof) => $this->coincide($prof->name, $busqueda)
                || $this->coincide($prof->email, $busqueda)
                || $this->coincide($prof->codigo, $busqueda)
                || $this->coincide($prof->especialidad, $busqueda)
            ));

        $totalProfesores = User::role(Rol::Profesor->value)->count();
        $totalProfesoresActivos = User::role(Rol::Profesor->value)->where('activo', true)->count();

        $filas = $profesores->map(function (User $prof) {
            $cursos = $prof->cursosComoProfesor;
            $entregas = $cursos->flatMap->tareas->flatMap->entregas;
            $totalEntregas = $entregas->count();
            $calificadas = $entregas->where('estado', 'calificado')->count();

            return [
                'usuario' => $prof,
                'cursos' => $cursos,
                'estudiantes_count' => $cursos->flatMap->inscripcionesActivas->pluck('estudiante_id')->unique()->count(),
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

        $filas = (match ($this->orden) {
            'atrasos' => $filas->sortByDesc('atrasos_calificacion'),
            'cursos' => $filas->sortByDesc(fn (array $f) => $f['cursos']->count()),
            'reciente' => $filas->sortByDesc(fn (array $f) => $f['usuario']->created_at),
            default => $filas->sortBy(fn (array $f) => mb_strtolower($f['usuario']->name)),
        })->values();

        $porPagina = 8;
        $pagina = $this->getPage();
        $paginacion = new LengthAwarePaginator(
            $filas->forPage($pagina, $porPagina)->values(),
            $filas->count(),
            $porPagina,
            $pagina,
            ['path' => request()->url(), 'pageName' => 'page', 'query' => request()->query()],
        );

        $cursosSinProfesor = Curso::whereDoesntHave('profesor', fn ($q) => $q->role(Rol::Profesor->value))
            ->where('estado', '!=', 'archivado')
            ->orderBy('nombre')
            ->get();

        return view('livewire.supervisor.profesores.index', [
            'profesoresConMetricas' => $paginacion,
            'paginacion' => $paginacion,
            'totalProfesores' => $totalProfesores,
            'totalProfesoresActivos' => $totalProfesoresActivos,
            'totalProfesoresInactivos' => $totalProfesores - $totalProfesoresActivos,
            'totalCursos' => Curso::count(),
            'cursosSinProfesorLista' => $cursosSinProfesor,
            'cursosSinProfesor' => $cursosSinProfesor->count(),
            'entregasTotalesPendientes' => Entrega::where('estado', '!=', 'calificado')->count(),
            'cursosDisponibles' => Curso::where('estado', '!=', 'archivado')->orderBy('nombre')->get(),
            'profesorSeleccionado' => $this->profesorSeleccionadoId ? User::find($this->profesorSeleccionadoId) : null,
            'cursoSeleccionado' => $this->cursoAAsignarId ? Curso::find($this->cursoAAsignarId) : null,
            'profesoresDisponibles' => User::role(Rol::Profesor->value)->where('activo', true)->orderBy('name')->get(),
        ])->title('Profesores - LearnUp');
    }
}
