<?php

namespace Database\Factories;

use App\Enums\Rol;
use App\Models\Quiz;
use App\Models\ResultadoQuiz;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResultadoQuiz>
 */
class ResultadoQuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'estudiante_id' => User::role(Rol::Estudiante->value)->inRandomOrder()->first()?->id
                ?? User::factory()->create()->assignRole(Rol::Estudiante->value)->id,
            'respuestas' => [0, 1],
            'puntaje' => fake()->randomFloat(2, 0, 20),
        ];
    }
}
