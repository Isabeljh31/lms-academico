<?php

namespace App\Livewire\PanelAdmin;

use App\Services\ConfiguracionPlataforma;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Configuracion extends Component
{
    public bool $botActivo = true;

    public function mount(ConfiguracionPlataforma $configuracion): void
    {
        $this->botActivo = $configuracion->botActivo();
    }

    public function alternarBot(ConfiguracionPlataforma $configuracion): void
    {
        $this->botActivo = ! $this->botActivo;
        $configuracion->establecerBotActivo($this->botActivo);

        session()->flash('estado', $this->botActivo
            ? 'El bot de sugerencias está activado en toda la plataforma.'
            : 'El bot de sugerencias está desactivado en toda la plataforma.');
    }

    public function render()
    {
        return view('livewire.panel-admin.configuracion');
    }
}
