<?php

namespace App\Livewire\PanelAdmin;

use App\Models\BitacoraAuditoria;
use App\Models\IntegracionExterna;
use App\Models\LogIntegracionApi;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Integración externa (Supervisor).
 * ERS RF-101: una API Key por institución/empresa cliente.
 * ERS RNF-04: las API Keys pueden revocarse y regenerarse.
 * ERS RF-106: consulta del log de solicitudes recibidas.
 *
 * La API Key en texto plano solo se muestra una vez (al crearla o
 * regenerarla); en la base de datos se guarda únicamente su hash.
 */
#[Layout('layouts.app')]
class Integraciones extends Component
{
    use WithPagination;

    public bool $mostrarModalCrear = false;

    public string $nombre = '';

    public string $descripcion = '';

    public ?string $apiKeyVisible = null;

    public ?string $apiKeyNombre = null;

    public string $filtroLog = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);
    }

    public function updatingFiltroLog(): void
    {
        $this->resetPage();
    }

    public function crear(): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->validate([
            'nombre' => ['required', 'string', 'max:150'],
            'descripcion' => ['nullable', 'string', 'max:500'],
        ], [
            'nombre.required' => 'Indica el nombre de la institución o empresa cliente.',
        ]);

        [$integracion, $apiKey] = IntegracionExterna::generarApiKey($this->nombre, $this->descripcion ?: null);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'integracion_api',
            descripcion: "API Key creada para \"{$integracion->nombre}\" (prefijo {$integracion->api_key_prefix}).",
        );

        $this->mostrarModalCrear = false;
        $this->reset(['nombre', 'descripcion']);
        $this->mostrarKey($integracion, $apiKey);
    }

    public function regenerar(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $integracion = IntegracionExterna::findOrFail($id);
        $prefijoAnterior = $integracion->api_key_prefix;
        $apiKey = $integracion->regenerarApiKey();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'integracion_api',
            descripcion: "API Key regenerada para \"{$integracion->nombre}\". La anterior quedó invalidada.",
            valorAnterior: $prefijoAnterior,
            valorNuevo: $integracion->api_key_prefix,
        );

        $this->mostrarKey($integracion, $apiKey);
    }

    public function revocar(int $id): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $integracion = IntegracionExterna::findOrFail($id);
        $integracion->revocar();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'integracion_api',
            descripcion: "API Key revocada para \"{$integracion->nombre}\" (prefijo {$integracion->api_key_prefix}).",
        );

        session()->flash('estado', "La API Key de \"{$integracion->nombre}\" fue revocada. Las solicitudes con esa key serán rechazadas.");
    }

    public function ocultarKey(): void
    {
        $this->reset(['apiKeyVisible', 'apiKeyNombre']);
    }

    protected function mostrarKey(IntegracionExterna $integracion, string $apiKey): void
    {
        $this->apiKeyVisible = $apiKey;
        $this->apiKeyNombre = $integracion->nombre;
    }

    public function render()
    {
        return view('livewire.panel-admin.integraciones', [
            'integraciones' => IntegracionExterna::withCount('logs')->orderBy('nombre')->get(),
            'logs' => LogIntegracionApi::with('integracion')
                ->when($this->filtroLog === 'exito', fn ($q) => $q->where('status_code', '<', 400))
                ->when($this->filtroLog === 'error', fn ($q) => $q->where('status_code', '>=', 400))
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(15),
            'endpoint' => url('/api/v1/matriculas'),
        ])->title('Integración API — LearnUp');
    }
}
