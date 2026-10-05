<?php

namespace App\Livewire\Lecciones;

use App\Models\Leccion;
use App\Models\Modulo;
use App\Notifications\NuevoContenidoPublicado;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * ERS RF-35: CRUD de Lecciones dentro de un Módulo.
 * RF-36: video, enlace a material o archivo subido (PDF, Word, Excel,
 *        PowerPoint, ZIP, imágenes) con límite de 10 MB.
 * RF-37: estado Borrador | Publicado | Programado.
 * RF-38: solo las publicadas (o programadas ya vencidas) las ve el estudiante.
 * RF-84: aviso a los estudiantes cuando una lección se publica.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public Modulo $modulo;

    public bool $mostrarFormulario = false;

    #[Locked]
    public ?int $leccionEditId = null;

    public string $titulo = '';

    public string $descripcion = '';

    public string $video_url = '';

    public string $material_url = '';

    public $archivo = null;

    public string $estado = 'borrador';

    public string $fecha_publicacion = '';

    /** ERS RF-67: marcar | abrir_recurso (Leccion::CONDICIONES). */
    public string $condicion_completitud = 'marcar';

    /** Tipo de recurso (Leccion::TIPOS_RECURSO): etiqueta e ícono en el contenido semanal. */
    public string $tipo_recurso = 'lectura';

    public function mount(Modulo $modulo): void
    {
        Gate::authorize('gestionar', $modulo->curso);
        $this->modulo = $modulo;
    }

    public function getCursoEditableProperty(): bool
    {
        return ! $this->modulo->curso->estaFinalizado();
    }

    public function abrirNueva(): void
    {
        $this->autorizarEdicion();
        $this->resetErrorBag();
        $this->reset(['leccionEditId', 'titulo', 'descripcion', 'video_url', 'material_url', 'archivo', 'fecha_publicacion']);
        $this->estado = 'borrador';
        $this->condicion_completitud = 'marcar';
        $this->tipo_recurso = 'lectura';
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $this->autorizarEdicion();

        $leccion = $this->leccion($id);
        $this->resetErrorBag();
        $this->leccionEditId = $leccion->id;
        $this->titulo = $leccion->titulo;
        $this->descripcion = $leccion->descripcion ?? '';
        $this->video_url = $leccion->video_url ?? '';
        $this->material_url = $leccion->material_url ?? '';
        $this->archivo = null;
        $this->estado = $leccion->estado;
        $this->fecha_publicacion = $leccion->fecha_publicacion?->format('Y-m-d\TH:i') ?? '';
        $this->condicion_completitud = $leccion->condicion_completitud ?? 'marcar';
        $this->tipo_recurso = $leccion->tipo_recurso ?? 'lectura';
        $this->mostrarFormulario = true;
    }

    public function guardar(SubidorArchivos $subidor): void
    {
        $this->autorizarEdicion();

        $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:10000'],
            'video_url' => ['nullable', 'url:http,https', 'max:500'],
            'material_url' => ['nullable', 'url:http,https', 'max:500'],
            'archivo' => SubidorArchivos::reglas(requerido: false),
            'estado' => ['required', Rule::in(['borrador', 'publicado', 'programado'])],
            'fecha_publicacion' => ['nullable', 'required_if:estado,programado', 'date', 'after:now'],
            'condicion_completitud' => ['required', Rule::in(array_keys(Leccion::CONDICIONES))],
            'tipo_recurso' => ['required', Rule::in(array_keys(Leccion::TIPOS_RECURSO))],
        ], [
            'fecha_publicacion.required_if' => 'Indica la fecha y hora de publicación automática.',
            'fecha_publicacion.after' => 'La fecha de publicación programada debe ser futura.',
            'video_url.url' => 'El enlace del video debe empezar con http:// o https://.',
            'material_url.url' => 'El enlace del material debe empezar con http:// o https://.',
            ...SubidorArchivos::mensajes('archivo'),
        ]);

        $leccion = $this->leccionEditId ? $this->leccion($this->leccionEditId) : null;

        if ($this->condicion_completitud === 'abrir_recurso'
            && $this->video_url === '' && $this->material_url === '' && ! $this->archivo) {
            $this->addError('condicion_completitud', 'Para completarse al abrir su recurso, la lección necesita un video, un enlace o un archivo.');

            return;
        }

        $estabaVisible = $leccion?->esVisibleParaEstudiante() ?? false;

        $datos = [
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion ?: null,
            'video_url' => $this->video_url ?: null,
            'material_url' => $this->material_url ?: $leccion?->material_url,
            'material_nombre' => $leccion?->material_nombre,
            'estado' => $this->estado,
            'fecha_publicacion' => $this->estado === 'programado' ? $this->fecha_publicacion : null,
            'condicion_completitud' => $this->condicion_completitud,
            'tipo_recurso' => $this->tipo_recurso,
        ];

        if ($this->material_url === '' && $leccion && ! $this->archivo) {
            $datos['material_url'] = null;
            $datos['material_nombre'] = null;
        }

        if ($this->archivo) {
            $datos['material_url'] = $subidor->subir($this->archivo, 'cursos/lecciones', 'archivo');
            $datos['material_nombre'] = $this->archivo->getClientOriginalName();
        }

        if ($leccion) {
            $leccion->update($datos);
            $mensaje = 'Lección actualizada.';
        } else {
            $leccion = $this->modulo->lecciones()->create([
                ...$datos,
                'orden' => ((int) $this->modulo->lecciones()->max('orden')) + 1,
            ]);
            $mensaje = 'Lección creada.';
        }

        if (! $estabaVisible && $leccion->fresh()->esVisibleParaEstudiante() && $this->modulo->esVisibleParaEstudiante()) {
            $estudiantes = $this->modulo->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
            Notification::send($estudiantes, new NuevoContenidoPublicado($leccion));
            $mensaje .= ' Se notificó a los estudiantes.';
        }

        $this->mostrarFormulario = false;
        $this->reset(['leccionEditId', 'titulo', 'descripcion', 'video_url', 'material_url', 'archivo', 'fecha_publicacion']);
        $this->estado = 'borrador';
        session()->flash('estado', $mensaje);
    }

    public function eliminar(int $id): void
    {
        $this->autorizarEdicion();

        $this->leccion($id)->delete();
        session()->flash('estado', 'Lección eliminada.');
    }

    public function moverArriba(int $id): void
    {
        $this->mover($id, 'arriba');
    }

    public function moverAbajo(int $id): void
    {
        $this->mover($id, 'abajo');
    }

    protected function mover(int $id, string $direccion): void
    {
        $this->autorizarEdicion();

        $lecciones = $this->modulo->lecciones()->get()->values();
        $indice = $lecciones->search(fn (Leccion $l) => $l->id === $id);
        abort_if($indice === false, 404);

        $destino = $direccion === 'arriba' ? $indice - 1 : $indice + 1;

        if ($destino < 0 || $destino >= $lecciones->count()) {
            return;
        }

        $ordenadas = $lecciones->all();
        [$ordenadas[$indice], $ordenadas[$destino]] = [$ordenadas[$destino], $ordenadas[$indice]];

        foreach (array_values($ordenadas) as $posicion => $leccion) {
            $leccion->update(['orden' => $posicion + 1]);
        }
    }

    protected function leccion(int $id): Leccion
    {
        return $this->modulo->lecciones()->findOrFail($id);
    }

    protected function autorizarEdicion(): void
    {
        Gate::authorize('gestionar', $this->modulo->curso);
        abort_unless($this->cursoEditable, 403, 'El curso está archivado y no admite cambios.');
    }

    public function render()
    {
        return view('livewire.lecciones.index', [
            'lecciones' => $this->modulo->lecciones()->withCount('completadas')->get(),
            'totalEstudiantes' => $this->modulo->curso->inscripcionesActivas()->count(),
        ])->title("Lecciones — {$this->modulo->nombreCompleto()}");
    }
}
