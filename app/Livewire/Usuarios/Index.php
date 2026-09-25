<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Gestión de usuarios (solo Supervisor).
 * ERS RF-03: crear cuentas con password temporal.
 * ERS RF-04: correo de bienvenida (pendiente integración SMTP).
 * ERS RF-15: activar/desactivar cuentas.
 * ERS RF-16: cambiar rol.
 * ERS RF-95: auditoría de acciones.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public bool $mostrarModalEditar = false;
    use WithPagination;

    public string $busqueda = '';
    public string $filtroRol = '';
    public string $filtroEstado = '';

    // Modal crear usuario (ERS RF-03)
    public bool $mostrarModalCrear = false;
    public string $nuevoNombre = '';
    public string $nuevoEmail = '';
    public string $nuevoRol = 'estudiante';

    // Modal confirmar desactivación
    public bool $mostrarModalDesactivar = false;
    public ?int $usuarioADesactivarId = null;

    public function mount(): void
    {
        Gate::authorize('verListado', User::class);
    }

    public function updatingBusqueda(): void { $this->resetPage(); }
    public function updatingFiltroRol(): void { $this->resetPage(); }
    public function updatingFiltroEstado(): void { $this->resetPage(); }

    // ==========================================
    // CREAR USUARIO (ERS RF-03, RF-04)
    // ==========================================

    public function crearUsuario(): void
    {
        Gate::authorize('verListado', User::class);

        $this->validate([
            'nuevoNombre' => ['required', 'string', 'max:255'],
            'nuevoEmail'  => ['required', 'email', 'max:255', 'unique:users,email'],
            'nuevoRol'    => ['required', 'string', 'in:' . implode(',', array_column(Rol::cases(), 'value'))],
        ]);

        // ERS RF-03: contraseña temporal aleatoria (mín. 12 caracteres)
        $passwordTemporal = Str::random(12);

        $usuario = User::create([
            'name'              => $this->nuevoNombre,
            'email'             => $this->nuevoEmail,
            'password'          => Hash::make($passwordTemporal),
            'email_verified_at' => now(),
            'activo'            => true,
            'primer_login'      => true,
        ]);
        $usuario->assignRole($this->nuevoRol);

        // TODO ERS RF-04: enviar correo de bienvenida con $passwordTemporal
        // Mail::to($usuario->email)->send(new BienvenidaMail($usuario, $passwordTemporal));

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'creacion_cuenta',
            descripcion: "Cuenta creada: {$usuario->name} ({$usuario->email}) con rol {$this->nuevoRol}",
            usuarioAfectado: $usuario
        );

        $this->reset(['nuevoNombre', 'nuevoEmail', 'nuevoRol', 'mostrarModalCrear']);
        session()->flash('estado', "Usuario '{$usuario->name}' creado. Password temporal: {$passwordTemporal}");
        session()->flash('password_temporal', $passwordTemporal);
        session()->flash('usuario_temporal_nombre', $usuario->name);
    }

    // ==========================================
    // CAMBIAR ROL (ERS RF-16)
    // ==========================================

    public function actualizarRol(int $usuarioId, string $rol): void
    {
        $usuario = User::findOrFail($usuarioId);
        Gate::authorize('actualizarRol', $usuario);

        if (!in_array($rol, array_column(Rol::cases(), 'value'), true)) {
            $this->addError('rol', 'Rol inválido.');
            return;
        }

        $rolAnterior = $usuario->getRoleNames()->first();
        $usuario->syncRoles([$rol]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'otro_admin',
            descripcion: "Rol cambiado: {$usuario->name} ({$usuario->email}) de {$rolAnterior} a {$rol}",
            usuarioAfectado: $usuario
        );

        session()->flash('estado', "Rol de {$usuario->name} actualizado a " . ucfirst($rol) . '.');
    }

    // ==========================================
    // ACTIVAR / DESACTIVAR CUENTA (ERS RF-15)
    // ==========================================

    public function confirmarDesactivar(int $usuarioId): void
    {
        Gate::authorize('verListado', User::class);
        abort_if(auth()->id() === $usuarioId, 403, 'No puedes desactivarte a ti mismo.');
        $this->usuarioADesactivarId = $usuarioId;
        $this->mostrarModalDesactivar = true;
    }

    public function desactivarCuenta(): void
    {
        Gate::authorize('verListado', User::class);
        $usuario = User::findOrFail($this->usuarioADesactivarId);
        abort_if(auth()->id() === $usuario->id, 403);

        $usuario->update(['activo' => false]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'desactivacion_cuenta',
            descripcion: "Cuenta desactivada: {$usuario->name} ({$usuario->email})",
            usuarioAfectado: $usuario
        );

        $this->mostrarModalDesactivar = false;
        $this->usuarioADesactivarId = null;
        session()->flash('estado', "Cuenta de {$usuario->name} desactivada.");
    }

    public function activarCuenta(int $usuarioId): void
    {
        Gate::authorize('verListado', User::class);
        $usuario = User::findOrFail($usuarioId);
        $usuario->update(['activo' => true]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'creacion_cuenta',
            descripcion: "Cuenta reactivada: {$usuario->name} ({$usuario->email})",
            usuarioAfectado: $usuario
        );

        session()->flash('estado', "Cuenta de {$usuario->name} reactivada.");
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        $query = User::with('roles')
            ->when($this->busqueda, fn ($q) => $q->where(
                fn ($q) => $q->where('name', 'like', "%{$this->busqueda}%")
                             ->orWhere('email', 'like', "%{$this->busqueda}%")
            ))
            ->when($this->filtroRol, fn ($q) => $q->role($this->filtroRol))
            ->when($this->filtroEstado === 'activo',    fn ($q) => $q->where('activo', true))
            ->when($this->filtroEstado === 'inactivo',  fn ($q) => $q->where('activo', false))
            ->when($this->filtroEstado === 'nuevo',     fn ($q) => $q->where('primer_login', true))
            ->orderBy('name');

        $usuarios = $query->paginate(15);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'roles'    => Rol::cases(),
        ]);
    }

    // ==========================================
    // EDITAR USUARIO (ERS RF-14)
    // ==========================================

    public function abrirEditar(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
        $usuario = \App\Models\User::findOrFail($id);
        $this->usuarioEditarId = $id;
        $this->editarNombre    = $usuario->name;
        $this->editarEmail     = $usuario->email;
        $this->mostrarModalEditar = true;
    }

    public function guardarEdicion(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->validate([
            'editarNombre' => ['required', 'string', 'max:255'],
            'editarEmail'  => ['required', 'email', 'max:255',
                \Illuminate\Validation\Rule::unique('users', 'email')->ignore($this->usuarioEditarId)],
        ]);

        $usuario = \App\Models\User::findOrFail($this->usuarioEditarId);
        $anterior = ['name' => $usuario->name, 'email' => $usuario->email];

        $usuario->update([
            'name'  => $this->editarNombre,
            'email' => $this->editarEmail,
        ]);

        \App\Models\BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'edicion_datos_usuario',
            descripcion: "Datos editados: {$anterior['name']} ({$anterior['email']}) → {$usuario->name} ({$usuario->email})",
            usuarioAfectado: $usuario
        );

        $this->mostrarModalEditar = false;
        $this->reset(['usuarioEditarId', 'editarNombre', 'editarEmail']);
        session()->flash('estado', "Datos de {$usuario->name} actualizados correctamente.");
    }

}