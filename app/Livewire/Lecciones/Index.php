<?php

namespace App\Livewire\Lecciones;

use App\Models\Leccion;
use App\Models\Modulo;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-35: CRUD de Lecciones dentro de un Módulo.
 * RF-36: video_url y material_url por lección.
 * RF-37: Estado borrador | publicado | programado.
 * RF-38: Solo lecciones publicadas/programadas visibles al estudiante.
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Modulo $modulo;

    // Formulario
    public bool $mostrarFormulario = false;
    public ?int $leccionEditId = null;
    public string $titulo = '';
    public string $descripcion = '';
    public string $video_url = '';
    public string $material_url = '';
    public string $estado = 'borrador';
    public string $fecha_publicacion = '';

    public function mount(Modulo $modulo): void
    {
        Gate::authorize('gestionar', $modulo->curso);
        $this->modulo = $modulo;
    }

    public function abrirNueva(): void
    {
        $this->reset(['leccionEditId','titulo','descripcion','video_url','material_url','fecha_publicacion']);
        $this->estado = 'borrador';
        $this->mostrarFormulario = true;
    }

    public function editar(int $id): void
    {
        $leccion = Leccion::findOrFail($id);
        $this->leccionEditId    = $id;
        $this->titulo           = $leccion->titulo;
        $this->descripcion      = $leccion->descripcion ?? '';
        $this->video_url        = $leccion->video_url ?? '';
        $this->material_url     = $leccion->material_url ?? '';
        $this->estado           = $leccion->estado;
        $this->fecha_publicacion = $leccion->fecha_publicacion?->format('Y-m-d\TH:i') ?? '';
        $this->mostrarFormulario = true;
    }

    public function guardar(): void
    {
        $this->validate([
            'titulo'          => ['required', 'string', 'max:255'],
            'descripcion'     => ['nullable', 'string'],
            'video_url'       => ['nullable', 'url', 'max:500'],
            'material_url'    => ['nullable', 'url', 'max:500'],
            'estado'          => ['required', 'in:borrador,publicado,programado'],
            'fecha_publicacion' => ['nullable', 'date', 'required_if:estado,programado'],
        ]);

        $datos = [
            'titulo'           => $this->titulo,
            'descripcion'      => $this->descripcion ?: null,
            'video_url'        => $this->video_url ?: null,
            'material_url'     => $this->material_url ?: null,
            'estado'           => $this->estado,
            'fecha_publicacion' => $this->estado === 'programado' ? $this->fecha_publicacion : null,
        ];

        if ($this->leccionEditId) {
            Leccion::findOrFail($this->leccionEditId)->update($datos);
            $msg = "Lección actualizada.";
        } else {
            $datos['modulo_id'] = $this->modulo->id;
            $datos['orden']     = $this->modulo->lecciones()->count();
            Leccion::create($datos);
            $msg = "Lección creada exitosamente.";
        }

        $this->mostrarFormulario = false;
        $this->reset(['leccionEditId','titulo','descripcion','video_url','material_url','fecha_publicacion']);
        $this->estado = 'borrador';
        session()->flash('estado', $msg);
    }

    public function eliminar(int $id): void
    {
        Leccion::findOrFail($id)->delete();
        session()->flash('estado', 'Lección eliminada.');
    }

    public function moverArriba(int $id): void
    {
        $leccion = Leccion::findOrFail($id);
        $anterior = $this->modulo->lecciones()->where('orden', '<', $leccion->orden)->orderByDesc('orden')->first();
        if ($anterior) {
            [$leccion->orden, $anterior->orden] = [$anterior->orden, $leccion->orden];
            $leccion->save(); $anterior->save();
        }
    }

    public function moverAbajo(int $id): void
    {
        $leccion = Leccion::findOrFail($id);
        $siguiente = $this->modulo->lecciones()->where('orden', '>', $leccion->orden)->orderBy('orden')->first();
        if ($siguiente) {
            [$leccion->orden, $siguiente->orden] = [$siguiente->orden, $leccion->orden];
            $leccion->save(); $siguiente->save();
        }
    }

    public function render()
    {
        return view('livewire.lecciones.index', [
            'lecciones' => $this->modulo->lecciones()->get(),
        ])->title("Lecciones — {$this->modulo->titulo}");
    }
}
