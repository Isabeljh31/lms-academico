<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Curso;
use App\Models\Inscripcion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inscripcion>
 */
class InscripcionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'estudiante_id' => User::role(Rol::Estudiante->value)->inRandomOrder()->first()?->id
                ?? User::factory()->create()->assignRole(Rol::Estudiante->value)->id,
            'fecha_inscripcion' => fake()->dateTimeBetween('-3 months', 'now'),
        ];
    }
}
