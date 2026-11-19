<?php

namespace App\Livewire\Supervisor\Estudiantes;

use App\Enums\Rol;
use App\Livewire\Supervisor\Estudiantes\Concerns\GestionaEstudiante;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Directorio de estudiantes (solo Supervisor).
 * ERS RF-03/RF-04: alta con contraseña temporal y correo de bienvenida.
 * ERS RF-10: alta individual. ERS RF-14: edición. RF-15: activar/desactivar.
 * ERS RF-96: auditoría de cada acción.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use GestionaEstudiante, WithPagination;

    /**
     * Nombres de los campos tal como los ve el usuario en los mensajes de error.
     *
     * @var array<string, string>
     */
    protected const ATRIBUTOS = [
        'nuevoNombres' => 'nombres',
        'nuevoApellidos' => 'apellidos',
        'nuevoDni' => 'DNI',
        'nuevoEmail' => 'correo electrónico',
        'nuevoAnioNacimiento' => 'año de nacimiento',
        'nuevoCorreoPersonal' => 'correo personal',
        'nuevoCelular' => 'celular',
        'nuevoApoderadoNombre' => 'nombre del apoderado',
        'nuevoApoderadoCelular' => 'celular del apoderado',
    ];

    public string $busqueda = '';

    // nombre | riesgo | reciente
    public string $orden = 'nombre';

    // '' (todos) | activo | inactivo
    public string $filtroEstado = '';

    // Crear estudiante (RF-10)
    public bool $mostrarModalCrear = false;

    public string $nuevoNombres = '';

    public string $nuevoApellidos = '';

    public string $nuevoDni = '';

    public string $nuevoEmail = '';

    public string $nuevoAnioNacimiento = '';

    public string $nuevoCorreoPersonal = '';

    public string $nuevoCelular = '';

    public string $nuevoApoderadoNombre = '';

    public string $nuevoApoderadoCelular = '';

    public function mount(): void
    {
        Gate::authorize('verListado', User::class);
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

    // ==========================================
    // CREAR ESTUDIANTE (ERS RF-03, RF-04, RF-10)
    // ==========================================

    public function abrirCrear(): void
    {
        $this->resetErrorBag();
        $this->reset([
            'nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail',
            'nuevoAnioNacimiento', 'nuevoCorreoPersonal', 'nuevoCelular',
            'nuevoApoderadoNombre', 'nuevoApoderadoCelular',
        ]);
        $this->mostrarModalCrear = true;
    }

    public function crearUsuario(GestorCuentas $gestor): void
    {
        Gate::authorize('verListado', User::class);

        // Se valida el mismo valor que se guardará (correo en minúsculas, sin
        // espacios sobrantes), para que "unique" detecte "Ana@x.com" si ya existe "ana@x.com".
        $this->nuevoEmail = User::normalizarEmail($this->nuevoEmail);
        $this->nuevoDni = trim($this->nuevoDni);
        $anioActual = (int) now()->format('Y');

        $this->validate(
            [
                ...GestorCuentas::reglasIdentidad('nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail'),
                // Se permite cualquier edad (sin exigir mayoría de edad): la mayoría de estudiantes son menores.
                'nuevoAnioNacimiento' => ['nullable', 'integer', 'min:'.($anioActual - 100), 'max:'.$anioActual],
                'nuevoCorreoPersonal' => ['nullable', 'email', 'max:255'],
                'nuevoCelular' => ['nullable', 'regex:/^9\d{8}$/'],
                'nuevoApoderadoNombre' => ['nullable', 'string', 'max:120'],
                'nuevoApoderadoCelular' => ['nullable', 'regex:/^9\d{8}$/'],
            ],
            [
                ...GestorCuentas::mensajesIdentidad('nuevoDni', 'nuevoEmail'),
                'nuevoAnioNacimiento.max' => 'Revisa el año de nacimiento.',
                'nuevoCelular.regex' => 'El celular debe tener 9 dígitos y empezar en 9.',
                'nuevoApoderadoCelular.regex' => 'El celular del apoderado debe tener 9 dígitos y empezar en 9.',
            ],
            self::ATRIBUTOS,
        );

        $resultado = $gestor->crear(
            nombres: $this->nuevoNombres,
            apellidos: $this->nuevoApellidos,
            email: $this->nuevoEmail,
            rol: Rol::Estudiante->value,
            ejecutor: auth()->user(),
            dni: $this->nuevoDni,
        );

        $usuario = $resultado['usuario'];
        $usuario->update([
            'anio_nacimiento' => $this->nuevoAnioNacimiento ?: null,
            'correo_personal' => $this->nuevoCorreoPersonal ?: null,
            'celular' => $this->nuevoCelular ?: null,
            'apoderado_nombre' => $this->nuevoApoderadoNombre ?: null,
            'apoderado_celular' => $this->nuevoApoderadoCelular ?: null,
        ]);

        $this->mostrarModalCrear = false;
        $this->reset([
            'nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail',
            'nuevoAnioNacimiento', 'nuevoCorreoPersonal', 'nuevoCelular',
            'nuevoApoderadoNombre', 'nuevoApoderadoCelular',
        ]);

        session()->flash('password_temporal', $resultado['password_temporal']);
        session()->flash('usuario_temporal_nombre', $usuario->name);
        session()->flash('correo_enviado', $resultado['correo_enviado']);
    }

    public function render(CalculadoraAsistencia $asistencia, CalculadoraNotas $notas)
    {
        $busqueda = $this->normalizar(trim($this->busqueda));

        $estudiantes = User::role(Rol::Estudiante->value)
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivo', fn ($q) => $q->where('activo', false))
            ->with(['inscripciones' => fn ($q) => $q->where('estado', 'activo')->where('activo_local', true)->with('curso')])
            ->get()
            ->when($busqueda !== '', fn ($coll) => $coll->filter(fn (User $u) => $this->coincide($u->name, $busqueda)
                || $this->coincide($u->email, $busqueda)
                || $this->coincide($u->dni, $busqueda)
                || $this->coincide($u->codigo, $busqueda)
            ));

        // Cursos activos de cada estudiante y, para no consultar asistencia/notas
        // una vez por estudiante, los estudiantes agrupados por curso.
        $cursosActivosPorEstudiante = $estudiantes->mapWithKeys(
            fn (User $estudiante) => [$estudiante->id => $estudiante->inscripciones->pluck('curso')->filter(fn ($c) => $c && ! $c->estaFinalizado())->values()]
        );

        $estudiantesPorCurso = collect();
        $cursosPorId = collect();
        foreach ($cursosActivosPorEstudiante as $estudianteId => $cursosActivos) {
            foreach ($cursosActivos as $curso) {
                /** @var Curso $curso */
                $cursosPorId->put($curso->id, $curso);
                $estudiantesPorCurso->put($curso->id, $estudiantesPorCurso->get($curso->id, collect())->push($estudianteId));
            }
        }

        $asistenciaPorCurso = [];
        $rendimientoPorCurso = [];
        foreach ($cursosPorId as $cursoId => $curso) {
            $idsDelCurso = $estudiantesPorCurso->get($cursoId)->unique()->values();

            if ($curso->usa_asistencia) {
                $asistenciaPorCurso[$cursoId] = $asistencia->porcentajesDelCurso($curso, $idsDelCurso);
            }

            $rendimientoPorCurso[$cursoId] = collect($notas->resumenDelCurso($curso, $idsDelCurso))
                ->map(fn (array $resumen) => $resumen['rendimiento'])
                ->all();
        }

        $notaMinima = $notas->notaMinima();

        $filas = $estudiantes->map(function (User $estudiante) use ($cursosActivosPorEstudiante, $asistenciaPorCurso, $rendimientoPorCurso, $notaMinima) {
            $cursosActivos = $cursosActivosPorEstudiante[$estudiante->id];
            $riesgo = $this->calcularRiesgoDesdeLote($estudiante, $cursosActivos, $asistenciaPorCurso, $rendimientoPorCurso, $notaMinima);

            return [
                'usuario' => $estudiante,
                'cursos_count' => $cursosActivos->count(),
                'riesgo_nivel' => $riesgo['nivel'],
            ];
        });

        $filas = (match ($this->orden) {
            'riesgo' => $filas->sortBy(fn (array $f) => match ($f['riesgo_nivel']) {
                'Alto' => 0,
                'Medio' => 1,
                default => 2,
            }),
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

        $totalEstudiantes = User::role(Rol::Estudiante->value)->count();
        $totalActivos = User::role(Rol::Estudiante->value)->where('activo', true)->count();

        return view('livewire.supervisor.estudiantes.index', [
            'estudiantesConMetricas' => $paginacion,
            'paginacion' => $paginacion,
            'totalEstudiantes' => $totalEstudiantes,
            'totalEstudiantesActivos' => $totalActivos,
            'totalEstudiantesInactivos' => $totalEstudiantes - $totalActivos,
            'matriculasActivas' => Inscripcion::where('estado', 'activo')->where('activo_local', true)->count(),
            'totalEnRiesgo' => $filas->filter(fn (array $f) => $f['riesgo_nivel'] !== null)->count(),
            'totalPrimerIngreso' => User::role(Rol::Estudiante->value)->where('primer_login', true)->count(),
            'usuarioEditar' => $this->usuarioEditarId ? User::find($this->usuarioEditarId) : null,
            'rolesCreables' => collect(Rol::cases())->filter(fn (Rol $rol) => in_array($rol->value, GestorCuentas::ROLES_CREABLES, true)),
        ])->title('Estudiantes - LearnUp');
    }
}
