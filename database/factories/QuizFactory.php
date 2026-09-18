<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            'modulo_id' => fn (array $atributos) => Modulo::factory()->create(['curso_id' => $atributos['curso_id']]),
            'tema' => 'Quiz: '.ucfirst(fake()->words(2, true)),
            'preguntas' => [
                [
                    'enunciado' => fake()->sentence().'?',
                    'opciones' => [fake()->word(), fake()->word(), fake()->word()],
                    'respuesta_correcta' => 0,
                ],
                [
                    'enunciado' => fake()->sentence().'?',
                    'opciones' => [fake()->word(), fake()->word()],
                    'respuesta_correcta' => 1,
                ],
            ],
        ];
    }
}
