<?php

namespace App\Livewire\Mensajeria;

use App\Models\Curso;
use App\Models\Mensaje;
use App\Models\User;
use App\Notifications\NuevoMensaje;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Collection;
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
 */
#[Layout('layouts.app')]
class Bandeja extends Component
{
    use WithFileUploads, WithPagination;

    // 'recibidos' | 'enviados' | 'nuevo'
    public string $vista = 'recibidos';

    public ?int $mensajeAbiertoId = null;

    public ?int $destinatarioId = null;

    public ?int $cursoContextoId = null;

    public string $asunto = '';

    public string $cuerpo = '';

    public $adjunto = null;

    public bool $enviarATodos = false;

    public function mount(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['estudiante', 'profesor']), 403);
    }

    public function updatedCursoContextoId(): void
    {
        $this->destinatarioId = null;
        $this->enviarATodos = false;
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
            'cursoContextoId' => ['required', 'integer'],
            'destinatarioId' => ['nullable', 'integer', 'required_unless:enviarATodos,true'],
            'asunto' => ['required', 'string', 'max:255'],
            'cuerpo' => ['required', 'string', 'max:10000'],
            'adjunto' => SubidorArchivos::reglas(requerido: false),
        ], [
            'cursoContextoId.required' => 'Selecciona el curso del mensaje.',
            'destinatarioId.required_unless' => 'Selecciona el destinatario.',
            ...SubidorArchivos::mensajes('adjunto'),
        ]);

        $curso = $this->misCursos()->firstWhere('id', $this->cursoContextoId);
        abort_unless($curso, 403, 'No participas en ese curso.');

        if ($this->enviarATodos) {
            // RF-81: solo un profesor del curso envía avisos a todo el curso.
            abort_unless(auth()->user()->hasRole('profesor') && $curso->esDocente(auth()->user()), 403);
            $destinatarios = $this->contactosDe($curso)->pluck('id')->all();
        } else {
            abort_unless($this->contactosDe($curso)->contains('id', $this->destinatarioId), 403, 'No puedes escribir a este usuario.');
            $destinatarios = [$this->destinatarioId];
        }

        $adjuntoUrl = $this->adjunto ? $subidor->subir($this->adjunto, 'mensajes', 'adjunto') : null;

        foreach ($destinatarios as $destinatarioId) {
            $mensaje = Mensaje::create([
                'remitente_id' => auth()->id(),
                'destinatario_id' => $destinatarioId,
                'curso_id' => $curso->id,
                'asunto' => $this->asunto,
                'cuerpo' => $this->cuerpo,
                'archivo_url' => $adjuntoUrl,
            ]);

            User::find($destinatarioId)?->notify(new NuevoMensaje($mensaje));
        }

        $this->reset(['destinatarioId', 'cursoContextoId', 'asunto', 'cuerpo', 'adjunto', 'enviarATodos']);
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
        $this->cursoContextoId = $mensaje->curso_id;
        $this->asunto = str_starts_with($mensaje->asunto, 'Re:') ? $mensaje->asunto : 'Re: '.$mensaje->asunto;
        $this->enviarATodos = false;
        $this->destinatarioId = $mensaje->remitente_id === auth()->id() ? $mensaje->destinatario_id : $mensaje->remitente_id;
        $this->mensajeAbiertoId = null;
    }

    /**
     * Cursos en los que el usuario participa (matrícula activa o como profesor).
     *
     * @return Collection<int, Curso>
     */
    protected function misCursos(): Collection
    {
        $usuario = auth()->user();

        return $usuario->hasRole('profesor')
            ? Curso::dictadosPor($usuario)->orderBy('nombre')->get()
            : Curso::whereHas('inscripcionesActivas', fn ($q) => $q->where('estudiante_id', $usuario->id))
                ->visiblesParaEstudiantes()->orderBy('nombre')->get();
    }

    /**
     * RF-80: con quién puede escribir el usuario dentro de un curso.
     * - Profesor del curso: sus estudiantes activos.
     * - Estudiante: los profesores del curso y sus compañeros activos.
     *
     * @return Collection<int, User>
     */
    protected function contactosDe(Curso $curso): Collection
    {
        $estudiantes = User::whereIn('id', $curso->inscripcionesActivas()->select('estudiante_id'))
            ->where('activo', true)
            ->whereKeyNot(auth()->id())
            ->orderBy('name')
            ->get();

        if (auth()->user()->hasRole('profesor')) {
            return $estudiantes;
        }

        $docentes = $curso->docentes()->filter(fn (User $docente) => $docente->activo);

        return $docentes->concat($estudiantes)->values();
    }

    public function render()
    {
        $user = auth()->user();
        $misCursos = $this->misCursos();
        $cursoContexto = $this->cursoContextoId ? $misCursos->firstWhere('id', $this->cursoContextoId) : null;

        return view('livewire.mensajeria.bandeja', [
            'recibidos' => Mensaje::where('destinatario_id', $user->id)->with(['remitente', 'curso'])->latest()->paginate(15, pageName: 'recibidos'),
            'enviados' => Mensaje::where('remitente_id', $user->id)->with(['destinatario', 'curso'])->latest()->paginate(15, pageName: 'enviados'),
            'noLeidos' => Mensaje::where('destinatario_id', $user->id)->whereNull('leido_at')->count(),
            'mensajeAbierto' => $this->mensajeAbiertoId
                ? Mensaje::with(['remitente', 'destinatario', 'curso'])
                    ->where(fn ($q) => $q->where('destinatario_id', $user->id)->orWhere('remitente_id', $user->id))
                    ->find($this->mensajeAbiertoId)
                : null,
            'contactos' => $cursoContexto ? $this->contactosDe($cursoContexto) : collect(),
            'misCursos' => $misCursos,
        ])->title('Mensajería — LearnUp');
    }
}
