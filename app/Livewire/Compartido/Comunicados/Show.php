<?php

namespace App\Livewire\Compartido\Comunicados;

use App\Models\Comunicado;
use App\Notifications\NuevoComunicado;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Detalle de un comunicado (institucional o de un curso), extensión fuera
 * del ERS: hasta ahora el contenido completo solo se veía recortado dentro
 * del panel o de la pestaña "Anuncios" del curso. Al abrirlo se marca como
 * leída la notificación correspondiente (NuevoComunicado), sin un campo de
 * "leído" aparte para no duplicar ese estado.
 */
#[Layout('layouts.app')]
class Show extends Component
{
    public Comunicado $comunicado;

    public function mount(Comunicado $comunicado): void
    {
        $usuario = auth()->user();

        if ($comunicado->curso_id !== null) {
            Gate::authorize('ver', $comunicado->curso);
        } elseif (! $usuario->hasRole('supervisor')) {
            abort_unless(
                $comunicado->estaPublicado() && Comunicado::query()->paraRol($usuario->getRoleNames()->first())->whereKey($comunicado->id)->exists(),
                404
            );
        }

        $this->comunicado = $comunicado->load('autor', 'curso');

        $this->marcarNotificacionLeida();
    }

    protected function marcarNotificacionLeida(): void
    {
        auth()->user()->unreadNotifications()
            ->where('type', NuevoComunicado::class)
            ->get()
            ->first(fn ($n) => (int) ($n->data['comunicado_id'] ?? null) === $this->comunicado->id)
            ?->markAsRead();
    }

    public function render()
    {
        return view('livewire.compartido.comunicados.show')
            ->title("{$this->comunicado->titulo} — LearnUp");
    }
}
