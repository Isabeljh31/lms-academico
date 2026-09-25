<?php
// ERS RF-03: registro público deshabilitado.
// Redirigir al login antes de que Livewire lo cargue.
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public function mount(): void
    {
        // ERS: El registro es solo por Supervisor o API. Sin acceso público.
        $this->redirect(route('login'), navigate: true);
    }
}; ?>

{{-- Esta vista no se renderiza nunca; el mount() siempre redirige. --}}
<div></div>
