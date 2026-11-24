<?php

namespace App\Livewire\Compartido\Notificaciones;

use App\Notifications\CambioFechaQuiz;
use App\Notifications\CambioFechaTarea;
use App\Notifications\EntregaDevuelta;
use App\Notifications\MatriculadoEnCurso;
use App\Notifications\NotaQuizModificada;
use App\Notifications\NotaRubroRegistrada;
use App\Notifications\NuevaTareaAsignada;
use App\Notifications\NuevoComunicado;
use App\Notifications\NuevoContenidoPublicado;
use App\Notifications\NuevoDebatePublicado;
use App\Notifications\NuevoMensaje;
use App\Notifications\NuevoQuizPublicado;
use App\Notifications\TareaCalificada;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Listado completo de notificaciones del usuario (extensión fuera del ERS,
 * equivalente al historial de notificaciones de Blackboard). La campanita del
 * header solo muestra las últimas 8; esta pantalla pagina todo el historial.
 * Compartida entre los 3 roles: cada quien ve solo las suyas, vía
 * auth()->user()->notifications(), la misma relación Notifiable que usa la
 * campanita, así que no hace falta una policy aparte.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public bool $soloNoLeidas = false;

    /** Ícono y etiqueta por clase de notificación, para distinguirlas de un vistazo. */
    protected const TIPOS = [
        NuevaTareaAsignada::class => ['icono' => 'tarea', 'etiqueta' => 'Tarea'],
        CambioFechaTarea::class => ['icono' => 'calendario', 'etiqueta' => 'Cambio de fecha'],
        TareaCalificada::class => ['icono' => 'check-circulo', 'etiqueta' => 'Calificación'],
        EntregaDevuelta::class => ['icono' => 'refrescar', 'etiqueta' => 'Entrega devuelta'],
        NuevoQuizPublicado::class => ['icono' => 'quiz', 'etiqueta' => 'Quiz'],
        CambioFechaQuiz::class => ['icono' => 'calendario', 'etiqueta' => 'Cambio de fecha'],
        NotaQuizModificada::class => ['icono' => 'check-circulo', 'etiqueta' => 'Calificación'],
        NotaRubroRegistrada::class => ['icono' => 'check-circulo', 'etiqueta' => 'Calificación'],
        NuevoComunicado::class => ['icono' => 'megafono', 'etiqueta' => 'Comunicado'],
        NuevoContenidoPublicado::class => ['icono' => 'material', 'etiqueta' => 'Contenido'],
        NuevoDebatePublicado::class => ['icono' => 'chat', 'etiqueta' => 'Debate'],
        NuevoMensaje::class => ['icono' => 'sobre', 'etiqueta' => 'Mensaje'],
        MatriculadoEnCurso::class => ['icono' => 'libro', 'etiqueta' => 'Matrícula'],
    ];

    public function alternarFiltro(): void
    {
        $this->soloNoLeidas = ! $this->soloNoLeidas;
        $this->resetPage();
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

        $notificaciones = $usuario->notifications()
            ->when($this->soloNoLeidas, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(15);

        $filas = $notificaciones->getCollection()->map(fn ($notificacion) => [
            'id' => $notificacion->id,
            'titulo' => $notificacion->data['titulo'] ?? 'Notificación',
            'mensaje' => $notificacion->data['mensaje'] ?? '',
            'url' => $notificacion->data['url'] ?? '#',
            'leida' => $notificacion->read_at !== null,
            'fecha' => $notificacion->created_at,
            ...self::TIPOS[$notificacion->type] ?? ['icono' => 'campana', 'etiqueta' => 'Notificación'],
        ]);

        return view('livewire.compartido.notificaciones.index', [
            'filas' => $filas,
            'paginador' => $notificaciones,
            'noLeidas' => $usuario->unreadNotifications()->count(),
        ])->title('Notificaciones - LearnUp');
    }
}
