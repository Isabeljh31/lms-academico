<?php

namespace App\Services\Evaluacion;

use App\Models\Curso;
use App\Models\RubroEvaluacion;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use Illuminate\Support\Collection;

/**
 * Reglas de cálculo del Esquema de Evaluación (ERS 3.7).
 *
 * - RF-56: la suma de pesos de los rubros debe ser exactamente 100 %.
 * - RF-58: Nota Final = Σ (nota del rubro × peso / 100), escala 0–20.
 * - Las actividades solo participan cuando el profesor activa el cálculo
 *   automático del rubro y las selecciona expresamente.
 * - RF-64: la nota final se compara con la nota mínima aprobatoria general.
 *
 * Mientras no estén registradas las notas de todos los rubros, la nota final
 * es acumulada (los rubros pendientes aportan 0) y la condición del
 * estudiante es "En curso": todavía no aprueba ni desaprueba. Para reportes
 * durante el periodo se usa el rendimiento a la fecha (promedio ponderado de
 * los rubros ya calificados).
 */
class CalculadoraNotas
{
    public const TOLERANCIA = 0.01;

    public function __construct(protected ConfiguracionPlataforma $configuracion) {}

    /**
     * @param  iterable<int|float|string|null>  $pesos
     */
    public function pesosSumanCien(iterable $pesos): bool
    {
        return abs($this->sumarPesos($pesos) - 100.0) < self::TOLERANCIA;
    }

    /**
     * @param  iterable<int|float|string|null>  $pesos
     */
    public function sumarPesos(iterable $pesos): float
    {
        $suma = 0.0;

        foreach ($pesos as $peso) {
            $suma += (float) $peso;
        }

        return round($suma, 2);
    }

    /**
     * Nota final ponderada. Los rubros sin nota aportan 0 (la nota aún no se
     * registró). Devuelve null si no hay ninguna nota registrada.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  array<int, float|string|null>  $notasPorRubro  rubro_id => nota
     */
    public function notaFinal(Collection $rubros, array $notasPorRubro): ?float
    {
        $suma = 0.0;
        $hayNotas = false;

        foreach ($rubros as $rubro) {
            $nota = $notasPorRubro[$rubro->id] ?? null;

            if ($nota === null || $nota === '') {
                continue;
            }

            $hayNotas = true;
            $suma += (float) $nota * ((float) $rubro->peso / 100);
        }

        return $hayNotas ? round($suma, 1) : null;
    }

    /**
     * Todos los rubros del esquema tienen nota registrada.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  array<int, float|string|null>  $notasPorRubro
     */
    public function estaCompleta(Collection $rubros, array $notasPorRubro): bool
    {
        return $rubros->isNotEmpty()
            && $rubros->every(fn ($rubro) => ($notasPorRubro[$rubro->id] ?? null) !== null && ($notasPorRubro[$rubro->id] ?? '') !== '');
    }

    /**
     * Rendimiento a la fecha en escala 0–20: promedio ponderado solo de los
     * rubros con nota. Con el esquema completo coincide con la nota final.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  array<int, float|string|null>  $notasPorRubro
     */
    public function rendimiento(Collection $rubros, array $notasPorRubro): ?float
    {
        $suma = 0.0;
        $pesos = 0.0;

        foreach ($rubros as $rubro) {
            $nota = $notasPorRubro[$rubro->id] ?? null;

            if ($nota === null || $nota === '') {
                continue;
            }

            $suma += (float) $nota * (float) $rubro->peso;
            $pesos += (float) $rubro->peso;
        }

        return $pesos > 0 ? round($suma / $pesos, 1) : null;
    }

