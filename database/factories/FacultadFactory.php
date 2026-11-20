<?php

namespace Database\Factories;

use App\Models\Facultad;
use App\Models\Sede;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Facultad>
 */
class FacultadFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'nombre' => 'Facultad de '.fake()->unique()->word(),
            'codigo' => strtoupper(fake()->unique()->bothify('F??#')),
            'activa' => true,
        ];
    }
}
