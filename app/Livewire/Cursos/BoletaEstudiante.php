<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\NotaRubro;
use App\Models\RubroEvaluacion;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * ERS RF-59: El estudiante puede ver su nota por rubro y nota final ponderada.
 * ERS RF-63: Vista preparada para impresión (PDF futuro).
 */
#[Layout('layouts.app')]
class BoletaEstudiante extends Component
{
    public Curso $curso;

    public function mount(Curso $curso): void
    {
        $user = auth()->user();

        // El estudiante solo puede ver su propia boleta si está inscrito
        if ($user->hasRole('estudiante')) {
            $inscrito = Inscripcion::where('curso_id', $curso->id)
                ->where('estudiante_id', $user->id)
                ->where('activo_local', true)
                ->exists();
            abort_unless($inscrito, 403, 'No estás inscrito en este curso.');
        } else {
            // Supervisor y profesor también pueden ver la boleta de cualquier estudiante
            abort_unless($user->hasAnyRole(['supervisor', 'profesor']), 403);
        }

        $this->curso = $curso;
    }

    public function render()
    {
        $user    = auth()->user();
        $esquema = $this->curso->esquemaEvaluacion()->with('rubros')->first();
        $rubros  = $esquema?->rubros ?? collect();

        $rubroIds   = $rubros->pluck('id');
        $notaMinima = config('app.nota_minima_aprobatoria', 10.5);

        $notasMap = NotaRubro::whereIn('rubro_id', $rubroIds)
            ->where('estudiante_id', $user->id)
            ->get()
            ->keyBy('rubro_id');

        $filas = $rubros->map(function (RubroEvaluacion $rubro) use ($notasMap, $notaMinima) {
            $nota = $notasMap->get($rubro->id);
            return [
                'rubro'    => $rubro,
                'nota'     => $nota?->nota,
                'aprobado' => $nota !== null && $nota->nota >= $notaMinima,
                'aporte'   => $nota?->nota !== null
                    ? round($nota->nota * ($rubro->peso / 100), 2)
                    : null,
            ];
        });

        $notaFinal = null;
        $pesoTotal = 0;
        $sumaFinal = 0;
        foreach ($filas as $f) {
            if ($f['nota'] !== null) {
                $sumaFinal += $f['nota'] * ($f['rubro']->peso / 100);
                $pesoTotal += $f['rubro']->peso;
            }
        }
        if ($pesoTotal > 0) {
            $notaFinal = round($sumaFinal, 1);
        }

        $aprobado   = $notaFinal !== null && $notaFinal >= $notaMinima;
        $sumaPesos  = $esquema?->sumaPesos() ?? 0;
        $pesosOk    = abs($sumaPesos - 100) < 0.01;

        return view('livewire.cursos.boleta-estudiante', [
            'esquema'    => $esquema,
            'rubros'     => $rubros,
            'filas'      => $filas,
            'notaFinal'  => $notaFinal,
            'aprobado'   => $aprobado,
            'notaMinima' => $notaMinima,
            'pesosOk'    => $pesosOk,
        ])->title("Boleta — {$this->curso->nombre}");
    }
}
