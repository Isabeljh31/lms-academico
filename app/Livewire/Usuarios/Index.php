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

    public string $busqueda = '';

    public string $filtroRol = '';

    public string $filtroEstado = '';

    public string $filtroCursoId = '';

    // Crear usuario (RF-10)
    public bool $mostrarModalCrear = false;

    public string $nuevoNombres = '';

    public string $nuevoApellidos = '';

    public string $nuevoEmail = '';

    public string $nuevoRol = 'estudiante';

    // Editar usuario (RF-14)
    public bool $mostrarModalEditar = false;

    #[Locked]
    public ?int $usuarioEditarId = null;

    public string $editarNombre = '';

    public string $editarEmail = '';

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
        $this->reset(['nuevoNombres', 'nuevoApellidos', 'nuevoEmail']);
        $this->nuevoRol = 'estudiante';
        $this->mostrarModalCrear = true;
    }

    public function crearUsuario(GestorCuentas $gestor): void
    {
        Gate::authorize('verListado', User::class);

        $this->validate([
            'nuevoNombres' => ['required', 'string', 'max:120'],
            'nuevoApellidos' => ['required', 'string', 'max:120'],
            'nuevoEmail' => ['required', 'email', 'max:255', 'unique:users,email'],
            'nuevoRol' => ['required', Rule::in(GestorCuentas::ROLES_CREABLES)],
        ], [
            'nuevoEmail.unique' => 'Ya existe una cuenta con este correo.',
        ]);

        $resultado = $gestor->crear(
            nombre: trim($this->nuevoNombres).' '.trim($this->nuevoApellidos),
            email: $this->nuevoEmail,
            rol: $this->nuevoRol,
            ejecutor: auth()->user(),
        );

        $usuario = $resultado['usuario'];

        $this->mostrarModalCrear = false;
        $this->reset(['nuevoNombres', 'nuevoApellidos', 'nuevoEmail']);

        session()->flash('password_temporal', $resultado['password_temporal']);
        session()->flash('usuario_temporal_nombre', $usuario->name);
        session()->flash('correo_enviado', $resultado['correo_enviado']);
    }

    // ==========================================
    // CAMBIAR ROL
    // ==========================================

    public function actualizarRol(int $usuarioId, string $rol): void
    {
        $usuario = User::findOrFail($usuarioId);
        Gate::authorize('actualizarRol', $usuario);

        if (! in_array($rol, array_column(Rol::cases(), 'value'), true)) {
            $this->addError('rol', 'Rol inválido.');

            return;
        }

        $rolAnterior = $usuario->rolPrincipal();
        $usuario->syncRoles([$rol]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'cambio_rol',
            descripcion: "Rol cambiado: {$usuario->name} ({$usuario->email}).",
            usuarioAfectado: $usuario,
            valorAnterior: $rolAnterior,
            valorNuevo: $rol,
        );

        session()->flash('estado', "Rol de {$usuario->name} actualizado a ".Rol::from($rol)->etiqueta().'.');
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
        $this->editarNombre = $usuario->name;
        $this->editarEmail = $usuario->email;
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        Gate::authorize('verListado', User::class);

        $this->validate([
            'editarNombre' => ['required', 'string', 'max:255'],
            'editarEmail' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->usuarioEditarId)],
        ], [
            'editarEmail.unique' => 'Ya existe otra cuenta con este correo.',
        ]);

        $usuario = User::findOrFail($this->usuarioEditarId);
        $anterior = ['nombre' => $usuario->name, 'email' => $usuario->email];

        $usuario->update([
            'name' => $this->editarNombre,
            'email' => strtolower($this->editarEmail),
        ]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'edicion_datos_usuario',
            descripcion: "Datos editados de {$usuario->name}.",
            usuarioAfectado: $usuario,
            valorAnterior: $anterior,
            valorNuevo: ['nombre' => $usuario->name, 'email' => $usuario->email],
        );

        $this->mostrarModalEditar = false;
        $this->reset(['usuarioEditarId', 'editarNombre', 'editarEmail']);
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
                    ->orWhere('email', 'like', "%{$this->busqueda}%")
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
            ->orderBy('name')
            ->paginate(15);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Rol::cases(),
            'rolesCreables' => collect(Rol::cases())->filter(fn (Rol $rol) => in_array($rol->value, GestorCuentas::ROLES_CREABLES, true)),
            'cursos' => Curso::orderBy('nombre')->get(['id', 'nombre']),
            'usuarioADesactivar' => $this->usuarioADesactivarId ? User::find($this->usuarioADesactivarId) : null,
        ])->title('Usuarios — LearnUp');
    }
}
