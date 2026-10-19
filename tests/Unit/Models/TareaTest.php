<?php

namespace Tests\Unit\Models;

use App\Models\Entrega;
use App\Models\Tarea;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * ERS RF-43: estado calculado de una tarea para cada estudiante.
 */
class TareaTest extends TestCase
{
    protected function tareaQueVence(string $fecha): Tarea
    {
        return new Tarea(['fecha_entrega' => Carbon::parse($fecha)]);
    }

    protected function entrega(array $atributos, string $entregadaEl): Entrega
    {
        $entrega = new Entrega($atributos);
        $entrega->updated_at = Carbon::parse($entregadaEl);

        return $entrega;
    }

    public function test_sin_entrega_y_con_plazo_abierto_esta_pendiente(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');

        $this->assertSame('pendiente', $tarea->estadoPara(null, Carbon::parse('2026-10-01')));
    }

    public function test_sin_entrega_y_con_plazo_vencido_esta_no_entregado(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');

        $this->assertSame('no_entregado', $tarea->estadoPara(null, Carbon::parse('2026-10-11')));
    }

    public function test_entregada_dentro_del_plazo_esta_entregado(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');

        $this->assertSame('entregado', $tarea->estadoPara($this->entrega(['estado' => 'entregado'], '2026-10-09'), Carbon::parse('2026-10-12')));
    }

    public function test_entregada_despues_del_plazo_esta_fuera_de_plazo(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');

        $this->assertSame('fuera_de_plazo', $tarea->estadoPara($this->entrega(['estado' => 'entregado'], '2026-10-11 08:00'), Carbon::parse('2026-10-12')));
    }

    public function test_con_nota_esta_calificado(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');
        $entrega = $this->entrega(['estado' => 'calificado', 'nota' => 15], '2026-10-11 08:00');

        $this->assertSame('calificado', $tarea->estadoPara($entrega, Carbon::parse('2026-10-12')));
    }

    public function test_devuelta_por_el_profesor_esta_devuelto(): void
    {
        $tarea = $this->tareaQueVence('2026-10-10 23:59');

        $this->assertSame('devuelto', $tarea->estadoPara($this->entrega(['estado' => 'devuelto'], '2026-10-09'), Carbon::parse('2026-10-12')));
    }

    public function test_normaliza_los_formatos_permitidos(): void
    {
        $tarea = new Tarea(['formatos_permitidos' => [' PDF', '.docx', '']]);

        $this->assertSame(['pdf', 'docx'], $tarea->formatosPermitidos());
    }
}
