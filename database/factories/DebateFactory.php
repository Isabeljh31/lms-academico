<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Debate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Debate>
 */
class DebateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'titulo' => ucfirst(fake()->words(4, true)),
            'consigna' => fake()->paragraph(),
            'estado' => 'publicado',
            'publicado_en' => now(),
        ];
    }

    public function borrador(): static
    {
        return $this->state(['estado' => 'borrador', 'publicado_en' => null]);
    }

    public function cerrado(): static
    {
        return $this->state(['cerrado_en' => now()]);
    }
}
