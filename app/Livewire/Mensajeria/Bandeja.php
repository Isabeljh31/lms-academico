<?php

namespace App\Livewire\Mensajeria;

use App\Models\Curso;
use App\Models\Mensaje;
use App\Models\User;
use App\Notifications\NuevoMensaje;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * ERS RF-80: Mensajería privada Estudiante ↔ Profesor.
 * RF-81: Avisos del Profesor a su curso (mensaje a todos los inscritos).
 * RF-82: Soporte adjuntos.
 * RF-83: Notificación de mensaje nuevo.
 */
#[Layout('layouts.app')]
class Bandeja extends Component
{
    use WithPagination, WithFileUploads;

    // 'recibidos' | 'enviados' | 'nuevo'
    public string $vista = 'recibidos';
    public ?int $mensajeAbiertoId = null;

    // Formulario nuevo mensaje
    public ?int $destinatarioId = null;
    public ?int $cursoContextoId = null;
    public string $asunto = '';
    public string $cuerpo = '';
    public $adjunto = null;
    public bool $enviarATodos = false;
    // Si true, envía a todos los inscritos en el curso (RF-81)

    public function mount(): void
    {
        abort_unless(auth()->check(), 401);
    }

    public function abrirMensaje(int $id): void
    {
        $mensaje = Mensaje::findOrFail($id);
        abort_unless(
            $mensaje->destinatario_id === auth()->id() || $mensaje->remitente_id === auth()->id(),
            403
        );
        // Marcar como leído (RF-83)
        if ($mensaje->destinatario_id === auth()->id()) {
            $mensaje->marcarComoLeido();
        }
        $this->mensajeAbiertoId = $id;
    }

    public function enviar(): void
    {
        $this->validate([
            'destinatarioId'  => ['nullable', 'exists:users,id', 'required_without:enviarATodos'],
            'cursoContextoId' => ['nullable', 'exists:cursos,id'],
            'asunto'          => ['required', 'string', 'max:255'],
            'cuerpo'          => ['required', 'string', 'max:10000'],
            'adjunto'         => ['nullable', 'file', 'max:5120'],
        ]);

        $adjuntoUrl = null;
        if ($this->adjunto) {
            $path = $this->adjunto->store('mensajes', 'public');
            $adjuntoUrl = Storage::disk('public')->url($path);
        }

        $destinatarios = [];

        if ($this->enviarATodos && $this->cursoContextoId) {
            // RF-81: enviar a todos los inscritos en el curso
            $curso = Curso::findOrFail($this->cursoContextoId);
            abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);
            $destinatarios = $curso->inscripcionesActivas()
                ->pluck('estudiante_id')
                ->filter(fn ($id) => $id !== auth()->id())
                ->all();
        } elseif ($this->destinatarioId) {
            $destinatarios = [$this->destinatarioId];
        }

        foreach ($destinatarios as $destId) {
            $mensaje = Mensaje::create([
                'remitente_id'    => auth()->id(),
                'destinatario_id' => $destId,
                'curso_id'        => $this->cursoContextoId,
                'asunto'          => $this->asunto,
                'cuerpo'          => $this->cuerpo,
                'archivo_url'     => $adjuntoUrl,
            ]);

            // RF-83: notificar al destinatario
            User::find($destId)?->notify(new NuevoMensaje($mensaje));
        }

        $this->reset(['destinatarioId','cursoContextoId','asunto','cuerpo','adjunto','enviarATodos']);
        $this->vista = 'enviados';
        session()->flash('estado', count($destinatarios) . ' mensaje(s) enviado(s).');
    }

    public function responder(): void
    {
        if (!$this->mensajeAbiertoId) return;
        
        $mensaje = Mensaje::find($this->mensajeAbiertoId);
        if (!$mensaje) return;
        
        $this->vista = 'nuevo';
        $this->cursoContextoId = $mensaje->curso_id;
        $this->asunto = str_starts_with($mensaje->asunto, 'Re:') ? $mensaje->asunto : 'Re: ' . $mensaje->asunto;
        $this->enviarATodos = false;
        
        // Si estoy respondiendo, el destinatario es el remitente del original
        // (A menos que yo sea el remitente y esté abriendo un mensaje 'enviado', en cuyo caso podría ser el destinatario original, pero usualmente uno responde a los recibidos)
        $this->destinatarioId = $mensaje->remitente_id === auth()->id() ? $mensaje->destinatario_id : $mensaje->remitente_id;
        
        $this->mensajeAbiertoId = null;
    }

    public function render()
    {
        $user = auth()->user();

        $recibidos = Mensaje::where('destinatario_id', $user->id)
            ->with(['remitente', 'curso'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $enviados = Mensaje::where('remitente_id', $user->id)
            ->with(['destinatario', 'curso'])
            ->orderByDesc('created_at')
            ->paginate(15);

        $noLeidos = Mensaje::where('destinatario_id', $user->id)
            ->whereNull('leido_at')
            ->count();

        $mensajeAbierto = $this->mensajeAbiertoId
            ? Mensaje::with(['remitente','destinatario','curso'])->find($this->mensajeAbiertoId)
            : null;

        // Personas con quienes puede comunicarse, filtrado por curso
        $contactos = collect();
        if ($this->cursoContextoId) {
            if ($user->hasRole('estudiante')) {
                $profesorId = \App\Models\Curso::where('id', $this->cursoContextoId)->value('profesor_id');
                if ($profesorId) {
                    $contactos = User::where('id', $profesorId)->get();
                }
            } else {
                $contactos = User::whereHas('inscripciones', fn ($q) =>
                    $q->where('curso_id', $this->cursoContextoId)->where('activo_local', true)
                )->get();
            }
        }

        $misCursos = $user->hasRole('supervisor')
            ? \App\Models\Curso::orderBy('nombre')->get()
            : ($user->hasRole('profesor')
                ? $user->cursosComoProfesor()->orderBy('nombre')->get()
                : \App\Models\Curso::whereIn('id', $user->inscripciones()->pluck('curso_id'))->get());

        return view('livewire.mensajeria.bandeja', [
            'recibidos'      => $recibidos,
            'enviados'       => $enviados,
            'noLeidos'       => $noLeidos,
            'mensajeAbierto' => $mensajeAbierto,
            'contactos'      => $contactos,
            'misCursos'      => $misCursos,
        ])->title("Mensajería — LearnUp");
    }
}