    /**
     * Condición del estudiante: aprobado | desaprobado (esquema completo),
     * en_curso (faltan rubros) o sin_notas.
     *
     * @param  Collection<int, RubroEvaluacion>  $rubros
     * @param  array<int, float|string|null>  $notasPorRubro
     */
    public function condicion(Collection $rubros, array $notasPorRubro): string
    {
        $notaFinal = $this->notaFinal($rubros, $notasPorRubro);

        return match (true) {
            $notaFinal === null => 'sin_notas',
            ! $this->estaCompleta($rubros, $notasPorRubro) => 'en_curso',
            $this->aprueba($notaFinal) => 'aprobado',
            default => 'desaprobado',
        };
    }

    /**
     * Nota final, rendimiento a la fecha y condición de cada estudiante
     * indicado, con consultas por lote (para reportes y paneles).
     *
     * @param  Collection<int, int>  $estudianteIds
     * @return array<int, array{nota_final: float|null, rendimiento: float|null, condicion: string}>
     */
    public function resumenDelCurso(Curso $curso, Collection $estudianteIds): array
    {
        $rubros = $curso->esquemaEvaluacion?->rubros ?? collect();

        $notas = app(NotasDeEvaluacion::class)->resolver($rubros, $estudianteIds);

        return $estudianteIds->mapWithKeys(function (int $id) use ($rubros, $notas) {
            $notasEstudiante = $notas[$id] ?? [];

            return [$id => [
                'nota_final' => $this->notaFinal($rubros, $notasEstudiante),
                'rendimiento' => $this->rendimiento($rubros, $notasEstudiante),
                'condicion' => $this->condicion($rubros, $notasEstudiante),
            ]];
        })->all();
    }

    /**
     * Porcentaje de aprobados entre quienes ya tienen todos los rubros
     * calificados. Null mientras nadie completa el esquema.
     *
     * @param  iterable<array{condicion: string}>  $resumen
     */
    public function tasaAprobacion(iterable $resumen): ?int
    {
        $condiciones = collect($resumen)->pluck('condicion');
        $completos = $condiciones->filter(fn (string $condicion) => in_array($condicion, ['aprobado', 'desaprobado'], true))->count();

        return $completos > 0 ? (int) round($condiciones->filter(fn ($c) => $c === 'aprobado')->count() / $completos * 100) : null;
    }

    public function notaMinima(): float
    {
        return $this->configuracion->notaMinimaAprobatoria();
    }

    public function aprueba(?float $notaFinal): bool
    {
        return $notaFinal !== null && $notaFinal >= $this->notaMinima();
    }

    /**
     * Nota final de un estudiante en un curso (null si no hay esquema o notas).
     */
    public function notaFinalDe(User $estudiante, Curso $curso): ?float
    {
        $rubros = $curso->esquemaEvaluacion?->rubros ?? collect();

        if ($rubros->isEmpty()) {
            return null;
        }

        $notas = app(NotasDeEvaluacion::class)->resolver($rubros, collect([$estudiante->id]))[$estudiante->id] ?? [];

        return $this->notaFinal($rubros, $notas);
    }

    /**
     * Rendimiento a la fecha de un estudiante en un curso (null sin notas).
     */
    public function rendimientoDe(User $estudiante, Curso $curso): ?float
    {
        $rubros = $curso->esquemaEvaluacion?->rubros ?? collect();

        $notas = app(NotasDeEvaluacion::class)->resolver($rubros, collect([$estudiante->id]))[$estudiante->id] ?? [];

        return $this->rendimiento($rubros, $notas);
    }

    /**
     * Notas finales de todos los estudiantes indicados, con consultas por lote.
     *
     * @param  Collection<int, int>  $estudianteIds
     * @return array<int, float|null> estudiante_id => nota final
     */
    public function notasFinalesDelCurso(Curso $curso, Collection $estudianteIds): array
    {
        $rubros = $curso->esquemaEvaluacion?->rubros ?? collect();

        $notas = app(NotasDeEvaluacion::class)->resolver($rubros, $estudianteIds);

        return $estudianteIds->mapWithKeys(fn (int $id) => [
            $id => $this->notaFinal($rubros, $notas[$id] ?? []),
        ])->all();
    }
}
