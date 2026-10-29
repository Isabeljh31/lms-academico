<?php

namespace App\Services\Evaluacion;

use App\Models\Curso;
use App\Models\User;
use App\Services\ConfiguracionPlataforma;
use App\Services\HojasCalculo\HojaXlsx;
use App\Services\HojasCalculo\LibroXlsx;
use Illuminate\Support\Collection;

/**
 * ERS RF-63: exportación del libro de calificaciones a Excel, organizada en
 * tres hojas:
 *   1. Resumen: datos del curso, indicadores y Esquema de Evaluación.
 *   2. Notas por rubro: nota de cada Rubro y Nota Final ponderada (RF-58),
 *      en rojo bajo la nota mínima (RF-64), con fila de promedios.
 *   3. Práctica: tareas y quizzes, aparte y sin entrar en la nota final (RF-60).
 */
class ExportadorLibroCalificaciones
{
    public function __construct(protected CalculadoraNotas $calculadora) {}

    /**
     * @param  Collection<int, array{estudiante: User, notas: array<int, float|null>, nota_final: float|null, rendimiento: float|null, condicion: string, rubros_con_nota: int, aprobado: bool}>  $filas
     * @param  array<string, float|null>  $notasPractica  "{estudiante_id}_t{tarea_id}" / "{estudiante_id}_q{quiz_id}" => nota
     */
    public function libro(Curso $curso, Collection $filas, array $notasPractica = []): LibroXlsx
    {
        $curso->loadMissing(['esquemaEvaluacion.rubros', 'profesor', 'profesoresAdicionales']);
        $rubros = $curso->esquemaEvaluacion?->rubros ?? collect();
        $notaMinima = $this->calculadora->notaMinima();
        $institucion = app(ConfiguracionPlataforma::class)->obtener('nombre_institucion') ?: 'LearnUp';
        $subtitulo = $institucion.' · '.$curso->nombre.($curso->codigo ? " ({$curso->codigo})" : '').' · Generado el '.now()->formatoFechaHora();

        $libro = new LibroXlsx("Libro de calificaciones — {$curso->nombre}");

        $this->hojaResumen($libro->hoja('Resumen'), $curso, $rubros, $filas, $notaMinima, $subtitulo);
        $this->hojaNotas($libro->hoja('Notas por rubro'), $rubros, $filas, $notaMinima, $subtitulo);
        $this->hojaPractica($libro->hoja('Práctica'), $curso, $filas, $notasPractica, $subtitulo);

        return $libro;
    }

    protected function hojaResumen(HojaXlsx $hoja, Curso $curso, Collection $rubros, Collection $filas, float $notaMinima, string $subtitulo): void
    {
        $rendimientos = $filas->pluck('rendimiento')->filter(fn ($nota) => $nota !== null);
        $aprobados = $filas->where('condicion', 'aprobado')->count();
        $desaprobados = $filas->where('condicion', 'desaprobado')->count();
        $completos = $aprobados + $desaprobados;

        $hoja->titulo('Libro de calificaciones', $subtitulo)
            ->seccion('Datos del curso')
            ->pares([
                'Curso' => $curso->nombre,
                'Código' => $curso->codigo ?? '—',
                'Profesor(es)' => $curso->docentes()->pluck('name')->join(', ') ?: '—',
                'Periodo' => $curso->fecha_inicio
                    ? $curso->fecha_inicio->formatoFecha().' al '.($curso->fecha_fin?->formatoFecha() ?? '—')
                    : '—',
                'Duración' => $curso->semanas_duracion ? "{$curso->semanas_duracion} semanas" : '—',
                'Estado' => ucfirst($curso->estado),
                'Nota mínima aprobatoria' => $notaMinima,
            ])
            ->espacio()
            ->seccion('Indicadores')
            ->pares([
                'Estudiantes matriculados' => $filas->count(),
                'Con todos los rubros calificados' => $completos,
                'En curso (faltan rubros)' => $filas->where('condicion', 'en_curso')->count(),
                'Aprobados' => HojaXlsx::celda($aprobados, HojaXlsx::ENTERO),
                'Desaprobados' => HojaXlsx::celda($desaprobados, HojaXlsx::ENTERO),
                'Tasa de aprobación' => $completos ? HojaXlsx::celda(round($aprobados / $completos * 100, 1), HojaXlsx::PORCENTAJE) : 'Se calcula al completar los rubros',
                'Promedio a la fecha' => $rendimientos->isNotEmpty() ? round($rendimientos->avg(), 2) : '—',
                'Rendimiento más alto' => $rendimientos->isNotEmpty() ? (float) $rendimientos->max() : '—',
                'Rendimiento más bajo' => $rendimientos->isNotEmpty() ? (float) $rendimientos->min() : '—',
            ])
            ->espacio()
            ->seccion('Esquema de evaluación')
            ->encabezados(['Rubro', 'Semana', 'Peso (%)']);

        foreach ($rubros as $rubro) {
            $hoja->fila([$rubro->nombre, (int) $rubro->semana, HojaXlsx::celda((float) $rubro->peso, HojaXlsx::PORCENTAJE)]);
        }

        $hoja->filaTotal(['Total', '', HojaXlsx::celda($this->calculadora->sumarPesos($rubros->pluck('peso')), HojaXlsx::TOTAL_PORCENTAJE)]);
    }

