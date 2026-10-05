<?php

namespace App\Livewire\Asistencia;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\User;
use App\Services\Asistencia\CalculadoraAsistencia;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * ERS RF-70: registro de asistencia por Sesión de Clase
 *            (Presente, Ausente, Tardanza, Justificado).
 * ERS RF-71: porcentaje de asistencia acumulado por estudiante.
 * ERS RF-73: el Profesor del curso decide si usa o no el control de asistencia
 *            (también lo puede fijar el Supervisor al editar el curso).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public const ESTADOS = ['presente', 'tardanza', 'justificado', 'ausente'];

    public Curso $curso;

    // 'sesiones' | 'pase_lista' | 'resumen'
    public string $vista = 'sesiones';

    #[Locked]
    public ?int $sesionActivaId = null;

    /** @var array<int, string> estudiante_id => estado */
    public array $marcas = [];

    public bool $mostrarFormSesion = false;

    public string $sesionTitulo = '';

    public string $sesionFecha = '';

    public string $sesionDuracion = '90';

    public string $sesionDescripcion = '';

    public function mount(Curso $curso): void
    {
        // Consulta: Profesor del curso y Supervisor. Registro: solo el Profesor (matriz ERS § 5).
        Gate::authorize('supervisar', $curso);

        $this->curso = $curso;
        $this->sesionFecha = now()->format('Y-m-d\TH:i');
    }

    public function getEditableProperty(): bool
    {
        return $this->curso->usa_asistencia
            && ! $this->curso->estaFinalizado()
            && Gate::allows('gestionar', $this->curso);
    }

    public function getPuedeConfigurarProperty(): bool
    {
        return Gate::allows('gestionar', $this->curso) && ! $this->curso->estaFinalizado();
    }

    /**
     * ERS RF-73: activa u omite el módulo de asistencia en este curso. Los
     * registros ya guardados se conservan aunque se desactive.
     */
    public function alternarUsoAsistencia(): void
    {
        abort_unless($this->puedeConfigurar, 403);

        $this->curso->update(['usa_asistencia' => ! $this->curso->usa_asistencia]);
        $this->vista = 'sesiones';

        session()->flash('estado', $this->curso->usa_asistencia
            ? 'Control de asistencia activado para este curso.'
            : 'Control de asistencia desactivado. Los registros anteriores se conservan.');
    }

    public function crearSesion(): void
    {
        $this->autorizarEdicion();

        $this->validate([
            'sesionTitulo' => ['required', 'string', 'max:255'],
            'sesionFecha' => ['required', 'date'],
            'sesionDuracion' => ['required', 'integer', 'min:15', 'max:480'],
            'sesionDescripcion' => ['nullable', 'string', 'max:1000'],
        ], [
            'sesionTitulo.required' => 'Escribe el título de la sesión.',
        ]);

        $numero = ((int) $this->curso->sesionesClase()->max('numero_sesion')) + 1;

        $this->curso->sesionesClase()->create([
            'titulo' => $this->sesionTitulo,
            'fecha' => $this->sesionFecha,
            'duracion_minutos' => (int) $this->sesionDuracion,
            'descripcion' => $this->sesionDescripcion ?: null,
            'numero_sesion' => $numero,
        ]);

        $this->mostrarFormSesion = false;
        $this->reset(['sesionTitulo', 'sesionDescripcion']);
        $this->sesionDuracion = '90';
        $this->sesionFecha = now()->format('Y-m-d\TH:i');
        session()->flash('estado', "Sesión #{$numero} creada.");
    }

    public function eliminarSesion(int $id): void
    {
        $this->autorizarEdicion();

        $sesion = $this->sesion($id);

        if ($sesion->asistencias()->exists()) {
            session()->flash('error', 'La sesión ya tiene asistencia registrada y no se puede eliminar.');

            return;
        }

        $sesion->delete();
        session()->flash('estado', 'Sesión eliminada.');
    }

    public function abrirPaseLista(int $sesionId): void
    {
        Gate::authorize('supervisar', $this->curso);

        $sesion = $this->sesion($sesionId);
        $registradas = $sesion->asistencias()->pluck('estado', 'estudiante_id');

        $this->sesionActivaId = $sesion->id;
        $this->marcas = $this->estudiantesActivos()
            ->mapWithKeys(fn (User $e) => [$e->id => $registradas[$e->id] ?? ''])
            ->all();
        $this->vista = 'pase_lista';
    }

    public function marcarTodos(string $estado): void
    {
        abort_unless(in_array($estado, self::ESTADOS, true), 422);

        foreach (array_keys($this->marcas) as $estudianteId) {
            $this->marcas[$estudianteId] = $estado;
        }
    }

    /**
     * ERS RF-70: guarda el pase de lista completo de la sesión activa.
     */
    public function guardarAsistencia(): void
    {
        $this->autorizarEdicion();
        $sesion = $this->sesion($this->sesionActivaId);
        $idsValidos = $this->estudiantesActivos()->pluck('id')->all();

        $this->validate([
            'marcas' => ['array'],
            'marcas.*' => ['required', Rule::in(self::ESTADOS)],
        ], [
            'marcas.*.required' => 'Marca el estado de todos los estudiantes.',
        ]);

        DB::transaction(function () use ($sesion, $idsValidos) {
            foreach ($this->marcas as $estudianteId => $estado) {
                if (! in_array((int) $estudianteId, $idsValidos, true)) {
                    continue;
                }

                Asistencia::updateOrCreate(
                    ['sesion_id' => $sesion->id, 'estudiante_id' => (int) $estudianteId],
                    ['estado' => $estado, 'registrado_por' => auth()->id()]
                );
            }
        });

        session()->flash('estado', "Asistencia de la sesión #{$sesion->numero_sesion} guardada.");
    }

    protected function sesion(?int $id): SesionClase
    {
        return $this->curso->sesionesClase()->findOrFail($id);
    }

    protected function estudiantesActivos(): Collection
    {
        return $this->curso->inscripcionesActivas()->with('estudiante')->get()
            ->pluck('estudiante')->filter()->sortBy('name')->values();
    }

    protected function autorizarEdicion(): void
    {
        Gate::authorize('gestionar', $this->curso);
        abort_unless($this->editable, 403, 'Este curso no registra asistencia o está archivado.');
    }

    public function render(CalculadoraAsistencia $calculadora)
    {
        $sesiones = $this->curso->sesionesClase()->withCount('asistencias')->get();
        $estudiantes = $this->estudiantesActivos();

        return view('livewire.asistencia.index', [
            'sesiones' => $sesiones,
            'totalSesiones' => $sesiones->count(),
            'sesionActiva' => $this->sesionActivaId ? $sesiones->firstWhere('id', $this->sesionActivaId) : null,
            'estudiantes' => $estudiantes,
            'resumen' => $this->vista === 'resumen'
                ? $estudiantes->map(fn (User $e) => [
                    'estudiante' => $e,
                    'historial' => $historial = $calculadora->historial($e, $this->curso),
                    'porcentaje' => $calculadora->porcentaje($historial->pluck('estado')),
                    'conteo' => $historial->countBy('estado'),
                ])
                : collect(),
        ])->title("Asistencia — {$this->curso->nombre}");
    }
}
