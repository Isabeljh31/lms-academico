<?php

namespace App\Livewire\Asistencia;

use App\Models\Asistencia;
use App\Models\Curso;
use App\Models\SesionClase;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-70: Registro de asistencia (Presente/Ausente/Tardanza/Justificado).
 * RF-71: % de asistencia acumulado por sesión.
 * RF-72: Historial de asistencia por estudiante.
 * RF-73: Asistencia es opcional por curso (solo si tiene sesiones configuradas).
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Curso $curso;

    // Vista activa: 'sesiones' | 'pase_lista' | 'historial'
    public string $vista = 'sesiones';
    public ?int $sesionActivaId = null;

    // Formulario nueva sesión
    public bool $mostrarFormSesion = false;
    public string $sesionTitulo = '';
    public string $sesionFecha = '';
    public string $sesionDuracion = '90';
    public string $sesionDescripcion = '';

    // Filtro historial
    public ?int $estudianteHistorialId = null;

    public function mount(Curso $curso): void
    {
        abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
        $this->sesionFecha = now()->format('Y-m-d\TH:i');
    }

    public function crearSesion(): void
    {
        $this->validate([
            'sesionTitulo'    => ['required', 'string', 'max:255'],
            'sesionFecha'     => ['required', 'date'],
            'sesionDuracion'  => ['required', 'integer', 'min:15', 'max:480'],
            'sesionDescripcion' => ['nullable', 'string', 'max:1000'],
        ]);

        $numero = $this->curso->sesionesClase()->count() + 1;

        SesionClase::create([
            'curso_id'        => $this->curso->id,
            'titulo'          => $this->sesionTitulo,
            'fecha'           => $this->sesionFecha,
            'duracion_minutos' => $this->sesionDuracion,
            'descripcion'     => $this->sesionDescripcion ?: null,
            'numero_sesion'   => $numero,
        ]);

        $this->mostrarFormSesion = false;
        $this->reset(['sesionTitulo', 'sesionDescripcion']);
        $this->sesionDuracion = '90';
        $this->sesionFecha = now()->format('Y-m-d\TH:i');
        session()->flash('estado', "Sesión #{$numero} creada.");
    }

    public function eliminarSesion(int $id): void
    {
        SesionClase::findOrFail($id)->delete();
        session()->flash('estado', 'Sesión eliminada.');
    }

    public function abrirPaseLista(int $sesionId): void
    {
        $this->sesionActivaId = $sesionId;
        $this->vista = 'pase_lista';
    }

    /**
     * ERS RF-70: Registrar o actualizar asistencia de un estudiante.
     */
    public function marcarAsistencia(int $estudianteId, string $estado): void
    {
        abort_unless(in_array($estado, ['presente', 'ausente', 'tardanza', 'justificado']), 422);

        Asistencia::updateOrCreate(
            ['sesion_id' => $this->sesionActivaId, 'estudiante_id' => $estudianteId],
            ['estado' => $estado, 'registrado_por' => auth()->id()]
        );
    }

    public function render()
    {
        $sesiones = $this->curso->sesionesClase()->withCount('asistencias')->get();

        $sesionActiva = null;
        $estudiantesConAsistencia = collect();

        if ($this->sesionActivaId) {
            $sesionActiva = SesionClase::with('asistencias')->find($this->sesionActivaId);
            $inscriptos = $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter();
            $asistenciasMap = $sesionActiva?->asistencias->keyBy('estudiante_id') ?? collect();

            $estudiantesConAsistencia = $inscriptos->map(fn (User $est) => [
                'estudiante'  => $est,
                'asistencia'  => $asistenciasMap->get($est->id),
                'estado'      => $asistenciasMap->get($est->id)?->estado ?? 'ausente',
            ]);
        }

        // ERS RF-72: historial por estudiante
        $historialEstudiante = collect();
        $estudianteFiltrado  = null;
        if ($this->estudianteHistorialId) {
            $estudianteFiltrado = User::find($this->estudianteHistorialId);
            $historialEstudiante = Asistencia::where('estudiante_id', $this->estudianteHistorialId)
                ->whereHas('sesion', fn ($q) => $q->where('curso_id', $this->curso->id))
                ->with('sesion')
                ->orderByDesc('created_at')
                ->get();
        }

        // ERS RF-71: % asistencia total del curso
        $totalSesiones   = $sesiones->count();
        $estudiantesActivos = $this->curso->inscripcionesActivas()->count();

        return view('livewire.asistencia.index', [
            'sesiones'                => $sesiones,
            'totalSesiones'           => $totalSesiones,
            'estudiantesActivos'      => $estudiantesActivos,
            'sesionActiva'            => $sesionActiva,
            'estudiantesConAsistencia' => $estudiantesConAsistencia,
            'historialEstudiante'     => $historialEstudiante,
            'estudianteFiltrado'      => $estudianteFiltrado,
            'estudiantesParaFiltro'   => $this->curso->inscripcionesActivas()->with('estudiante')->get()->pluck('estudiante')->filter(),
        ])->title("Asistencia — {$this->curso->nombre}");
    }
}
