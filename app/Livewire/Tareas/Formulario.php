<?php

namespace App\Livewire\Tareas;

use App\Models\Curso;
use App\Models\Tarea;
use App\Notifications\CambioFechaTarea;
use App\Notifications\NuevaTareaAsignada;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-40: tarea con título, descripción, instrucciones, fecha de inicio,
 * fecha límite, puntaje, intentos y tipo de entrega (individual/grupal).
 * RF-41: formatos de archivo permitidos. RF-37: Borrador/Publicado/Programado.
 * RF-76: tarea grupal asignada a un grupo específico.
 * RF-84: aviso de tarea nueva y de cambio de fecha.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    public Curso $curso;

    public ?Tarea $tarea = null;

    public string $titulo = '';

    public string $descripcion = '';

    public string $instrucciones = '';

    public ?int $modulo_id = null;

    public string $fecha_inicio = '';

    public string $fecha_entrega = '';

    public float $peso_nota = 1;

    public int $intentos_permitidos = 1;

    /** @var array<int, string> */
    public array $formatos_permitidos = [];

    public string $tipo_entrega = 'individual';

    public ?int $grupo_asignado_id = null;

    public string $estado_contenido = 'publicado';

    public string $publicar_en = '';

    public function mount(Curso $curso, ?Tarea $tarea = null): void
    {
        $this->curso = $curso;

        if ($tarea?->exists) {
            Gate::authorize('gestionar', $tarea);

            $this->tarea = $tarea;
            $this->titulo = $tarea->titulo;
            $this->descripcion = $tarea->descripcion ?? '';
            $this->instrucciones = $tarea->instrucciones ?? '';
            $this->modulo_id = $tarea->modulo_id;
            $this->fecha_inicio = $tarea->fecha_inicio?->format('Y-m-d\TH:i') ?? '';
            $this->fecha_entrega = $tarea->fecha_entrega->format('Y-m-d\TH:i');
            $this->peso_nota = (float) $tarea->peso_nota;
            $this->intentos_permitidos = (int) $tarea->intentos_permitidos;
            $this->formatos_permitidos = $tarea->formatosPermitidos();
            $this->tipo_entrega = $tarea->tipo_entrega;
            $this->grupo_asignado_id = $tarea->grupo_asignado_id;
            $this->estado_contenido = $tarea->visible ? ($tarea->estado_contenido ?? 'publicado') : 'borrador';
            $this->publicar_en = $tarea->publicar_en?->format('Y-m-d\TH:i') ?? '';
        } else {
            Gate::authorize('crear', [Tarea::class, $curso]);
        }

        abort_if($curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');
    }

    public function guardar(): void
    {
        $this->tarea
            ? Gate::authorize('gestionar', $this->tarea)
            : Gate::authorize('crear', [Tarea::class, $this->curso]);
        abort_if($this->curso->estaFinalizado(), 403, 'El curso está archivado y no admite cambios.');

        $validado = $this->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            'instrucciones' => ['nullable', 'string', 'max:5000'],
            'modulo_id' => ['required', Rule::exists('modulos', 'id')->where('curso_id', $this->curso->id)],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_entrega' => array_filter(['required', 'date', $this->fecha_inicio !== '' ? 'after_or_equal:fecha_inicio' : null]),
            'peso_nota' => ['required', 'numeric', 'min:0', 'max:100'],
            'intentos_permitidos' => ['required', 'integer', 'min:1', 'max:10'],
            'formatos_permitidos' => ['array'],
            'formatos_permitidos.*' => [Rule::in(SubidorArchivos::extensionesPermitidas())],
            'tipo_entrega' => ['required', 'in:individual,grupal'],
            'grupo_asignado_id' => ['nullable', Rule::exists('grupos', 'id')->where('curso_id', $this->curso->id)],
            'estado_contenido' => ['required', Rule::in(['borrador', 'publicado', 'programado'])],
            'publicar_en' => ['nullable', 'required_if:estado_contenido,programado', 'date'],
        ], [
            'modulo_id.required' => 'Selecciona la semana a la que pertenece la tarea.',
            'modulo_id.exists' => 'La semana elegida no pertenece a este curso.',
            'fecha_entrega.after_or_equal' => 'La fecha límite no puede ser anterior a la fecha de inicio.',
            'publicar_en.required_if' => 'Indica cuándo se publicará automáticamente.',
        ]);

        $datos = [
            ...collect($validado)->except(['estado_contenido', 'publicar_en', 'grupo_asignado_id'])->all(),
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'formatos_permitidos' => array_values($this->formatos_permitidos) ?: null,
            'grupo_asignado_id' => $this->tipo_entrega === 'grupal' ? $this->grupo_asignado_id : null,
            'visible' => $this->estado_contenido !== 'borrador',
            'estado_contenido' => $this->estado_contenido === 'borrador' ? 'publicado' : $this->estado_contenido,
            'publicar_en' => $this->estado_contenido === 'programado' ? $this->publicar_en : null,
        ];

        $eraVisible = $this->tarea?->esVisibleParaEstudiante() ?? false;
        $fechaAnterior = $this->tarea?->fecha_entrega?->copy();

        if ($this->tarea) {
            $this->tarea->update($datos);
            $tarea = $this->tarea->fresh();
        } else {
            $tarea = $this->curso->tareas()->create($datos)->fresh();
        }

        $estudiantes = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();

        if ($tarea->esVisibleParaEstudiante() && ! $eraVisible) {
            Notification::send($estudiantes, new NuevaTareaAsignada($tarea));
        } elseif ($eraVisible && $fechaAnterior && ! $fechaAnterior->equalTo(Carbon::parse($tarea->fecha_entrega))) {
            Notification::send($estudiantes, new CambioFechaTarea($tarea, $fechaAnterior));
        }

        session()->flash('estado', "Tarea \"{$tarea->titulo}\" guardada.");
        $this->redirect(route('cursos.tareas.index', $this->curso), navigate: true);
    }

    public function render()
    {
        return view('livewire.tareas.formulario', [
            'modulos' => $this->curso->modulos()->orderBy('orden')->get(),
            'grupos' => $this->curso->grupos()->orderBy('nombre')->get(),
            'extensiones' => SubidorArchivos::extensionesPermitidas(),
        ])->title(($this->tarea ? 'Editar tarea' : 'Nueva tarea').' — LearnUp');
    }
}
