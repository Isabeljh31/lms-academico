<?php

namespace Database\Factories;

use App\Models\Curso;
use App\Models\Modulo;
use App\Models\Tarea;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tarea>
 */
class TareaFactory extends Factory
{
    public function definition(): array
    {
        $curso = Curso::factory();

        return [
            'curso_id' => $curso,
            'modulo_id' => fn (array $atributos) => Modulo::factory()->create(['curso_id' => $atributos['curso_id']]),
            'titulo' => 'Práctica: '.ucfirst(fake()->words(3, true)),
            'descripcion' => fake()->paragraph(),
            'fecha_entrega' => fake()->dateTimeBetween('-2 weeks', '+3 weeks'),
            'peso_nota' => fake()->randomElement([1, 2, 5, 10]),
        ];
    }
}
