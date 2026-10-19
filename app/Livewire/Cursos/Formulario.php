<?php

namespace App\Livewire\Cursos;

use App\Enums\Rol;
use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\User;
use App\Rules\TieneRol;
use App\Services\Multimedia\SubidorArchivos;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Formulario de creación/edición de cursos (solo Supervisor).
 * ERS RF-18, RF-19, RF-22, RF-24, RF-25, RF-73; regla de negocio 8.
 */
#[Layout('layouts.app')]
class Formulario extends Component
{
    use WithFileUploads;

    public ?Curso $curso = null;

    public string $nombre = '';

    public string $codigo = '';

    public string $descripcion = '';

    public ?int $profesor_id = null;

    /**
     * ERS RF-23: profesores adicionales (co-docentes), además del titular.
     *
     * @var array<int, int|string>
     */
    public array $profesoresAdicionales = [];

    public $portada = null;

    // estados: borrador | publicado | despublicado | archivado
    public string $estado = 'borrador';

    public string $fecha_inicio = '';

    public string $fecha_fin = '';

    /** Semanas en que se organiza el contenido (Semana 1..N); por defecto Curso::SEMANAS_PREDETERMINADAS. */
    public string $semanas_duracion = '16';

    public string $sesiones_clase_count = '';

    public bool $usa_asistencia = true;

    public function mount(?Curso $curso = null): void
    {
        if ($curso && $curso->exists) {
            Gate::authorize('administrar', $curso);

            $this->curso = $curso;
            $this->nombre = $curso->nombre;
            $this->codigo = $curso->codigo ?? '';
            $this->descripcion = $curso->descripcion ?? '';
            $this->profesor_id = $curso->profesor_id;
            $this->profesoresAdicionales = $curso->profesoresAdicionales()->pluck('users.id')->map(fn ($id) => (string) $id)->all();
            $this->estado = $curso->estado;
            $this->fecha_inicio = $curso->fecha_inicio?->format('Y-m-d') ?? '';
            $this->fecha_fin = $curso->fecha_fin?->format('Y-m-d') ?? '';
            $this->semanas_duracion = (string) ($curso->semanas_duracion ?? $curso->totalSemanas());
            $this->sesiones_clase_count = (string) ($curso->sesiones_clase_count ?? '');
            $this->usa_asistencia = (bool) $curso->usa_asistencia;
        } else {
            Gate::authorize('crear', Curso::class);
        }
    }

    /** El código se muestra en mayúsculas apenas el usuario sale del campo. */
    public function updatedCodigo(): void
    {
        $this->codigo = (string) Curso::normalizarCodigo($this->codigo);
    }

    public function getEsArchivadoProperty(): bool
    {
        return (bool) $this->curso?->estaFinalizado();
    }

