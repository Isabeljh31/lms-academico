<?php

namespace App\Livewire\Compartido\Tareas;

use App\Models\BitacoraAuditoria;
use App\Models\Entrega;
use App\Models\Grupo;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\EntregaDevuelta;
use App\Notifications\TareaCalificada;
use App\Services\ConfiguracionPlataforma;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Detalle de una tarea.
 * - Estudiante (RF-42, RF-43): entrega archivo, respuesta escrita y/o enlace;
 *   reentrega mientras tenga intentos o si el profesor la devolvió.
 * - Profesor titular (RF-49): revisa, comenta, califica o devuelve. Toda
 *   modificación de una nota ya registrada queda en la auditoría (RF-95).
 */
#[Layout('layouts.app')]
class Detalle extends Component
{
    use WithFileUploads;

    public Tarea $tarea;

    public ?UploadedFile $archivo = null;

    public string $comentario = '';

    public string $respuestaTexto = '';

    public string $enlace = '';

    /** @var array<int, string> */
    public array $notas = [];

    /** @var array<int, string> */
    public array $feedbacks = [];

    public function mount(Tarea $tarea): void
    {
        Gate::authorize('ver', $tarea);
        $this->tarea = $tarea;
    }

    public function getPuedeGestionarProperty(): bool
    {
        return Gate::allows('gestionar', $this->tarea);
    }

    public function getPuedeCalificarProperty(): bool
    {
        return Gate::allows('calificar', $this->tarea->curso) && ! $this->tarea->curso->estaFinalizado();
    }

    public function getMiEntregaProperty(): ?Entrega
    {
        return $this->tarea->entregaDe(auth()->user());
    }

    public function getMiEstadoProperty(): string
    {
        return $this->tarea->estadoPara($this->miEntrega);
    }

    public function getMiGrupoProperty(): ?Grupo
    {
        if (! $this->tarea->esGrupal()) {
            return null;
        }

        return auth()->user()->grupoEnCurso($this->tarea->curso);
    }

    /**
     * El estudiante puede volver a entregar si el profesor devolvió la
     * entrega, o si aún tiene intentos y la entrega no fue calificada.
     */
    public function getPuedeReentregarProperty(): bool
    {
        $entrega = $this->miEntrega;

        if (! $entrega || $entrega->estado === 'calificado') {
            return false;
        }

        return $entrega->estado === 'devuelto'
            || $entrega->intentos_usados < $this->tarea->intentos_permitidos;
    }

    public function getNotaMinimaProperty(): float
    {
        return app(ConfiguracionPlataforma::class)->notaMinimaAprobatoria();
    }

    // ==========================================
    // ENTREGA DEL ESTUDIANTE (RF-42)
    // ==========================================

    public function entregar(SubidorArchivos $subidor): void
    {
        Gate::authorize('crear', [Entrega::class, $this->tarea]);

        $entregaPrevia = $this->miEntrega;

        if ($entregaPrevia && ! $this->puedeReentregar) {
            $this->addError('archivo', $entregaPrevia->estado === 'calificado'
                ? 'Tu entrega ya fue calificada.'
                : 'Ya usaste todos los intentos de entrega de esta tarea.');

            return;
        }

        if ($this->tarea->esGrupal() && ! $this->miGrupo) {
            $this->addError('archivo', 'Todavía no perteneces a ningún grupo en este curso. Pide a tu profesor que te agregue a uno antes de entregar.');

            return;
        }

        $this->validate([
            'archivo' => SubidorArchivos::reglas($this->tarea->formatosPermitidos() ?: null, requerido: false),
            'respuestaTexto' => ['nullable', 'string', 'max:10000'],
            'enlace' => ['nullable', 'url:http,https', 'max:500'],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ], [
            'enlace.url' => 'El enlace debe empezar con http:// o https://.',
            ...SubidorArchivos::mensajes('archivo'),
        ]);

        if (! $this->archivo && trim($this->respuestaTexto) === '' && trim($this->enlace) === '') {
            $this->addError('archivo', 'Adjunta un archivo, escribe una respuesta o agrega un enlace.');

            return;
        }

        $url = $this->archivo
            ? $subidor->subir($this->archivo, 'entregas', 'archivo', $this->tarea->formatosPermitidos() ?: null)
            : null;

        $datos = [
            'archivo_url' => $url ?? ($entregaPrevia?->archivo_url),
            'respuesta_texto' => $this->respuestaTexto ?: null,
            'enlace' => $this->enlace ?: null,
            'comentario' => $this->comentario ?: null,
            'estado' => 'entregado',
            'nota' => null,
            'intentos_usados' => ($entregaPrevia?->intentos_usados ?? 0) + 1,
        ];

        DB::transaction(function () use ($datos) {
            // Una entrega grupal crea/actualiza una fila por cada integrante del grupo.
            $miembros = $this->tarea->esGrupal() ? $this->miGrupo->estudiantes : collect([auth()->user()]);

            foreach ($miembros as $miembro) {
                $this->tarea->entregas()->updateOrCreate(
                    ['estudiante_id' => $miembro->id],
                    [...$datos, 'grupo_id' => $this->miGrupo?->id]
                );
            }
        });

        $this->tarea->unsetRelation('entregas');
        $this->reset(['archivo', 'comentario', 'respuestaTexto', 'enlace']);
        session()->flash('estado', 'Entrega registrada correctamente.');
    }

