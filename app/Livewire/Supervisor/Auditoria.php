<?php

namespace App\Livewire\Supervisor;

use App\Models\BitacoraAuditoria;
use App\Models\Curso;
use App\Services\HojasCalculo\LibroXlsx;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bitácora de auditoría (módulo 17).
 * ERS RF-97: solo el Supervisor la consulta (ruta con role:supervisor y control en mount).
 * ERS RF-98: filtros por rango de fechas, usuario ejecutor, tipo de evento y curso.
 * ERS RF-99: solo lectura; el modelo impide modificar o eliminar registros.
 */
#[Layout('layouts.app')]
class Auditoria extends Component
{
    use WithPagination;

    public const MAX_EXPORTACION = 5000;

    public string $filtroTipo = '';

    public string $filtroCursoId = '';

    public string $filtroDesde = '';

    public string $filtroHasta = '';

    public string $filtroUsuario = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updating(string $propiedad): void
    {
        if (str_starts_with($propiedad, 'filtro')) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['filtroTipo', 'filtroCursoId', 'filtroDesde', 'filtroHasta', 'filtroUsuario']);
        $this->resetPage();
    }

    public function updated(string $propiedad): void
    {
        if (in_array($propiedad, ['filtroDesde', 'filtroHasta'], true)) {
            $this->validate([
                'filtroDesde' => ['nullable', 'date'],
                'filtroHasta' => array_filter(['nullable', 'date', $this->filtroDesde !== '' ? 'after_or_equal:filtroDesde' : null]),
            ], [
                'filtroHasta.after_or_equal' => 'La fecha final no puede ser anterior a la inicial.',
            ]);
        }
    }

    /**
     * Consulta con los filtros; los valores inválidos se ignoran.
     */
    protected function consulta(): Builder
    {
        $tipo = array_key_exists($this->filtroTipo, BitacoraAuditoria::TIPOS) ? $this->filtroTipo : null;
        $desde = strtotime($this->filtroDesde) ? date('Y-m-d', strtotime($this->filtroDesde)) : null;
        $hasta = strtotime($this->filtroHasta) ? date('Y-m-d', strtotime($this->filtroHasta)) : null;
        $usuario = mb_substr(trim($this->filtroUsuario), 0, 100);

        return BitacoraAuditoria::with(['ejecutadoPor', 'usuarioAfectado', 'curso'])
            ->when($tipo, fn ($q) => $q->where('tipo_evento', $tipo))
            ->when(ctype_digit($this->filtroCursoId), fn ($q) => $q->where('curso_id', (int) $this->filtroCursoId))
            ->when($desde, fn ($q) => $q->where('created_at', '>=', $desde.' 00:00:00'))
            ->when($hasta, fn ($q) => $q->where('created_at', '<=', $hasta.' 23:59:59'))
            ->when($usuario !== '', fn ($q) => $q->whereHas('ejecutadoPor', fn ($u) => $u->where(fn ($u) => $u
                ->where('name', 'like', "%{$usuario}%")
                ->orWhere('email', 'like', "%{$usuario}%")
            )))
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * ERS RF-98: exporta a Excel los registros que cumplen los filtros actuales
     * (máximo 5000), con una hoja de resumen de los filtros aplicados.
     */
    public function exportarExcel(): StreamedResponse
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $registros = $this->consulta()->limit(self::MAX_EXPORTACION)->get();
        $curso = $this->filtroCursoId !== '' ? Curso::find((int) $this->filtroCursoId) : null;
        $subtitulo = 'Generado el '.now()->formatoFechaHora().' por '.auth()->user()->name;

        $libro = new LibroXlsx('Bitácora de auditoría');

        $filtros = $libro->hoja('Filtros');
        $filtros->titulo('Bitácora de auditoría', $subtitulo)
            ->seccion('Filtros aplicados')
            ->pares([
                'Tipo de evento' => $this->filtroTipo !== '' ? (BitacoraAuditoria::TIPOS[$this->filtroTipo] ?? $this->filtroTipo) : 'Todos',
                'Curso' => $curso?->nombre ?? 'Todos',
                'Usuario' => $this->filtroUsuario !== '' ? $this->filtroUsuario : 'Todos',
                'Desde' => $this->filtroDesde !== '' ? $this->filtroDesde : '—',
                'Hasta' => $this->filtroHasta !== '' ? $this->filtroHasta : '—',
                'Registros exportados' => $registros->count(),
            ])
            ->espacio()
            ->seccion('Registros por tipo')
            ->encabezados(['Tipo de evento', 'Cantidad']);

        foreach ($registros->groupBy(fn (BitacoraAuditoria $registro) => $registro->etiquetaTipo())->sortKeys() as $tipo => $grupo) {
            $filtros->fila([$tipo, $grupo->count()]);
        }

        $hoja = $libro->hoja('Registros')
            ->titulo('Registros de la bitácora', $subtitulo.' · Registros inmutables (ERS RF-99)')
            ->encabezados(['Fecha', 'Ejecutado por', 'Tipo', 'Curso', 'Usuario afectado', 'Descripción', 'Valor anterior', 'Valor nuevo', 'Motivo', 'IP'], fijar: true);

        foreach ($registros as $registro) {
            $hoja->fila([
                $registro->created_at,
                $registro->ejecutadoPor?->name ?? 'Sistema externo (API)',
                $registro->etiquetaTipo(),
                $registro->curso?->nombre ?? '',
                $registro->usuarioAfectado?->name ?? '',
                $registro->descripcion ?? '',
                $registro->valor_anterior ?? '',
                $registro->valor_nuevo ?? '',
                $registro->motivo ?? '',
                $registro->ip ?? '',
            ]);
        }

        if ($registros->isEmpty()) {
            $hoja->fila(['', 'No hay registros con los filtros aplicados.']);
        }

        return $libro->descargar('bitacora-auditoria-'.now()->format('Ymd-His').'.xlsx');
    }

    public function render()
    {
        return view('livewire.supervisor.auditoria', [
            'registros' => $this->consulta()->paginate(25),
            'cursos' => Curso::orderBy('nombre')->get(['id', 'nombre']),
            'tiposEvento' => BitacoraAuditoria::TIPOS,
        ])->title('Bitácora de auditoría — LearnUp');
    }
}
