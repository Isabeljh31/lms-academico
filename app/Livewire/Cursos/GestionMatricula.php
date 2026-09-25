<?php

namespace App\Livewire\Cursos;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use App\Notifications\MatriculadoEnCurso;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ERS RF-26: Matrícula manual por Supervisor/Profesor.
 * ERS RF-29: Dar de baja con confirmación.
 * ERS RF-30: Preservar historial académico al dar de baja.
 * ERS RF-31: Confirmación explícita antes de baja.
 * ERS RF-33: Notificar al estudiante al matricularse.
 */
#[Layout('layouts.app')]
class GestionMatricula extends Component
{
    use WithPagination;

    public Curso $curso;

    // Búsqueda para agregar estudiante
    public string $busquedaEstudiante = '';
    public ?int $estudianteSeleccionadoId = null;

    // Modal de baja
    public bool $mostrarModalBaja = false;
    public ?int $inscripcionBajaId = null;
    public string $motivoBaja = '';

    // Filtros de la lista
    public string $filtroEstado = 'activo';

    public function mount(Curso $curso): void
    {
        abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);
        Gate::authorize('gestionar', $curso);
        $this->curso = $curso;
    }

    public function updatingBusquedaEstudiante(): void
    {
        $this->estudianteSeleccionadoId = null;
    }

    public function updatingFiltroEstado(): void { $this->resetPage(); }

    // ==========================================
    // MATRÍCULA MANUAL (ERS RF-26)
    // ==========================================

    public function seleccionarEstudiante(int $id): void
    {
        $this->estudianteSeleccionadoId = $id;
        $user = User::find($id);
        $this->busquedaEstudiante = $user?->name ?? '';
    }

    public function matricularEstudiante(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);

        $this->validate([
            'estudianteSeleccionadoId' => ['required', 'exists:users,id'],
        ]);

        $estudiante = User::findOrFail($this->estudianteSeleccionadoId);

        // Verificar que no esté ya inscrito y activo
        $inscripcionExistente = Inscripcion::where('curso_id', $this->curso->id)
            ->where('estudiante_id', $estudiante->id)
            ->first();

        if ($inscripcionExistente) {
            if ($inscripcionExistente->estaActiva()) {
                $this->addError('estudianteSeleccionadoId', "{$estudiante->name} ya está inscrito y activo en este curso.");
                return;
            }
            // Reactivar inscripción anterior (ERS RF-30: conserva historial)
            $inscripcionExistente->update([
                'estado'       => 'activo',
                'activo_local' => true,
                'fecha_baja'   => null,
                'dado_de_baja_por' => null,
            ]);
            $inscripcion = $inscripcionExistente;
        } else {
            $inscripcion = Inscripcion::create([
                'curso_id'          => $this->curso->id,
                'estudiante_id'     => $estudiante->id,
                'fecha_inscripcion' => now(),
                'origen'            => 'manual',
                'estado'            => 'activo',
                'activo_local'      => true,
            ]);
        }

        // ERS RF-33: notificar al estudiante
        $estudiante->notify(new MatriculadoEnCurso($this->curso, 'manual'));

        // ERS RF-96: auditoría
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'matricula_manual',
            descripcion: "Matrícula manual: {$estudiante->name} ({$estudiante->email}) en \"{$this->curso->nombre}\"",
            usuarioAfectado: $estudiante,
            curso: $this->curso
        );

        $this->reset(['busquedaEstudiante', 'estudianteSeleccionadoId']);
        session()->flash('estado', "✅ {$estudiante->name} matriculado correctamente.");
    }

    // ==========================================
    // BAJA LÓGICA (ERS RF-29, RF-30, RF-31)
    // ==========================================

    public function confirmarBaja(int $inscripcionId): void
    {
        abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);
        $this->inscripcionBajaId = $inscripcionId;
        $this->motivoBaja        = '';
        $this->mostrarModalBaja  = true;
    }

    public function ejecutarBaja(): void
    {
        abort_unless(auth()->user()->hasAnyRole(['supervisor', 'profesor']), 403);

        $this->validate([
            'motivoBaja' => ['nullable', 'string', 'max:500'],
        ]);

        $inscripcion = Inscripcion::with('estudiante', 'curso')->findOrFail($this->inscripcionBajaId);

        // ERS RF-32: matrícula de API solo desactiva local, no elimina
        if ($inscripcion->esDeApiExterna()) {
            $inscripcion->desactivarAccesoLocal(auth()->user());
        } else {
            $inscripcion->darDeBaja(auth()->user());
        }

        $estudiante = $inscripcion->estudiante;

        // ERS RF-96: auditoría de baja
        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'baja_estudiante',
            descripcion: "Baja de {$estudiante?->name} en \"{$this->curso->nombre}\""
                . ($this->motivoBaja ? " — Motivo: {$this->motivoBaja}" : ''),
            usuarioAfectado: $estudiante,
            curso: $this->curso
        );

        $this->mostrarModalBaja = false;
        $this->inscripcionBajaId = null;
        $this->motivoBaja = '';
        session()->flash('estado', "⚠️ {$estudiante?->name} dado de baja del curso. El historial académico se conserva.");
    }

    // ==========================================
    // RENDER
    // ==========================================

    public function render()
    {
        // Estudiantes inscriptos (con filtro de estado)
        $inscripciones = $this->curso->inscripciones()
            ->with('estudiante', 'dadoDeBajaPor')
            ->when($this->filtroEstado === 'activo', fn ($q) => $q->where('activo_local', true)->where('estado', 'activo'))
            ->when($this->filtroEstado === 'baja',   fn ($q) => $q->where('estado', 'inactivo'))
            ->when($this->filtroEstado === 'api',    fn ($q) => $q->where('origen', 'api'))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Búsqueda de estudiantes para agregar (no ya inscritos activos)
        $estudiantesBusqueda = collect();
        if (strlen($this->busquedaEstudiante) >= 2) {
            $yaInscritos = $this->curso->inscripcionesActivas()->pluck('estudiante_id');
            $estudiantesBusqueda = User::role('estudiante')
                ->whereNotIn('id', $yaInscritos)
                ->where(fn ($q) =>
                    $q->where('name', 'like', "%{$this->busquedaEstudiante}%")
                      ->orWhere('email', 'like', "%{$this->busquedaEstudiante}%")
                )
                ->where('activo', true)
                ->limit(8)
                ->get();
        }

        $totalActivos = $this->curso->inscripcionesActivas()->count();
        $totalBajas   = $this->curso->inscripciones()->where('estado', 'inactivo')->count();
        $totalApi     = $this->curso->inscripciones()->where('origen', 'api')->count();

        return view('livewire.cursos.gestion-matricula', [
            'inscripciones'       => $inscripciones,
            'estudiantesBusqueda' => $estudiantesBusqueda,
            'totalActivos'        => $totalActivos,
            'totalBajas'          => $totalBajas,
            'totalApi'            => $totalApi,
        ])->title("Gestión de Matrícula — {$this->curso->nombre}");
    }
}
