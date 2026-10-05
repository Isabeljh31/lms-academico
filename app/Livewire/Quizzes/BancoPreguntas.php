<?php

namespace App\Livewire\Quizzes;

use App\Models\Curso;
use App\Models\Quiz;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ERS RF-47: banco de preguntas del curso, organizado por tema, para
 * reutilizarlas en distintos quizzes (ver Quizzes\Formulario).
 */
#[Layout('layouts.app')]
class BancoPreguntas extends Component
{
    use WithPagination;

    public Curso $curso;

    public string $filtroTema = '';

    public string $busqueda = '';

    public bool $mostrarModal = false;

    #[Locked]
    public ?int $preguntaId = null;

    public string $tema = '';

    public string $pregunta = '';

    public string $tipo = 'opcion_unica';

    /** @var array<int, string> */
    public array $opciones = ['', ''];

    /** @var array<int, int|string> */
    public array $correctas = [0];

    public string $explicacion = '';

    public string $dificultad = '';

    public function mount(Curso $curso): void
    {
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
    }

    public function updating(string $propiedad): void
    {
        if (in_array($propiedad, ['filtroTema', 'busqueda'], true)) {
            $this->resetPage();
        }
    }

    public function updatedTipo(string $tipo): void
    {
        if ($tipo === 'verdadero_falso') {
            $this->opciones = ['Verdadero', 'Falso'];
            $this->correctas = [0];
        }

        if ($tipo !== 'opcion_multiple') {
            $this->correctas = [(int) ($this->correctas[0] ?? 0)];
        }
    }

    public function nueva(): void
    {
        Gate::authorize('gestionar', $this->curso);
        $this->resetErrorBag();
        $this->reset(['preguntaId', 'pregunta', 'explicacion', 'dificultad']);
        $this->tema = $this->filtroTema;
        $this->tipo = 'opcion_unica';
        $this->opciones = ['', ''];
        $this->correctas = [0];
        $this->mostrarModal = true;
    }

    public function editar(int $id): void
    {
        Gate::authorize('gestionar', $this->curso);

        $item = $this->curso->bancoPreguntasCurso()->findOrFail($id);
        $this->resetErrorBag();
        $this->preguntaId = $item->id;
        $this->tema = $item->tema;
        $this->pregunta = $item->pregunta;
        $this->tipo = $item->tipo;
        $this->opciones = array_values($item->opciones ?? []);
        $this->correctas = array_map('intval', $item->respuestas_correctas ?? [0]);
        $this->explicacion = $item->explicacion ?? '';
        $this->dificultad = $item->dificultad ?? '';
        $this->mostrarModal = true;
    }

    public function agregarOpcion(): void
    {
        if (count($this->opciones) < 8) {
            $this->opciones[] = '';
        }
    }

    public function quitarOpcion(int $indice): void
    {
        if (count($this->opciones) <= 2) {
            return;
        }

        unset($this->opciones[$indice]);
        $this->opciones = array_values($this->opciones);
        $this->correctas = array_values(array_filter(
            array_map(fn ($c) => (int) $c > $indice ? (int) $c - 1 : (int) $c, array_filter($this->correctas, fn ($c) => (int) $c !== $indice)),
            fn ($c) => $c < count($this->opciones)
        )) ?: [0];
    }

    public function guardar(): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->correctas = array_values(array_unique(array_map('intval', (array) $this->correctas)));

        $this->validate([
            'tema' => ['required', 'string', 'max:150'],
            'pregunta' => ['required', 'string', 'max:1000'],
            'tipo' => ['required', Rule::in(array_keys(Quiz::TIPOS_PREGUNTA))],
            'opciones' => ['required', 'array', 'min:2', 'max:8'],
            'opciones.*' => ['required', 'string', 'max:255'],
            'correctas' => ['required', 'array', 'min:1', $this->tipo === 'opcion_multiple' ? 'max:8' : 'max:1'],
            'correctas.*' => ['integer', 'min:0', 'max:'.(count($this->opciones) - 1)],
            'explicacion' => ['nullable', 'string', 'max:1000'],
            'dificultad' => ['nullable', Rule::in(['baja', 'media', 'alta'])],
        ], [
            'opciones.*.required' => 'Completa el texto de todas las opciones.',
            'correctas.required' => 'Marca al menos una respuesta correcta.',
            'correctas.max' => 'Este tipo de pregunta admite una sola respuesta correcta.',
        ]);

        $datos = [
            'tema' => trim($this->tema),
            'pregunta' => $this->pregunta,
            'tipo' => $this->tipo,
            'opciones' => array_values($this->opciones),
            'respuestas_correctas' => $this->correctas,
            'explicacion' => $this->explicacion ?: null,
            'dificultad' => $this->dificultad ?: null,
        ];

        if ($this->preguntaId) {
            $this->curso->bancoPreguntasCurso()->findOrFail($this->preguntaId)->update($datos);
        } else {
            $this->curso->bancoPreguntasCurso()->create([...$datos, 'creado_por' => auth()->id()]);
        }

        $this->mostrarModal = false;
        session()->flash('estado', 'Pregunta guardada en el banco.');
    }

    public function eliminar(int $id): void
    {
        Gate::authorize('gestionar', $this->curso);

        $this->curso->bancoPreguntasCurso()->findOrFail($id)->delete();
        session()->flash('estado', 'Pregunta eliminada del banco. Los quizzes que ya la usan no cambian.');
    }

    public function render()
    {
        return view('livewire.quizzes.banco-preguntas', [
            'preguntas' => $this->curso->bancoPreguntasCurso()
                ->when($this->filtroTema, fn ($q) => $q->where('tema', $this->filtroTema))
                ->when($this->busqueda, fn ($q) => $q->where('pregunta', 'like', "%{$this->busqueda}%"))
                ->orderBy('tema')
                ->orderByDesc('id')
                ->paginate(15),
            'temas' => $this->curso->bancoPreguntasCurso()->distinct()->orderBy('tema')->pluck('tema'),
            'tipos' => Quiz::TIPOS_PREGUNTA,
        ])->title("Banco de preguntas — {$this->curso->nombre}");
    }
}
