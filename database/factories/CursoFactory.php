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
            'estado' => 'publicado',
        ];
    }

    /**
     * Curso aún no visible para estudiantes.
     */
    public function borrador(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'borrador']);
    }

    /**
     * Curso finalizado: no admite cambios retroactivos (ERS RNF-14).
     */
    public function archivado(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'archivado']);
    }
}
