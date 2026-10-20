<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\User;
use App\Services\Usuarios\GestorCuentas;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestión de usuarios (solo Supervisor).
 * ERS RF-03/RF-04: alta con contraseña temporal y correo de bienvenida.
 * ERS RF-10: alta individual (nombres, apellidos, correo, rol).
 * ERS RF-14: edición de datos. RF-15: activar/desactivar. RF-16: filtros.
 * ERS RF-96: auditoría de cada acción.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

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
        'nuevoRol' => 'rol',
        'editarNombres' => 'nombres',
        'editarApellidos' => 'apellidos',
        'editarDni' => 'DNI',
        'editarEmail' => 'correo electrónico',
        'editarRol' => 'rol',
    ];

    public string $busqueda = '';

    public string $filtroRol = '';

    public string $filtroEstado = '';

    public string $filtroCursoId = '';

    // Crear usuario (RF-10)
    public bool $mostrarModalCrear = false;

    public string $nuevoNombres = '';

    public string $nuevoApellidos = '';

    public string $nuevoDni = '';

    public string $nuevoEmail = '';

    public string $nuevoRol = 'estudiante';

    // Editar usuario (RF-14)
    public bool $mostrarModalEditar = false;

    #[Locked]
    public ?int $usuarioEditarId = null;

    public string $editarNombres = '';

    public string $editarApellidos = '';

    public string $editarDni = '';

    public string $editarEmail = '';

    /** Solo se puede corregir si la cuenta aún no tiene historial (ver UsuarioPolicy::corregirRol). */
    public string $editarRol = '';

    // Confirmar desactivación (RNF-09)
    public bool $mostrarModalDesactivar = false;

    #[Locked]
    public ?int $usuarioADesactivarId = null;

    public function mount(): void
    {
        Gate::authorize('verListado', User::class);
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['busqueda', 'filtroRol', 'filtroEstado', 'filtroCursoId'], true)) {
            $this->resetPage();
        }
    }

    // ==========================================
    // CREAR USUARIO (ERS RF-03, RF-04, RF-10)
    // ==========================================

    public function abrirCrear(): void
    {
        $this->resetErrorBag();
        $this->reset(['nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail']);
        $this->nuevoRol = 'estudiante';
        $this->mostrarModalCrear = true;
    }

    public function crearUsuario(GestorCuentas $gestor): void
    {
        Gate::authorize('verListado', User::class);

        // Se valida el mismo valor que se guardará (correo en minúsculas, sin
        // espacios sobrantes), para que "unique" detecte "Ana@x.com" si ya existe "ana@x.com".
        $this->nuevoEmail = User::normalizarEmail($this->nuevoEmail);
        $this->nuevoDni = trim($this->nuevoDni);

        $this->validate(
            [
                ...GestorCuentas::reglasIdentidad('nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail'),
                'nuevoRol' => ['required', Rule::in(GestorCuentas::ROLES_CREABLES)],
            ],
            GestorCuentas::mensajesIdentidad('nuevoDni', 'nuevoEmail'),
            self::ATRIBUTOS,
        );

        $resultado = $gestor->crear(
            nombres: $this->nuevoNombres,
            apellidos: $this->nuevoApellidos,
            email: $this->nuevoEmail,
            rol: $this->nuevoRol,
            ejecutor: auth()->user(),
            dni: $this->nuevoDni,
        );

        $usuario = $resultado['usuario'];

        $this->mostrarModalCrear = false;
        $this->reset(['nuevoNombres', 'nuevoApellidos', 'nuevoDni', 'nuevoEmail']);

        session()->flash('password_temporal', $resultado['password_temporal']);
        session()->flash('usuario_temporal_nombre', $usuario->name);
        session()->flash('correo_enviado', $resultado['correo_enviado']);
    }

    // ==========================================
    // EDITAR USUARIO (ERS RF-14)
    // ==========================================

    public function abrirEditar(int $id): void
    {
        Gate::authorize('verListado', User::class);

        $usuario = User::findOrFail($id);
        $this->resetErrorBag();
        $this->usuarioEditarId = $usuario->id;
        $this->editarNombres = (string) $usuario->nombres;
        $this->editarApellidos = (string) $usuario->apellidos;
        $this->editarDni = (string) $usuario->dni;
        $this->editarEmail = $usuario->email;
        $this->editarRol = (string) $usuario->rolPrincipal();
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        Gate::authorize('verListado', User::class);

        $this->editarEmail = User::normalizarEmail($this->editarEmail);
        $this->editarDni = trim($this->editarDni);

        $this->validate(
            [
                ...GestorCuentas::reglasIdentidad('editarNombres', 'editarApellidos', 'editarDni', 'editarEmail', $this->usuarioEditarId),
            ],
            [
                ...GestorCuentas::mensajesIdentidad('editarDni', 'editarEmail'),
                'editarDni.unique' => 'Ya existe otra cuenta con este DNI.',
                'editarEmail.unique' => 'Ya existe otra cuenta con este correo.',
            ],
            self::ATRIBUTOS,
        );

        $usuario = User::findOrFail($this->usuarioEditarId);
        $rolAnterior = $usuario->rolPrincipal();
        $corrigeRol = $this->editarRol !== '' && $this->editarRol !== $rolAnterior;

        if ($corrigeRol) {
            $this->validate(['editarRol' => [Rule::in(GestorCuentas::ROLES_CREABLES)]]);

            if (Gate::denies('corregirRol', $usuario)) {
                $this->addError('editarRol', 'El rol ya no se puede cambiar: la cuenta tiene historial académico (cursos, matrículas, entregas o notas).');

                return;
            }
        }

        $campos = ['nombres', 'apellidos', 'dni', 'email'];
        $anterior = $usuario->only($campos);

        $usuario->update([
            'nombres' => $this->editarNombres,
            'apellidos' => $this->editarApellidos,
            'dni' => $this->editarDni,
            'email' => $this->editarEmail,
        ]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'edicion_datos_usuario',
            descripcion: "Datos editados de {$usuario->name}.",
            usuarioAfectado: $usuario,
            valorAnterior: $anterior,
            valorNuevo: $usuario->only($campos),
        );

        if ($corrigeRol) {
            $usuario->syncRoles([$this->editarRol]);

            BitacoraAuditoria::registrarAccionAdmin(
                ejecutor: auth()->user(),
                tipoEvento: 'cambio_rol',
                descripcion: "Rol corregido (cuenta sin historial académico): {$usuario->name} ({$usuario->email}).",
                usuarioAfectado: $usuario,
                valorAnterior: $rolAnterior,
                valorNuevo: $this->editarRol,
            );
        }

        $this->mostrarModalEditar = false;
        $this->reset(['usuarioEditarId', 'editarNombres', 'editarApellidos', 'editarDni', 'editarEmail', 'editarRol']);
        session()->flash('estado', "Datos de {$usuario->name} actualizados correctamente.");
    }

    // ==========================================
    // ACTIVAR / DESACTIVAR CUENTA (ERS RF-15, RF-17)
    // ==========================================

    public function confirmarDesactivar(int $usuarioId): void
    {
        Gate::authorize('verListado', User::class);
        abort_if(auth()->id() === $usuarioId, 403, 'No puedes desactivar tu propia cuenta.');

        $this->usuarioADesactivarId = $usuarioId;
        $this->mostrarModalDesactivar = true;
    }

    public function desactivarCuenta(): void
    {
        Gate::authorize('verListado', User::class);
        $usuario = User::findOrFail($this->usuarioADesactivarId);
        abort_if(auth()->id() === $usuario->id, 403, 'No puedes desactivar tu propia cuenta.');

        $usuario->update(['activo' => false]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'desactivacion_cuenta',
            descripcion: "Cuenta desactivada: {$usuario->name} ({$usuario->email}). El historial académico se conserva.",
            usuarioAfectado: $usuario
        );

        $this->mostrarModalDesactivar = false;
        $this->usuarioADesactivarId = null;
        session()->flash('estado', "Cuenta de {$usuario->name} desactivada. Su historial académico se conserva.");
    }

    public function eliminarCuenta(int $usuarioId, GestorCuentas $gestor): void
    {
        $gestor->eliminar(User::findOrFail($usuarioId));
        $this->resetErrorBag();
        $this->resetPage();
        session()->flash('estado', 'Cuenta eliminada correctamente.');
    }

    public function activarCuenta(int $usuarioId): void
    {
        Gate::authorize('verListado', User::class);
        $usuario = User::findOrFail($usuarioId);
        $usuario->update(['activo' => true]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'reactivacion_cuenta',
            descripcion: "Cuenta reactivada: {$usuario->name} ({$usuario->email}).",
            usuarioAfectado: $usuario
        );

        session()->flash('estado', "Cuenta de {$usuario->name} reactivada.");
    }

    public function render()
    {
        $usuarios = User::with('roles')
            ->when($this->busqueda, fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$this->busqueda}%")
                    ->orWhere('email', 'like', '%'.mb_strtolower($this->busqueda).'%')
                    ->orWhere('dni', 'like', "%{$this->busqueda}%")
            ))
            ->when($this->filtroRol, fn ($q) => $q->role($this->filtroRol))
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivo', fn ($q) => $q->where('activo', false))
            ->when($this->filtroEstado === 'nuevo', fn ($q) => $q->where('primer_login', true))
            ->when($this->filtroCursoId, fn ($q) => $q->where(fn ($q) => $q
                ->whereHas('inscripciones', fn ($i) => $i->where('curso_id', $this->filtroCursoId))
                ->orWhereHas('cursosComoProfesor', fn ($c) => $c->whereKey($this->filtroCursoId))
                ->orWhereHas('cursosComoProfesorAdicional', fn ($c) => $c->whereKey($this->filtroCursoId))
            ))
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(15);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Rol::cases(),
            'rolesCreables' => collect(Rol::cases())->filter(fn (Rol $rol) => in_array($rol->value, GestorCuentas::ROLES_CREABLES, true)),
            'cursos' => Curso::orderBy('nombre')->get(['id', 'nombre']),
            'usuarioADesactivar' => $this->usuarioADesactivarId ? User::find($this->usuarioADesactivarId) : null,
            'usuarioEditar' => $this->usuarioEditarId ? User::find($this->usuarioEditarId) : null,
        ])->title('Usuarios — LearnUp');
    }
}
