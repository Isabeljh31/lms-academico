<?php

namespace App\Livewire\PanelAdmin;

use App\Models\BitacoraAuditoria;
use App\Services\ConfiguracionPlataforma;
use DateTimeZone;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Configuración general de la plataforma (solo Supervisor).
 * ERS RF-107: nota mínima aprobatoria general (escala 0–20), aplicada a todos
 *             los cursos (CalculadoraNotas la lee en cada cálculo).
 * ERS RF-108: nombre de la institución, zona horaria y formato de fecha.
 * ERS RF-96: cada cambio queda en la bitácora con el valor anterior y el nuevo.
 */
#[Layout('layouts.app')]
class Configuracion extends Component
{
    /** @var array<string, string> */
    public const FORMATOS_FECHA = ConfiguracionPlataforma::FORMATOS_FECHA;

    public bool $botActivo = true;

    public string $notaMinima = '10.5';

    public string $nombreInstitucion = '';

    public string $zonaHoraria = 'America/Lima';

    public string $formatoFecha = 'd/m/Y';

    public function mount(ConfiguracionPlataforma $configuracion): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->botActivo = $configuracion->botActivo();
        $this->notaMinima = (string) $configuracion->notaMinimaAprobatoria();
        $this->nombreInstitucion = (string) $configuracion->obtener('nombre_institucion');
        $this->zonaHoraria = (string) $configuracion->obtener('zona_horaria');
        $this->formatoFecha = (string) $configuracion->obtener('formato_fecha');
    }

    public function guardar(ConfiguracionPlataforma $configuracion): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->validate([
            'notaMinima' => ['required', 'numeric', 'min:0', 'max:20', 'decimal:0,1'],
            'nombreInstitucion' => ['required', 'string', 'max:150'],
            'zonaHoraria' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'formatoFecha' => ['required', Rule::in(array_keys(self::FORMATOS_FECHA))],
        ], [
            'notaMinima.max' => 'La nota mínima debe estar entre 0 y 20.',
            'notaMinima.decimal' => 'Usa como máximo un decimal.',
            'zonaHoraria.in' => 'Selecciona una zona horaria válida.',
        ]);

        $nuevos = [
            'nota_minima_aprobatoria' => round((float) $this->notaMinima, 1),
            'nombre_institucion' => trim($this->nombreInstitucion),
            'zona_horaria' => $this->zonaHoraria,
            'formato_fecha' => $this->formatoFecha,
        ];

        $anteriores = collect($nuevos)->mapWithKeys(fn ($valor, $clave) => [$clave => $configuracion->obtener($clave)])->all();
        $cambios = array_filter($nuevos, fn ($valor, $clave) => $anteriores[$clave] != $valor, ARRAY_FILTER_USE_BOTH);

        if ($cambios === []) {
            session()->flash('estado', 'No hubo cambios que guardar.');

            return;
        }

        $configuracion->guardar($cambios);
        $configuracion->aplicarZonaHoraria();

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'configuracion',
            descripcion: 'Configuración general actualizada: '.implode(', ', array_keys($cambios)).'.',
            valorAnterior: array_intersect_key($anteriores, $cambios),
            valorNuevo: $cambios,
        );

        session()->flash('estado', 'Configuración guardada.'
            .(isset($cambios['nota_minima_aprobatoria']) ? " La nota mínima aprobatoria ahora es {$cambios['nota_minima_aprobatoria']} en todos los cursos." : ''));
    }

    public function alternarBot(ConfiguracionPlataforma $configuracion): void
    {
        abort_unless(auth()->user()->hasRole('supervisor'), 403);

        $this->botActivo = ! $this->botActivo;
        $configuracion->establecerBotActivo($this->botActivo);

        BitacoraAuditoria::registrarAccionAdmin(
            ejecutor: auth()->user(),
            tipoEvento: 'configuracion',
            descripcion: $this->botActivo ? 'Asistente de IA activado.' : 'Asistente de IA desactivado.',
            valorAnterior: ! $this->botActivo,
            valorNuevo: $this->botActivo,
        );

        session()->flash('estado', $this->botActivo
            ? 'El asistente de IA está activado en toda la plataforma.'
            : 'El asistente de IA está desactivado en toda la plataforma.');
    }

    public function render()
    {
        return view('livewire.panel-admin.configuracion', [
            'formatosFecha' => self::FORMATOS_FECHA,
            'zonasHorarias' => collect(DateTimeZone::listIdentifiers(DateTimeZone::AMERICA))->push('UTC'),
        ])->title('Configuración — LearnUp');
    }
}
