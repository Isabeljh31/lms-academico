<?php

namespace Database\Factories;

use App\Models\Debate;
use App\Models\DebateComentario;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DebateComentario>
 */
class DebateComentarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'debate_id' => Debate::factory(),
            'user_id' => User::factory(),
            'contenido' => fake()->sentence(),
        ];
    }
}