    protected function hojaNotas(HojaXlsx $hoja, Collection $rubros, Collection $filas, float $notaMinima, string $subtitulo): void
    {
        $hoja->titulo('Notas por rubro de evaluación', $subtitulo.' · Escala 0-20 · En rojo: bajo la nota mínima ('.$notaMinima.') · La nota final es acumulada mientras falten rubros')
            ->encabezados([
                'N°', 'Estudiante', 'Correo',
                ...$rubros->map(fn ($rubro) => "{$rubro->nombre} (".(float) $rubro->peso.' %)')->all(),
                'Nota final', 'Rendimiento a la fecha', 'Condición',
            ], fijar: true);

        foreach ($filas->values() as $indice => $fila) {
            $final = $fila['nota_final'];

            $hoja->fila([
                $indice + 1,
                $fila['estudiante']->name,
                $fila['estudiante']->email,
                ...$rubros->map(fn ($rubro) => $this->nota($fila['notas'][$rubro->id] ?? null, $notaMinima))->all(),
                $final === null ? '' : HojaXlsx::celda((float) $final, match ($fila['condicion']) {
                    'aprobado' => HojaXlsx::DECIMAL_EXITO,
                    'desaprobado' => HojaXlsx::DECIMAL_ALERTA,
                    default => HojaXlsx::DECIMAL,
                }),
                $fila['rendimiento'] === null ? '' : (float) $fila['rendimiento'],
                match ($fila['condicion']) {
                    'aprobado' => HojaXlsx::celda('Aprobado', HojaXlsx::TEXTO_EXITO),
                    'desaprobado' => HojaXlsx::celda('Desaprobado', HojaXlsx::TEXTO_ALERTA),
                    'en_curso' => 'En curso ('.$fila['rubros_con_nota'].' de '.$rubros->count().' rubros)',
                    default => 'Sin notas',
                },
            ]);
        }

        if ($filas->isEmpty()) {
            $hoja->fila(['', 'No hay estudiantes con matrícula activa.']);

            return;
        }

        $promedio = fn (Collection $notas) => $notas->isEmpty() ? '' : round($notas->avg(), 2);

        $hoja->filaTotal([
            '', 'Promedio del curso', '',
            ...$rubros->map(fn ($rubro) => $promedio($filas->map(fn ($fila) => $fila['notas'][$rubro->id] ?? null)->filter(fn ($nota) => $nota !== null)))->all(),
            $promedio($filas->pluck('nota_final')->filter(fn ($nota) => $nota !== null)),
            $promedio($filas->pluck('rendimiento')->filter(fn ($nota) => $nota !== null)),
            '',
        ]);
    }

    /**
     * @param  array<string, float|null>  $notasPractica
     */
    protected function hojaPractica(HojaXlsx $hoja, Curso $curso, Collection $filas, array $notasPractica, string $subtitulo): void
    {
        $tareas = $curso->tareas()->orderBy('fecha_entrega')->get();
        $quizzes = $curso->quizzes()->orderBy('id')->get();

        $hoja->titulo('Tareas y quizzes de práctica', $subtitulo.' · Participan en la nota final solo si fueron seleccionados en una evaluación automática');

        if ($tareas->isEmpty() && $quizzes->isEmpty()) {
            $hoja->fila(['El curso no tiene tareas ni quizzes.']);

            return;
        }

        $hoja->encabezados([
            'Estudiante',
            ...$tareas->map(fn ($tarea) => "Tarea: {$tarea->titulo}")->all(),
            ...$quizzes->map(fn ($quiz) => "Quiz: {$quiz->tema}")->all(),
        ], fijar: true);

        foreach ($filas as $fila) {
            $id = $fila['estudiante']->id;

            $hoja->fila([
                $fila['estudiante']->name,
                ...$tareas->map(fn ($tarea) => $this->nota($notasPractica["{$id}_t{$tarea->id}"] ?? null))->all(),
                ...$quizzes->map(fn ($quiz) => $this->nota($notasPractica["{$id}_q{$quiz->id}"] ?? null))->all(),
            ]);
        }
    }

    protected function nota(?float $nota, ?float $notaMinima = null): mixed
    {
        if ($nota === null) {
            return '';
        }

        return HojaXlsx::celda($nota, $notaMinima !== null && $nota < $notaMinima ? HojaXlsx::DECIMAL_ALERTA : HojaXlsx::DECIMAL);
    }
}
