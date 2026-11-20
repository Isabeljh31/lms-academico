<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\RubroEvaluacion;
use App\Services\Evaluacion\PracticasEnEvaluaciones;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Panel del Profesor (pantalla Actividades): reparte el % que el Supervisor
 * fijó para las prácticas de campo entre una o varias evaluaciones, con un
 * tramo de prácticas por evaluación. Ejemplos con 12 prácticas y 30 %:
 * Eva1 = 1-4 (10 %), Eva2 = 5-8 (10 %), Eva3 = 9-12 (10 %); o Eva1 = 1-12 (30 %).
 * Las reglas y el guardado están en Services\Evaluacion\PracticasEnEvaluaciones.
 */
class PracticasEvaluaciones extends Component
{
    #[Locked]
    public Curso $curso;

    #[Locked]
    public bool $editable = true;

    /**
     * Tramo de cada evaluación (id => desde, hasta, %), como texto del formulario.
     *
     * @var array<int, array{desde: string, hasta: string, peso: string}>
     */
    public array $tramos = [];

    public function mount(Curso $curso, bool $editable = true): void
    {
        Gate::authorize('calificar', $curso);

        $this->curso = $curso;
        $this->editable = $editable && ! $curso->estaFinalizado();
        $this->tramos = $this->rubros()->mapWithKeys(fn (RubroEvaluacion $rubro) => [$rubro->id => [
            'desde' => (string) ($rubro->practica_desde ?? ''),
            'hasta' => (string) ($rubro->practica_hasta ?? ''),
            'peso' => $rubro->peso_practicas !== null ? $this->formato((float) $rubro->peso_practicas) : '',
        ]])->all();
    }

    /** Rellena un reparto en bloques consecutivos y % en partes iguales (el profesor puede ajustarlo antes de guardar). */
    public function sugerirReparto(PracticasEnEvaluaciones $servicio): void
    {
        $sugerido = $servicio->repartoSugerido($this->rubros(), $this->curso->totalPracticasCampo(), $this->pesoTotal());

        $this->resetErrorBag();
        $this->tramos = $this->rubros()->mapWithKeys(fn (RubroEvaluacion $rubro) => [$rubro->id => isset($sugerido[$rubro->id])
            ? ['desde' => (string) $sugerido[$rubro->id]['desde'], 'hasta' => (string) $sugerido[$rubro->id]['hasta'], 'peso' => $this->formato($sugerido[$rubro->id]['peso'])]
            : ['desde' => '', 'hasta' => '', 'peso' => ''],
        ])->all();
    }

    /** Todas las prácticas en una sola evaluación, con el total del Supervisor. */
    public function todasEn(int $rubroId): void
    {
        abort_unless($this->rubros()->contains('id', $rubroId), 404);

        $this->resetErrorBag();
        $this->tramos = collect($this->tramos)->map(fn ($tramo, $id) => (int) $id === $rubroId
            ? ['desde' => '1', 'hasta' => (string) $this->curso->totalPracticasCampo(), 'peso' => $this->formato($this->pesoTotal())]
            : ['desde' => '', 'hasta' => '', 'peso' => ''])->all();
    }

    public function guardar(PracticasEnEvaluaciones $servicio): void
    {
        Gate::authorize('calificar', $this->curso);
        abort_unless($this->editable, 403);

        $this->validate([
            'tramos' => ['array'],
            'tramos.*.desde' => ['nullable', 'integer', 'min:1'],
            'tramos.*.hasta' => ['nullable', 'integer', 'min:1'],
            'tramos.*.peso' => ['nullable', 'numeric', 'decimal:0,2'],
        ], [
            'tramos.*.desde.integer' => 'Escribe un número de práctica.',
            'tramos.*.hasta.integer' => 'Escribe un número de práctica.',
            'tramos.*.peso.numeric' => 'Escribe un % válido (hasta 2 decimales).',
            'tramos.*.peso.decimal' => 'Escribe un % válido (hasta 2 decimales).',
        ]);

        $servicio->guardar($this->curso, collect($this->tramos)->map(fn (array $tramo) => [
            'desde' => $tramo['desde'] !== '' ? (int) $tramo['desde'] : null,
            'hasta' => $tramo['hasta'] !== '' ? (int) $tramo['hasta'] : null,
            'peso' => $tramo['peso'] !== '' ? round((float) str_replace(',', '.', $tramo['peso']), 2) : null,
        ])->all(), auth()->user());

        session()->flash('estado', 'Reparto de las prácticas de campo guardado.');
        $this->redirectRoute('cursos.actividades', $this->curso, navigate: true);
    }

    /** @return Collection<int, RubroEvaluacion> */
    protected function rubros(): Collection
    {
        return $this->curso->esquemaEvaluacion?->rubros()->orderBy('orden')->get() ?? collect();
    }

    protected function pesoTotal(): float
    {
        return (float) ($this->curso->esquemaEvaluacion?->peso_practicas_campo ?? 0);
    }

    protected function formato(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 2, '.', ''), '0'), '.');
    }

    public function render()
    {
        $repartido = round(collect($this->tramos)->sum(fn (array $tramo) => is_numeric(str_replace(',', '.', $tramo['peso'])) ? (float) str_replace(',', '.', $tramo['peso']) : 0), 2);

        return view('livewire.cursos.practicas-evaluaciones', [
            'rubros' => $this->rubros(),
            'totalPracticas' => $this->curso->totalPracticasCampo(),
            'pesoTotal' => $this->pesoTotal(),
            'repartido' => $repartido,
        ]);
    }
}