    // ==========================================
    // CALIFICACIÓN (RF-49, RF-95)
    // ==========================================

    public function calificar(int $entregaId): void
    {
        $entrega = $this->entregaDeEstaTarea($entregaId);
        Gate::authorize('calificar', $entrega);

        $this->validate([
            "notas.$entregaId" => ['required', 'numeric', 'min:0', 'max:20'],
            "feedbacks.$entregaId" => ['nullable', 'string', 'max:2000'],
        ], [
            "notas.$entregaId.required" => 'Ingresa la nota (0 a 20).',
            "notas.$entregaId.max" => 'La nota máxima es 20.',
            "notas.$entregaId.min" => 'La nota mínima es 0.',
        ]);

        $nota = round((float) $this->notas[$entregaId], 2);
        $feedback = $this->feedbacks[$entregaId] ?? null;

        foreach ($this->miembrosDe($entrega) as $entregaMiembro) {
            $notaAnterior = $entregaMiembro->nota;

            $entregaMiembro->update(['nota' => $nota, 'feedback' => $feedback, 'estado' => 'calificado']);

            if ($notaAnterior !== null && (float) $notaAnterior !== $nota) {
                BitacoraAuditoria::registrarCambioNota(
                    ejecutor: auth()->user(),
                    curso: $this->tarea->curso,
                    estudiante: $entregaMiembro->estudiante,
                    entidad: 'entrega_tarea',
                    entidadId: $entregaMiembro->id,
                    valorAnterior: $notaAnterior,
                    valorNuevo: $nota,
                    motivo: 'Recalificación de la tarea "'.$this->tarea->titulo.'".'
                );
            }

            $entregaMiembro->estudiante->notify(new TareaCalificada($entregaMiembro));
        }

        session()->flash('estado', $entrega->grupo_id
            ? 'Calificación guardada para todo el grupo ('.$entrega->grupo->nombre.').'
            : 'Calificación guardada para '.$entrega->estudiante->name.'.');
    }

    /**
     * ERS RF-49: devuelve la entrega para corrección con un comentario.
     */
    public function devolver(int $entregaId): void
    {
        $entrega = $this->entregaDeEstaTarea($entregaId);
        Gate::authorize('calificar', $entrega);

        $this->validate(
            ["feedbacks.$entregaId" => ['required', 'string', 'max:2000']],
            ["feedbacks.$entregaId.required" => 'Escribe un comentario para que el estudiante sepa qué corregir.']
        );

        foreach ($this->miembrosDe($entrega) as $entregaMiembro) {
            $entregaMiembro->update(['estado' => 'devuelto', 'feedback' => $this->feedbacks[$entregaId]]);
            $entregaMiembro->estudiante->notify(new EntregaDevuelta($entregaMiembro));
        }

        session()->flash('estado', 'Entrega devuelta para corrección. Se notificó al estudiante.');
    }

    protected function entregaDeEstaTarea(int $entregaId): Entrega
    {
        return $this->tarea->entregas()->with('estudiante', 'grupo', 'tarea.curso')->findOrFail($entregaId);
    }

    /**
     * @return Collection<int, Entrega>
     */
    protected function miembrosDe(Entrega $entrega): Collection
    {
        return $entrega->grupo_id
            ? $this->tarea->entregas()->where('grupo_id', $entrega->grupo_id)->with('estudiante', 'tarea.curso')->get()
            : collect([$entrega]);
    }

    public function render()
    {
        $entregas = collect();
        $sinEntregar = collect();

        if (Gate::allows('supervisar', $this->tarea)) {
            // Primero lo que falta calificar, luego lo devuelto y al final lo ya calificado.
            $prioridad = ['entregado' => 0, 'devuelto' => 1, 'calificado' => 2];
            $entregas = $this->tarea->entregas()->with('estudiante', 'grupo')->latest('updated_at')->get()
                ->sortBy(fn ($entrega) => $prioridad[$entrega->estado] ?? 3)->values();

            foreach ($entregas as $entrega) {
                $this->notas[$entrega->id] ??= $entrega->nota !== null ? (string) (float) $entrega->nota : '';
                $this->feedbacks[$entrega->id] ??= $entrega->feedback ?? '';
            }

            $sinEntregar = User::whereIn('id', $this->tarea->curso->inscripcionesActivas()->select('estudiante_id'))
                ->whereNotIn('id', $entregas->pluck('estudiante_id'))
                ->orderBy('name')
                ->get();
        }

        return view('livewire.compartido.tareas.detalle', [
            'entregas' => $entregas,
            'sinEntregar' => $sinEntregar,
        ])->title("{$this->tarea->titulo} — LearnUp");
    }
}
