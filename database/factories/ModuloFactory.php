<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Modulo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Modulo>
 */
class ModuloFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'titulo' => 'Unidad '.fake()->numberBetween(1, 12).': '.ucfirst(fake()->words(2, true)),
            'orden' => fake()->numberBetween(1, 10),
            'materiales' => [],
        ];
    }
}
