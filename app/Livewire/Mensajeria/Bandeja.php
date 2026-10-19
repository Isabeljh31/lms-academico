<?php

namespace App\Livewire\Mensajeria;

use App\Models\Curso;
use App\Models\Mensaje;
use App\Models\User;
use App\Notifications\NuevoMensaje;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * ERS RF-80: mensajes privados entre Estudiante y Profesor, y entre
 *            Estudiantes de un mismo curso (solo dentro de cursos comunes).
 * ERS RF-81: el Profesor puede enviar un aviso a todos los estudiantes de su curso.
 * ERS RF-82: bandeja con adjuntos (lista blanca de formatos, 10 MB).
 * ERS RF-83: notificación de mensaje nuevo.
 * La matriz de permisos no da mensajería al Supervisor (ruta con role:estudiante|profesor).
 * Bandeja exclusiva de un curso: el destino se fija por la ruta (cursos.mensajeria).
 */
#[Layout('layouts.app')]
class Bandeja extends Component
{
    use WithFileUploads, WithPagination;

    public Curso $curso;

    // 'recibidos' | 'enviados' | 'nuevo'
    public string $vista = 'recibidos';

    public ?int $mensajeAbiertoId = null;

    public ?int $destinatarioId = null;

    public string $asunto = '';

    public string $cuerpo = '';

    public $adjunto = null;

    public bool $enviarATodos = false;

    public function mount(Curso $curso): void
    {
        Gate::authorize('ver', $curso);

        $this->curso = $curso;
    }

    public function abrirMensaje(int $id): void
    {
        $mensaje = Mensaje::where(fn ($q) => $q->where('destinatario_id', auth()->id())->orWhere('remitente_id', auth()->id()))
            ->findOrFail($id);

        if ($mensaje->destinatario_id === auth()->id()) {
            $mensaje->marcarComoLeido();
        }

        $this->mensajeAbiertoId = $mensaje->id;
    }

    public function enviar(SubidorArchivos $subidor): void
    {
        $this->validate([
            'destinatarioId' => ['nullable', 'integer', 'required_unless:enviarATodos,true'],
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:10000'],
            'adjunto' => SubidorArchivos::reglas(requerido: false),
        ], [
            'destinatarioId.required_unless' => 'Selecciona el destinatario.',
            ...SubidorArchivos::mensajes('adjunto'),
        ]);

        if ($this->enviarATodos) {
            // RF-81: solo un profesor del curso envía avisos a todo el curso.
            abort_unless(auth()->user()->hasRole('profesor') && $this->curso->esDocente(auth()->user()), 403);
            $destinatarios = $this->contactosDe()->pluck('id')->all();
        } else {
            abort_unless($this->contactosDe()->contains('id', $this->destinatarioId), 403, 'No puedes escribir a este usuario.');
            $destinatarios = [$this->destinatarioId];
        }

        $adjuntoUrl = $this->adjunto ? $subidor->subir($this->adjunto, 'mensajes', 'adjunto') : null;

        foreach ($destinatarios as $destinatarioId) {
            $mensaje = Mensaje::create([
                'remitente_id' => auth()->id(),
                'destinatario_id' => $destinatarioId,
                'curso_id' => $this->curso->id,
                'asunto' => $this->asunto,
                'cuerpo' => $this->cuerpo,
                'archivo_url' => $adjuntoUrl,
            ]);

            User::find($destinatarioId)?->notify(new NuevoMensaje($mensaje));
        }

        $this->reset(['destinatarioId', 'asunto', 'cuerpo', 'adjunto', 'enviarATodos']);
        $this->vista = 'enviados';
        session()->flash('estado', count($destinatarios) === 1 ? 'Mensaje enviado.' : count($destinatarios).' mensajes enviados.');
    }

    public function responder(): void
    {
        $mensaje = $this->mensajeAbiertoId
            ? Mensaje::where(fn ($q) => $q->where('destinatario_id', auth()->id())->orWhere('remitente_id', auth()->id()))->find($this->mensajeAbiertoId)
            : null;

        if (! $mensaje) {
            return;
        }

        $this->vista = 'nuevo';
        $this->asunto = str_starts_with($mensaje->asunto, 'Re:') ? $mensaje->asunto : 'Re: '.$mensaje->asunto;
        $this->enviarATodos = false;
        $this->destinatarioId = $mensaje->remitente_id === auth()->id() ? $mensaje->destinatario_id : $mensaje->remitente_id;
        $this->mensajeAbiertoId = null;
    }

    /**
     * RF-80: con quién puede escribir el usuario dentro de este curso.
     * - Profesor del curso: sus estudiantes activos.
     * - Estudiante: los profesores del curso y sus compañeros activos.
     *
     * @return Collection<int, User>
     */
    protected function contactosDe(): Collection
    {
        $estudiantes = User::whereIn('id', $this->curso->inscripcionesActivas()->select('estudiante_id'))
            ->where('activo', true)
            ->whereKeyNot(auth()->id())
            ->orderBy('name')
            ->get();

        if (auth()->user()->hasRole('profesor')) {
            return $estudiantes;
        }

        $docentes = $this->curso->docentes()->filter(fn (User $docente) => $docente->activo);

        return $docentes->concat($estudiantes)->values();
    }

    public function render()
    {
        $user = auth()->user();

        return view('livewire.mensajeria.bandeja', [
            'recibidos' => Mensaje::where('destinatario_id', $user->id)->where('curso_id', $this->curso->id)->with('remitente')->latest()->paginate(15, pageName: 'recibidos'),
            'enviados' => Mensaje::where('remitente_id', $user->id)->where('curso_id', $this->curso->id)->with('destinatario')->latest()->paginate(15, pageName: 'enviados'),
            'noLeidos' => Mensaje::where('destinatario_id', $user->id)->where('curso_id', $this->curso->id)->whereNull('leido_at')->count(),
            'mensajeAbierto' => $this->mensajeAbiertoId
                ? Mensaje::with(['remitente', 'destinatario'])
                    ->where('curso_id', $this->curso->id)
                    ->where(fn ($q) => $q->where('destinatario_id', $user->id)->orWhere('remitente_id', $user->id))
                    ->find($this->mensajeAbiertoId)
                : null,
            'contactos' => $this->contactosDe(),
        ])->title("Mensajería · {$this->curso->nombre} — LearnUp");
    }
}
