<?php

namespace App\Livewire\Estudiante;

use App\Models\Curso;
use App\Models\RubroEvaluacion;
use App\Services\Evaluacion\CalculadoraNotas;
use App\Services\Evaluacion\NotasDeEvaluacion;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-59: el estudiante ve cada rubro, su peso, su nota y la nota final
 * ponderada; RF-64: en rojo si queda bajo la nota mínima aprobatoria.
 * Cada estudiante solo ve su propia boleta (sin parámetros de otro usuario).
 */
#[Layout('layouts.app')]
class BoletaCurso extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        $usuario = auth()->user();

        abort_unless($usuario->hasRole('estudiante'), 403, 'La boleta es personal de cada estudiante.');
        abort_unless($usuario->estaMatriculadoEn($curso), 403, 'No estás matriculado en este curso.');

        $this->curso = $curso;
    }

    public function render(CalculadoraNotas $calculadora)
    {
        $esquema = $this->curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros = $esquema?->rubros ?? collect();
        $notaMinima = $calculadora->notaMinima();

        $notasMap = app(NotasDeEvaluacion::class)->resolver($rubros, collect([auth()->id()]))[auth()->id()] ?? [];

        $filas = $rubros->map(function (RubroEvaluacion $rubro) use ($notasMap, $notaMinima) {
            $nota = $notasMap[$rubro->id] ?? null;

            return [
                'rubro' => $rubro,
                'nota' => $nota,
                'aprobado' => $nota !== null && $nota >= $notaMinima,
                'aporte' => $nota !== null ? round($nota * ((float) $rubro->peso / 100), 2) : null,
            ];
        });

        $notaFinal = $calculadora->notaFinal($rubros, $notasMap);

        return view('livewire.estudiante.boleta-curso', [
            'esquema' => $esquema,
            'rubros' => $rubros,
            'filas' => $filas,
            'notaFinal' => $notaFinal,
            // Mientras falten rubros la nota es acumulada y la condición es "en_curso".
            'condicion' => $calculadora->condicion($rubros, $notasMap),
            'rubrosConNota' => count(array_filter($notasMap, fn ($nota) => $nota !== null)),
            'aprobado' => $calculadora->condicion($rubros, $notasMap) === 'aprobado',
            'notaMinima' => $notaMinima,
            'pesosOk' => $calculadora->pesosSumanCien($rubros->pluck('peso')),
        ])->title("Boleta — {$this->curso->nombre}");
    }
}
