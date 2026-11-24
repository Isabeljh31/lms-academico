<?php

namespace Tests\Feature;

use App\Models\Salon;
use App\Models\Sede;
use App\Models\TipoSalon;
use Database\Seeders\SedesYTiposSalonSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Siembra del catálogo de reprogramación: idempotente y con los códigos
 * exactos de las aulas que ya usan las clases.
 */
class SedesYTiposSalonSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_siembra_sedes_tipos_y_los_salones_de_los_olivos_sin_duplicar_al_repetirse(): void
    {
        $this->seed(SedesYTiposSalonSeeder::class);
        $this->seed(SedesYTiposSalonSeeder::class);

        $this->assertSame(6, Sede::count());
        $this->assertSame(9, TipoSalon::count());
        $this->assertSame(9, Salon::count());

        $losOlivos = Sede::where('codigo', 'LO')->sole();
        $salones = Salon::with('tipo')->orderBy('codigo')->get();
        $this->assertTrue($salones->every(fn (Salon $salon) => $salon->sede_id === $losOlivos->id && $salon->capacidad >= 40));
        $this->assertSame('Laboratorio de cómputo', $salones->firstWhere('codigo', 'Lab 3')->tipo->nombre);
        $this->assertSame('Aula teórica', $salones->firstWhere('codigo', 'A-201')->tipo->nombre);
    }
}
