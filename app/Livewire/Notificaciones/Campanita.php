<?php

namespace App\Livewire\Notificaciones;

use Livewire\Component;

class Campanita extends Component
{
    public bool $abierta = false;

    public function alternar(): void
    {
        $this->abierta = ! $this->abierta;
    }

    public function marcarLeida(string $id): void
    {
        auth()->user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function marcarTodasLeidas(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function render()
    {
        $usuario = auth()->user();

        return view('livewire.notificaciones.campanita', [
            'noLeidas' => $usuario->unreadNotifications()->count(),
            'notificaciones' => $usuario->notifications()->latest()->take(8)->get(),
        ]);
    }
}
