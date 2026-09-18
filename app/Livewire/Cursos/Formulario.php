<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\User;
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

    public ?int $profesor_id = null;

    public ?UploadedFile $portada = null;

    public function mount(?Curso $curso = null): void
    {
        if ($curso?->exists) {
            Gate::authorize('gestionar', $curso);

            $this->curso = $curso;
            $this->nombre = $curso->nombre;
            $this->descripcion = $curso->descripcion ?? '';
            $this->profesor_id = $curso->profesor_id;
        } else {
            Gate::authorize('crear', Curso::class);
            $this->profesor_id = auth()->user()->hasRole('administrador') ? null : auth()->id();
        }
    }

    public function guardar(SubidorArchivos $subidor): void
    {
        $reglas = [
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'portada' => ['nullable', 'image', 'max:4096'],
        ];

        if (auth()->user()->hasRole('administrador')) {
            $reglas['profesor_id'] = ['required', 'exists:users,id'];
        }

        $validado = $this->validate($reglas);

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
                'profesor_id' => auth()->user()->hasRole('administrador') ? $this->profesor_id : auth()->id(),
            ]);
        }

        $this->redirect(route('cursos.show', $curso), navigate: true);
    }

    public function render()
    {
        $profesores = auth()->user()->hasRole('administrador')
            ? User::role('profesor')->orderBy('name')->get()
            : collect();

        return view('livewire.cursos.formulario', [
            'profesores' => $profesores,
        ]);
    }
}
