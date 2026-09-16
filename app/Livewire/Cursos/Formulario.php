<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;

    public ?Curso $curso = null;

    public string $nombre = '';

    public string $descripcion = '';

    public ?UploadedFile $portada = null;

    public function mount(?Curso $curso = null): void
    {
        if ($curso?->exists) {
            Gate::authorize('gestionar', $curso);

            $this->curso = $curso;
            $this->nombre = $curso->nombre;
            $this->descripcion = $curso->descripcion ?? '';
        } else {
            Gate::authorize('crear', Curso::class);
        }
    }

    public function guardar(SubidorArchivos $subidor): void
    {
        $validado = $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'portada' => ['nullable', 'image', 'max:4096'],
        ]);

        if ($this->portada) {
            if ($this->curso) {
                $subidor->eliminar($this->curso->imagen_portada_url);
            }
            $validado['imagen_portada_url'] = $subidor->subir($this->portada, 'cursos/portadas');
        }

        if ($this->curso) {
            $this->curso->update($validado);
            $curso = $this->curso;
        } else {
            $curso = Curso::create([
                ...$validado,
                'profesor_id' => auth()->id(),
            ]);
        }

        $this->redirect(route('cursos.show', $curso), navigate: true);
    }

    public function render()
    {
        return view('livewire.cursos.formulario');
    }
}
