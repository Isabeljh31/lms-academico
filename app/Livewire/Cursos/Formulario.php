<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario de creación/edición de cursos.
 * ERS RF-19, RF-22: campos de estado, fechas, duración y sesiones.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;

    public ?Curso $curso = null;

    // Campos básicos
    public string $nombre = '';
    public string $descripcion = '';
    public ?int $profesor_id = null;
    public $portada = null;

    // ERS RF-22: estado de publicación
    // estados: borrador | publicado | despublicado | archivado
    public string $estado = 'borrador';

    // ERS RF-19: fechas, duración y sesiones
    public string $fecha_inicio = '';
    public string $fecha_fin = '';
    public string $semanas_duracion = '';
    public string $sesiones_clase_count = '';

    public function mount(?Curso $curso = null): void
    {
        Gate::authorize('crear', Curso::class);

        if ($curso && $curso->exists) {
            Gate::authorize('gestionar', $curso);
            $this->curso = $curso;
            $this->nombre              = $curso->nombre;
            $this->descripcion         = $curso->descripcion ?? '';
            $this->profesor_id         = $curso->profesor_id;
            $this->estado              = $curso->estado;
            $this->fecha_inicio        = $curso->fecha_inicio?->format('Y-m-d') ?? '';
            $this->fecha_fin           = $curso->fecha_fin?->format('Y-m-d') ?? '';
            $this->semanas_duracion    = (string) ($curso->semanas_duracion ?? '');
            $this->sesiones_clase_count = (string) ($curso->sesiones_clase_count ?? '');
        } else {
            // Para nuevo curso: el profesor es el auth si no es supervisor
            $this->profesor_id = auth()->user()->hasRole('supervisor') ? null : auth()->id();
        }
    }

    public function guardar(): void
    {
        Gate::authorize('crear', Curso::class);

        $this->validate([
            'nombre'              => ['required', 'string', 'max:255'],
            'descripcion'         => ['nullable', 'string', 'max:5000'],
            'profesor_id'         => ['nullable', 'exists:users,id'],
            'portada'             => ['nullable', 'image', 'max:2048'],
            'estado'              => ['required', 'string', 'in:borrador,publicado,despublicado,archivado'],
            'fecha_inicio'        => ['nullable', 'date'],
            'fecha_fin'           => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            'semanas_duracion'    => ['nullable', 'integer', 'min:1', 'max:104'],
            'sesiones_clase_count' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        // Upload de portada
        $portadaUrl = $this->curso?->imagen_portada_url;
        if ($this->portada) {
            $path = $this->portada->store('portadas', 'public');
            $portadaUrl = Storage::disk('public')->url($path);
        }

        $datos = [
            'nombre'               => $this->nombre,
            'descripcion'          => $this->descripcion ?: null,
            'profesor_id'          => auth()->user()->hasRole('supervisor')
                                        ? $this->profesor_id
                                        : auth()->id(),
            'imagen_portada_url'   => $portadaUrl,
            'estado'               => $this->estado,
            'fecha_inicio'         => $this->fecha_inicio ?: null,
            'fecha_fin'            => $this->fecha_fin ?: null,
            'semanas_duracion'     => $this->semanas_duracion ?: null,
            'sesiones_clase_count' => $this->sesiones_clase_count ?: null,
        ];

        if ($this->curso && $this->curso->exists) {
            Gate::authorize('gestionar', $this->curso);
            $this->curso->update($datos);
            $mensaje = "Curso '{$this->curso->nombre}' actualizado.";
        } else {
            Curso::create($datos);
            $mensaje = "Curso '{$this->nombre}' creado exitosamente.";
        }

        session()->flash('estado', $mensaje);
        $this->redirect(route('cursos.index'), navigate: true);
    }

    public function render()
    {
        $profesores = auth()->user()->hasRole('supervisor')
            ? User::role('profesor')->orderBy('name')->get()
            : collect();

        $estadosCurso = [
            'borrador'      => 'Borrador (no visible para estudiantes)',
            'publicado'     => 'Publicado (visible y con inscripciones activas)',
            'despublicado'  => 'Despublicado (oculto temporalmente)',
            'archivado'     => 'Archivado (finalizado, historial conservado)',
        ];

        return view('livewire.cursos.formulario', [
            'profesores'   => $profesores,
            'estadosCurso' => $estadosCurso,
        ]);
    }
}
