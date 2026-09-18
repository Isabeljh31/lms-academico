<?php

namespace App\Livewire\Admin\Comunicados;

use App\Models\Comunicado;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $titulo = '';
    public string $contenido = '';
    public string $prioridad = 'informativo'; // informativo, urgente
    public string $destinatario = 'profesores'; // profesores, estudiantes, todos

    public bool $mostrarFormulario = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('administrador'), 403);
    }

    public function crearComunicado(): void
    {
        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'contenido' => ['required', 'string', 'max:5000'],
            'prioridad' => ['required', 'in:informativo,urgente'],
            'destinatario' => ['required', 'in:profesores,estudiantes,todos'],
        ]);

        Comunicado::create([
            'user_id' => auth()->id(),
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'prioridad' => $this->prioridad,
            'destinatario' => $this->destinatario,
            'activo' => true,
        ]);

        $this->reset(['titulo', 'contenido', 'prioridad', 'destinatario', 'mostrarFormulario']);
        session()->flash('estado', 'Comunicado institucional publicado exitosamente.');
    }

    public function toggleActivo(int $comunicadoId): void
    {
        $comunicado = Comunicado::findOrFail($comunicadoId);
        $comunicado->update(['activo' => ! $comunicado->activo]);

        $estadoTxt = $comunicado->activo ? 'activado' : 'desactivado';
        session()->flash('estado', "Comunicado '{$comunicado->titulo}' {$estadoTxt}.");
    }

    public function eliminar(int $comunicadoId): void
    {
        $comunicado = Comunicado::findOrFail($comunicadoId);
        $comunicado->delete();

        session()->flash('estado', 'Comunicado eliminado del sistema.');
    }

    public function render()
    {
        $comunicados = Comunicado::with('autor')
            ->orderByDesc('created_at')
            ->paginate(10);

        return view('livewire.admin.comunicados.index', [
            'comunicados' => $comunicados,
        ]);
    }
}
