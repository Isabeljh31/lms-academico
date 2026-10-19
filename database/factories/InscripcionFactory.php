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
            'origen' => 'manual',
            'estado' => 'activo',
            'activo_local' => true,
        ];
    }

    /**
     * Matrícula sincronizada desde un sistema externo (ERS RF-28).
     */
    public function deApi(): static
    {
        return $this->state(fn (array $attributes) => ['origen' => 'api']);
    }

    /**
     * Baja lógica: se conserva el historial (ERS RF-30).
     */
    public function deBaja(): static
    {
        return $this->state(fn (array $attributes) => ['estado' => 'inactivo', 'activo_local' => false, 'fecha_baja' => now()]);
    }
}
