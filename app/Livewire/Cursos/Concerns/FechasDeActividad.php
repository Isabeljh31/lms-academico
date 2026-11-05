<?php

namespace App\Livewire\Cursos\Concerns;

use Closure;
use Illuminate\Support\Carbon;

/**
 * Fechas de los formularios de tarea y quiz del Profesor:
 *
 * - Al crear: inicio = ahora (sin segundos) y fecha límite = hoy a las 23:59,
 *   o mañana a las 23:59 si ya pasó. Nunca una fecha límite en el pasado.
 * - Al guardar: la fecha límite no puede estar en el pasado (si se escribió
 *   o se cambió; una tarea ya vencida se puede seguir editando sin tocarla).
 * - Lectura en 12 h bajo cada campo («Vence hoy a las 11:59 p. m.») para no
 *   confundir a. m. y p. m. (vista livewire.cursos._lectura-fecha).
 *
 * Los valores van en el formato del selector (Y-m-d\TH:i), en la hora de la
 * institución que aplica ConfiguracionPlataforma::aplicarZonaHoraria().
 */
trait FechasDeActividad
{
    /**
     * @return array{0: string, 1: string} inicio y fecha límite por defecto
     */
    protected function fechasPorDefecto(): array
    {
        $ahora = now()->startOfMinute();
        $limite = $ahora->copy()->setTime(23, 59);

        if ($limite->lte($ahora)) {
            $limite->addDay();
        }

        return [$ahora->format('Y-m-d\TH:i'), $limite->format('Y-m-d\TH:i')];
    }

    /**
     * La fecha límite no puede estar en el pasado, salvo que sea la que ya
     * tenía guardada la actividad ($guardada).
     */
    protected function reglaLimiteNoPasado(?string $guardada): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) use ($guardada) {
            if (blank($valor) || $valor === $guardada || strtotime((string) $valor) === false) {
                return;
            }

            if (Carbon::parse($valor)->lt(now()->startOfMinute())) {
                $fallar('La fecha límite ya pasó. Elige una fecha y hora desde ahora.');
            }
        };
    }

    /**
     * «Vence hoy a las 11:59 p. m.», «Empieza el 03/11/2026 a la 1:05 p. m.».
     */
    public function lecturaFecha(?string $valor, string $prefijo, string $vacio = ''): string
    {
        if (blank($valor) || strtotime($valor) === false) {
            return $vacio;
        }

        $fecha = Carbon::parse($valor);
        $dias = (int) now()->startOfDay()->diffInDays($fecha->copy()->startOfDay(), false);
        $cuando = match ($dias) {
            0 => 'hoy',
            1 => 'mañana',
            -1 => 'ayer',
            default => 'el '.$fecha->format('d/m/Y'),
        };
        $hora = (int) $fecha->format('g');

        return "{$prefijo} {$cuando} ".($hora === 1 ? 'a la' : 'a las')." {$hora}:".$fecha->format('i').' '.($fecha->hour < 12 ? 'a. m.' : 'p. m.');
    }
}
