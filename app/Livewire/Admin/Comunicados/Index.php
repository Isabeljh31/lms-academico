<?php

namespace App\Livewire\Admin\Comunicados;

use App\Models\BitacoraAuditoria;
use App\Models\Comunicado;
use App\Notifications\NuevoComunicado;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ERS RF-87 / CU-22: el Supervisor emite comunicados dirigidos a
 * Estudiantes, Profesores o ambos, con título, descripción y fecha y hora de
 * publicación. RF-84: al publicarse se notifica a los destinatarios.
 * Los comunicados programados los publica el comando comunicados:publicar.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $titulo = '';

    public string $contenido = '';

    public string $prioridad = 'informativo';

    public string $destinatario = 'todos';

    public string $publicarEn = '';

    public bool $mostrarFormulario = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function crearComunicado(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'contenido' => ['required', 'string', 'max:5000'],
            'prioridad' => ['required', 'in:informativo,urgente'],
            'destinatario' => ['required', 'in:profesores,estudiantes,todos'],
            'publicarEn' => ['nullable', 'date', 'after:now'],
        ], [
            'titulo.required' => 'Escribe el título del comunicado.',
            'contenido.required' => 'Escribe el contenido del comunicado.',
            'publicarEn.after' => 'La fecha de publicación programada debe ser futura.',
        ]);

        $comunicado = Comunicado::create([
            'user_id' => auth()->id(),
            'titulo' => $this->titulo,
            'contenido' => $this->contenido,
            'prioridad' => $this->prioridad,
            'destinatario' => $this->destinatario,
            'activo' => true,
            'publicar_en' => $this->publicarEn ?: null,
            'notificado' => false,
        ]);

        if ($comunicado->estaPublicado()) {
            $this->notificar($comunicado);
        }

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'comunicado',
            descripcion: "Comunicado creado: \"{$comunicado->titulo}\" para {$comunicado->destinatario}"
                .($comunicado->publicar_en ? ' (programado para '.$comunicado->publicar_en->formatoFechaHora().')' : '').'.',
        );

        $this->reset(['titulo', 'contenido', 'prioridad', 'destinatario', 'publicarEn', 'mostrarFormulario']);
        session()->flash('estado', $comunicado->estaPublicado()
            ? 'Comunicado publicado y notificado a los destinatarios.'
            : 'Comunicado programado. Se publicará y notificará en la fecha indicada.');
    }

    public function toggleActivo(int $comunicadoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $comunicado = Comunicado::whereNull('curso_id')->findOrFail($comunicadoId);
        $comunicado->update(['activo' => ! $comunicado->activo]);

        if ($comunicado->estaPublicado() && ! $comunicado->notificado) {
            $this->notificar($comunicado);
        }

        session()->flash('estado', "Comunicado \"{$comunicado->titulo}\" ".($comunicado->activo ? 'activado.' : 'desactivado.'));
    }

    public function eliminar(int $comunicadoId): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $comunicado = Comunicado::whereNull('curso_id')->findOrFail($comunicadoId);
        $comunicado->delete();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'comunicado',
            descripcion: "Comunicado eliminado: \"{$comunicado->titulo}\".",
        );

        session()->flash('estado', 'Comunicado eliminado.');
    }

    protected function notificar(Comunicado $comunicado): void
    {
        Notification::send($comunicado->destinatarios(), new NuevoComunicado($comunicado));
        $comunicado->update(['notificado' => true]);
    }

    public function render()
    {
        return view('livewire.admin.comunicados.index', [
            'comunicados' => Comunicado::with('autor')->whereNull('curso_id')->latest()->paginate(10),
        ])->title('Comunicados — LearnUp');
    }
}
