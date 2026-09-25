<?php

namespace App\Livewire\PanelAdmin;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * ERS RF-97: solo el Supervisor puede ver la bitácora de auditoría.
 * ERS RF-98: filtros por fechas, usuario, tipo de evento y curso.
 * ERS RF-99: los registros son inmutables (solo lectura).
 */
#[Layout('layouts.app')]
class Auditoria extends Component
{
    use WithPagination;

    // Filtros (ERS RF-98)
    public string $filtroTipo = '';
    public string $filtroCursoId = '';
    public string $filtroDesde = '';
    public string $filtroHasta = '';
    public string $filtroUsuario = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updatingFiltroTipo(): void { $this->resetPage(); }
    public function updatingFiltroCursoId(): void { $this->resetPage(); }
    public function updatingFiltroDesde(): void { $this->resetPage(); }
    public function updatingFiltroHasta(): void { $this->resetPage(); }
    public function updatingFiltroUsuario(): void { $this->resetPage(); }

    public function render()
    {
        $query = BitacoraAuditoria::with(['ejecutadoPor', 'usuarioAfectado', 'curso'])
            ->when($this->filtroTipo, fn ($q) => $q->where('tipo_evento', $this->filtroTipo))
            ->when($this->filtroCursoId, fn ($q) => $q->where('curso_id', $this->filtroCursoId))
            ->when($this->filtroDesde, fn ($q) => $q->where('created_at', '>=', $this->filtroDesde))
            ->when($this->filtroHasta, fn ($q) => $q->where('created_at', '<=', $this->filtroHasta . ' 23:59:59'))
            ->when($this->filtroUsuario, fn ($q) => $q->whereHas(
                'ejecutadoPor',
                fn ($u) => $u->where('name', 'like', "%{$this->filtroUsuario}%")
                             ->orWhere('email', 'like', "%{$this->filtroUsuario}%")
            ))
            ->orderByDesc('created_at');

        $registros = $query->paginate(25);
        $cursos = Curso::orderBy('nombre')->get(['id', 'nombre']);

        $tiposEvento = [
            'cambio_nota'          => 'Cambio de nota',
            'baja_estudiante'      => 'Baja de estudiante',
            'creacion_cuenta'      => 'Creación de cuenta',
            'desactivacion_cuenta' => 'Desactivación de cuenta',
            'matricula_manual'     => 'Matrícula manual',
            'matricula_api'        => 'Matrícula por API',
            'modificacion_esquema' => 'Modificación de Esquema',
            'otro_admin'           => 'Otra acción',
        ];

        return view('livewire.panel-admin.auditoria', [
            'registros'   => $registros,
            'cursos'      => $cursos,
            'tiposEvento' => $tiposEvento,
        ])->title('Bitácora de Auditoría — LearnUp');
    }
}
