<?php

namespace App\Livewire\Profesor;

use Livewire\Attributes\Locked;

/**
 * Pantalla «Evaluación» del curso (Profesor): cómo se calcula la nota final,
 * con una tarjeta por evaluación (actividades que cuentan, notas registradas
 * y qué falta). Es la misma lógica de Actividades (panel de cálculo, avance),
 * solo cambia lo que se muestra.
 */
class Evaluacion extends Actividades
{
    #[Locked]
    public string $seccion = 'evaluacion';
}
