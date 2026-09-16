<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => ucfirst(fake()->words(3, true)),
            'descripcion' => fake()->paragraph(),
            'profesor_id' => User::role(Rol::Profesor->value)->inRandomOrder()->first()?->id
                ?? User::factory()->create()->assignRole(Rol::Profesor->value)->id,
            'imagen_portada_url' => null,
        ];
    }
}