    public function guardar(SubidorArchivos $subidor): void
    {
        $editando = $this->curso && $this->curso->exists;
        $editando ? Gate::authorize('administrar', $this->curso) : Gate::authorize('crear', Curso::class);

        // Regla de negocio 8 / RNF-14: un curso archivado solo puede cambiar de estado.
        if ($editando && $this->esArchivado) {
            $this->guardarSoloEstado();

            return;
        }

        $this->codigo = (string) Curso::normalizarCodigo($this->codigo);

        $this->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'codigo' => ['nullable', 'string', 'max:30', 'regex:'.Curso::FORMATO_CODIGO, Rule::unique('cursos', 'codigo')->ignore($this->curso?->id)],
            'descripcion' => ['nullable', 'string', 'max:5000'],
            // Si el titular no cambia se conserva aunque hoy sea el Supervisor
            // (curso sin profesor tras desasignarlo, ver Admin\Profesores\Index).
            'profesor_id' => ['required', (int) $this->profesor_id === $this->curso?->profesor_id
                ? Rule::exists('users', 'id')
                : new TieneRol(Rol::Profesor)],
            'profesoresAdicionales' => ['array', 'max:10', new TieneRol(Rol::Profesor)],
            'portada' => SubidorArchivos::reglas(config('archivos.extensiones.imagenes'), requerido: false),
            'estado' => ['required', Rule::in(Curso::ESTADOS)],
            'fecha_inicio' => ['nullable', 'date'],
            'fecha_fin' => ['nullable', 'date', 'after_or_equal:fecha_inicio'],
            // No puede quedar por debajo de la última semana que ya tiene contenido.
            'semanas_duracion' => ['required', 'integer', 'min:'.max(1, (int) $this->curso?->modulos()->max('semana')), 'max:104'],
            'sesiones_clase_count' => ['nullable', 'integer', 'min:1', 'max:999'],
            'usa_asistencia' => ['boolean'],
        ], [
            'profesor_id.required' => 'Selecciona el profesor titular del curso.',
            'fecha_fin.after_or_equal' => 'La fecha de fin no puede ser anterior a la de inicio.',
            'codigo.unique' => 'Ya existe un curso con este código.',
            'codigo.regex' => 'El código solo puede tener letras (A-Z), números y guiones, sin espacios ni tildes. Ejemplo: MAT-201.',
            'codigo.max' => 'El código no puede tener más de 30 caracteres.',
            'semanas_duracion.required' => 'Indica cuántas semanas dura el curso.',
            'semanas_duracion.min' => 'La semana :min ya tiene contenido: la duración no puede ser menor. Vacía o mueve esas semanas primero.',
            ...SubidorArchivos::mensajes('portada'),
        ]);

        $adicionales = collect($this->profesoresAdicionales)->map(fn ($id) => (int) $id)
            ->reject(fn (int $id) => $id === (int) $this->profesor_id)->unique()->values();

        // ERS RF-25: solo se publica un curso con contenido en al menos una semana (o en prácticas de campo).
        if ($this->estado === 'publicado' && (! $editando || $this->curso->modulos()->doesntExist())) {
            $this->addError('estado', 'Para publicar el curso debe tener contenido en al menos una semana. Guárdalo como borrador, prepara sus semanas y luego publícalo.');

            return;
        }

        $portadaUrl = $this->curso?->imagen_portada_url;

        if ($this->portada) {
            $portadaUrl = $subidor->subir($this->portada, 'cursos/portadas', 'portada', config('archivos.extensiones.imagenes'));
            $subidor->eliminar($this->curso?->imagen_portada_url);
        }

        $datos = [
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'descripcion' => $this->descripcion ?: null,
            'profesor_id' => $this->profesor_id,
            'imagen_portada_url' => $portadaUrl,
            'estado' => $this->estado,
            'fecha_inicio' => $this->fecha_inicio ?: null,
            'fecha_fin' => $this->fecha_fin ?: null,
            'semanas_duracion' => $this->semanas_duracion ?: null,
            'sesiones_clase_count' => $this->sesiones_clase_count ?: null,
            'usa_asistencia' => $this->usa_asistencia,
        ];

        if ($editando) {
            $anterior = $this->curso->only(array_keys($datos)) + ['profesores_adicionales' => $this->curso->profesoresAdicionales()->pluck('users.id')->sort()->values()->all()];
            $this->curso->update($datos);
            $curso = $this->curso;
            $mensaje = "Curso \"{$curso->nombre}\" actualizado.";
        } else {
            $anterior = null;
            $curso = Curso::create($datos);
            $mensaje = "Curso \"{$curso->nombre}\" creado como {$curso->estado}. Agrega sus módulos y define su esquema de evaluación.";
        }

        $curso->profesoresAdicionales()->sync($adicionales->all());

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_curso',
            descripcion: ($editando ? 'Curso editado: ' : 'Curso creado: ')."\"{$curso->nombre}\" (estado {$curso->estado}).",
            curso: $curso,
            valorAnterior: $anterior,
            valorNuevo: $curso->only(array_keys($datos)) + ['profesores_adicionales' => $adicionales->sort()->values()->all()],
        );

        session()->flash('estado', $mensaje);
        $this->redirect(route('cursos.show', $curso), navigate: true);
    }

    protected function guardarSoloEstado(): void
    {
        $this->validate(['estado' => ['required', Rule::in(Curso::ESTADOS)]]);

        if ($this->estado === 'archivado') {
            $this->addError('estado', 'El curso ya está archivado. Solo puedes cambiar su estado para reabrirlo.');

            return;
        }

        $this->curso->update(['estado' => $this->estado]);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'modificacion_curso',
            descripcion: "Curso reabierto: \"{$this->curso->nombre}\".",
            curso: $this->curso,
            valorAnterior: 'archivado',
            valorNuevo: $this->estado,
        );

        session()->flash('estado', "El curso \"{$this->curso->nombre}\" se reabrió con estado {$this->estado}.");
        $this->redirect(route('cursos.show', $this->curso), navigate: true);
    }

    public function render()
    {
        return view('livewire.cursos.formulario', [
            'profesores' => User::role(Rol::Profesor->value)->where('activo', true)->orderBy('name')->get(),
            'estadosCurso' => [
                'borrador' => 'Borrador (no visible para estudiantes)',
                'publicado' => 'Publicado (visible para los estudiantes matriculados)',
                'despublicado' => 'Despublicado (oculto temporalmente)',
                'archivado' => 'Archivado (finalizado: ya no admite cambios)',
            ],
        ])->title(($this->curso ? 'Editar curso' : 'Nuevo curso').' — LearnUp');
    }
}
