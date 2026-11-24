<?php

namespace Database\Factories;

use App\Models\TipoSalon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TipoSalon>
 */
class TipoSalonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nombre' => 'Tipo '.fake()->unique()->bothify('??-###'),
            'activo' => true,
        ];
    }
}
