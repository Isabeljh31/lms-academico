<?php

namespace App\Livewire\BotSugerencias;

use App\Models\ConversacionBot;
use App\Models\Curso;
use App\Services\Bot\Contracts\ProveedorIA;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Widget extends Component
{
    public bool $abierto = false;

    public string $mensaje = '';

    /**
     * ERS RF-94: el curso de contexto lo fija el servidor al montar el
     * componente y no puede cambiarse desde el navegador.
     */
    #[Locked]
    public ?int $cursoId = null;

    public bool $enviando = false;

    public function mount(?Curso $curso = null): void
    {
        $curso ??= request()->route('curso');
        $this->cursoId = $curso instanceof Curso && Gate::allows('ver', $curso) ? $curso->id : null;
    }

    public function alternar(): void
    {
        $this->abierto = ! $this->abierto;
    }

    /**
     * Permite abrir el widget desde el acceso rápido del panel del
     * estudiante (App\Livewire\Estudiante\PanelInicio) sin acoplar ambos
     * componentes: solo escucha un evento del navegador.
     */
    #[On('abrir-bot')]
    public function abrirDesdeAccesoRapido(): void
    {
        $this->abierto = true;
    }

    public function getConversacionProperty(): ConversacionBot
    {
        return ConversacionBot::firstOrCreate(
            ['usuario_id' => auth()->id(), 'curso_id' => $this->cursoId],
            ['mensajes' => []]
        );
    }

    public function getCursoActualProperty(): ?Curso
    {
        $curso = $this->cursoId ? Curso::find($this->cursoId) : null;

        return $curso && Gate::allows('ver', $curso) ? $curso : null;
    }

    public function enviar(ProveedorIA $bot): void
    {
        abort_unless(app(ConfiguracionPlataforma::class)->botActivo(), 403, 'El bot está desactivado por el administrador.');

        $this->validate(['mensaje' => ['required', 'string', 'max:1000']]);

        $conversacion = ConversacionBot::firstOrCreate(
            ['usuario_id' => auth()->id(), 'curso_id' => $this->cursoActual?->id],
            ['mensajes' => []]
        );
        $historialPrevio = $conversacion->mensajes ?? [];

        $conversacion->agregarMensaje('usuario', $this->mensaje);
        $pregunta = $this->mensaje;
        $this->mensaje = '';

        $respuesta = $bot->responder($pregunta, $this->cursoActual, $historialPrevio, auth()->user());

        $conversacion->agregarMensaje('asistente', $respuesta);
    }

    public function render()
    {
        return view('livewire.bot-sugerencias.widget', [
            'mensajes' => $this->abierto ? $this->conversacion->mensajes ?? [] : [],
            'activo' => app(ProveedorIA::class)->activo(),
            'botHabilitado' => app(ConfiguracionPlataforma::class)->botActivo(),
        ]);
    }
}
