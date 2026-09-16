<?php

namespace App\Livewire\Usuarios;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $busqueda = '';

    public function mount(): void
    {
        Gate::authorize('verListado', User::class);
    }

    public function actualizarRol(int $usuarioId, string $rol): void
    {
        $usuario = User::findOrFail($usuarioId);

        Gate::authorize('actualizarRol', $usuario);

        if (! in_array($rol, array_column(Rol::cases(), 'value'), true)) {
            $this->addError('rol', 'Rol inválido.');

            return;
        }

        $usuario->syncRoles([$rol]);

        session()->flash('estado', "Rol de {$usuario->name} actualizado a ".ucfirst($rol).'.');
    }

    public function render()
    {
        $usuarios = User::query()
            ->when($this->busqueda, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$this->busqueda}%")
                ->orWhere('email', 'like', "%{$this->busqueda}%")
            ))
            ->with('roles')
            ->orderBy('name')
            ->paginate(10);

        return view('livewire.usuarios.index', [
            'usuarios' => $usuarios,
            'roles' => Rol::cases(),
        ]);
    }
}
