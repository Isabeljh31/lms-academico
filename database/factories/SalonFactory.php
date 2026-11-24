<?php

namespace Database\Factories;

use App\Models\Salon;
use App\Models\Sede;
use App\Models\TipoSalon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Salón activo de 40 plazas en una sede y un tipo nuevos.
 *
 * @extends Factory<Salon>
 */
class SalonFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sede_id' => Sede::factory(),
            'codigo' => strtoupper(fake()->unique()->bothify('LO-#-##??')),
            'nombre' => 'Salón '.fake()->unique()->numerify('###'),
            'tipo_salon_id' => TipoSalon::factory(),
            'capacidad' => 40,
            'equipamiento' => null,
            'activo' => true,
        ];
    }

    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }
}
