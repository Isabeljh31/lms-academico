<?php

namespace App\Livewire\Tareas;

use App\Models\Curso;
use App\Models\Tarea;
use App\Models\User;
use App\Notifications\NuevaTareaAsignada;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulario extends Component
{
    public Curso $curso;

    public ?Tarea $tarea = null;

    public string $titulo = '';

    public string $descripcion = '';

    public ?int $modulo_id = null;

    public string $fecha_entrega = '';

    public float $peso_nota = 1;

    public string $tipo_entrega = 'individual';

    public bool $visible = true;

    public function mount(Curso $curso, ?Tarea $tarea = null): void
    {
        $this->curso = $curso;

        if ($tarea?->exists) {
            Gate::authorize('gestionar', $tarea);

            $this->tarea = $tarea;
            $this->titulo = $tarea->titulo;
            $this->descripcion = $tarea->descripcion ?? '';
            $this->modulo_id = $tarea->modulo_id;
            $this->fecha_entrega = $tarea->fecha_entrega->format('Y-m-d\TH:i');
            $this->peso_nota = (float) $tarea->peso_nota;
            $this->tipo_entrega = $tarea->tipo_entrega;
            $this->visible = $tarea->visible;
        } else {
            Gate::authorize('crear', [Tarea::class, $curso]);
        }
    }

    public function guardar(): void
    {
        $validado = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:2000'],
            'modulo_id' => ['required', 'exists:modulos,id'],
            'fecha_entrega' => ['required', 'date'],
            'peso_nota' => ['required', 'numeric', 'min:0', 'max:100'],
            'tipo_entrega' => ['required', 'in:individual,grupal'],
            'visible' => ['boolean'],
        ]);

        // Solo se notifica a los inscritos la primera vez que la tarea pasa
        // a estar visible (al crearla ya visible, o al editarla y marcarla
        // visible habiendo estado oculta). Si nace oculta porque el
        // profesor la está preparando con anticipación, no se avisa a
        // nadie todavía.
        $eraVisibleAntes = $this->tarea?->visible ?? false;

        if ($this->tarea) {
            $this->tarea->update($validado);
            $tarea = $this->tarea;
        } else {
            $tarea = $this->curso->tareas()->create($validado);
        }

        if ($tarea->visible && ! $eraVisibleAntes) {
            $estudiantes = User::whereIn('id', $this->curso->inscripciones()->pluck('estudiante_id'))->get();
            Notification::send($estudiantes, new NuevaTareaAsignada($tarea));
        }

        $this->redirect(route('cursos.tareas.index', $this->curso), navigate: true);
    }

    public function render()
    {
        return view('livewire.tareas.formulario', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
        ]);
    }
}
