<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Entrega;
use App\Models\Tarea;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entrega>
 */
class EntregaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tarea_id' => Tarea::factory(),
            'estudiante_id' => User::role(Rol::Estudiante->value)->inRandomOrder()->first()?->id
                ?? User::factory()->create()->assignRole(Rol::Estudiante->value)->id,
            'archivo_url' => 'https://ui-avatars.com/api/?name=Entrega',
            'comentario' => fake()->optional()->sentence(),
            'estado' => 'entregado',
            'nota' => null,
            'feedback' => null,
        ];
    }

    public function calificada(): static
    {
        return $this->state(fn () => [
            'estado' => 'calificado',
            'nota' => fake()->randomFloat(2, 8, 20),
            'feedback' => fake()->sentence(),
        ]);
    }
}
