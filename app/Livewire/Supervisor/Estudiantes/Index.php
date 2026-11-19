<?php

namespace App\Livewire\Supervisor\Estudiantes;

use App\Enums\Rol;
use App\Livewire\Concerns\FiltraPorUbicacion;
use App\Livewire\Supervisor\Estudiantes\Concerns\GestionaEstudiante;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
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
    use FiltraPorUbicacion, GestionaEstudiante, WithPagination;

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

    /** Sede y facultad propias del estudiante nuevo. */
    public string $nuevoSedeId = '';

    public string $nuevoFacultadId = '';

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
            'nuevoApoderadoNombre', 'nuevoApoderadoCelular', 'nuevoSedeId', 'nuevoFacultadId',
        ]);
        $this->mostrarModalCrear = true;
    }

    /** Otra sede: la facultad elegida era de la anterior. */
    public function updatedNuevoSedeId(): void
    {
        $this->nuevoFacultadId = '';
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
                ...$this->reglasUbicacionEstudiante('nuevoSedeId', 'nuevoFacultadId'),
            ],
            [
                ...GestorCuentas::mensajesIdentidad('nuevoDni', 'nuevoEmail'),
                ...$this->mensajesUbicacionEstudiante('nuevoSedeId', 'nuevoFacultadId'),
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
            'sede_id' => $this->nuevoSedeId !== '' ? (int) $this->nuevoSedeId : null,
            'facultad_id' => $this->nuevoFacultadId !== '' ? (int) $this->nuevoFacultadId : null,
        ]);

        $this->mostrarModalCrear = false;
        $this->reset([
            'nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail',
            'nuevoAnioNacimiento', 'nuevoCorreoPersonal', 'nuevoCelular',
            'nuevoApoderadoNombre', 'nuevoApoderadoCelular', 'nuevoSedeId', 'nuevoFacultadId',
        ]);

        session()->flash('password_temporal', $resultado['password_temporal']);
        session()->flash('usuario_temporal_nombre', $usuario->name);
        session()->flash('correo_enviado', $resultado['correo_enviado']);
    }

    /**
     * Asistencia y rendimiento de todos los estudiantes del directorio, en lote
     * por curso (unas pocas consultas por curso en vez de varias por estudiante y curso).
     *
     * @param  Collection<int, User>  $estudiantes  con inscripciones activas y su curso
     * @return array{asistencia: array<int, array<int, float|null>>, rendimiento: array<int, array<int, float|null>>}
     */
    protected function riesgoPorCurso(Collection $estudiantes, CalculadoraAsistencia $asistencia, CalculadoraNotas $notas): array
    {
        $precalculado = ['asistencia' => [], 'rendimiento' => []];
        $cursos = $estudiantes->flatMap(fn (User $estudiante) => $estudiante->inscripciones->pluck('curso'))
            ->filter(fn ($curso) => $curso && ! $curso->estaFinalizado())
            ->unique('id');

        foreach ($cursos as $curso) {
            $ids = $estudiantes->filter(fn (User $estudiante) => $estudiante->inscripciones->contains('curso_id', $curso->id))
                ->pluck('id')->values();

            $precalculado['rendimiento'][$curso->id] = collect($notas->resumenDelCurso($curso, $ids))
                ->map(fn (array $resumen) => $resumen['rendimiento'])->all();

            if ($curso->usa_asistencia) {
                $precalculado['asistencia'][$curso->id] = $asistencia->porcentajesDelCurso($curso, $ids);
            }
        }

        return $precalculado;
    }

    public function render(CalculadoraAsistencia $asistencia, CalculadoraNotas $notas)
    {
        $busqueda = $this->normalizar(trim($this->busqueda));

        // Sede → facultad (presenciales), virtuales y remotos, o sin matrícula (FiltraPorUbicacion).
        $cursosDelListado = $this->cursosDeUbicacion();
        $matriculaActiva = fn ($q) => $q->where('estado', 'activo')->where('activo_local', true);

        $estudiantes = $this->faltaUbicacion() ? collect() : User::role(Rol::Estudiante->value)
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivo', fn ($q) => $q->where('activo', false))
            // Aparece por sus cursos de ese listado y, en presenciales, también por su sede y facultad propias.
            ->when($cursosDelListado, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('inscripciones', fn ($i) => $matriculaActiva($i)->whereIn('curso_id', $cursosDelListado))
                ->when($this->vistaUbicacion === 'presencial', fn ($q) => $q->orWhere(fn ($propia) => $propia
                    ->where('sede_id', (int) $this->sedeUbicacion)->where('facultad_id', (int) $this->facultadUbicacion)))))
            ->when($this->soloSinCursos(), fn ($q) => $q->whereDoesntHave('inscripciones', $matriculaActiva))
            ->with(['inscripciones' => fn ($q) => $q->where('estado', 'activo')->where('activo_local', true)->with('curso')])
            ->get()
            ->when($busqueda !== '', fn ($coll) => $coll->filter(fn (User $u) => $this->coincide($u->name, $busqueda)
                || $this->coincide($u->email, $busqueda)
                || $this->coincide($u->dni, $busqueda)
                || $this->coincide($u->codigo, $busqueda)
            ));

        $precalculado = $this->riesgoPorCurso($estudiantes, $asistencia, $notas);

        $filas = $estudiantes->map(function (User $estudiante) use ($asistencia, $notas, $precalculado) {
            $cursosActivos = $estudiante->inscripciones->pluck('curso')->filter(fn ($c) => $c && ! $c->estaFinalizado());
            $riesgo = $this->calcularRiesgo($estudiante, $cursosActivos, $asistencia, $notas, $precalculado);

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
        $filasDePagina = $filas->forPage($pagina, $porPagina)->values();
        $paginacion = new LengthAwarePaginator(
            $filasDePagina,
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
            'opcionesUbicacion' => $this->opcionesUbicacion(),
            'faltaUbicacion' => $this->faltaUbicacion(),
            'ubicacionActiva' => $this->ubicacionActiva(),
        ])->title('Estudiantes - LearnUp');
    }
}
