<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->firstName().' '.fake()->lastName(),
            // Se derivan de "name" para que un test pueda indicar solo el nombre completo.
            'nombres' => fn (array $atributos) => preg_split('/\s+/u', trim($atributos['name']), 2)[0],
            'apellidos' => fn (array $atributos) => preg_split('/\s+/u', trim($atributos['name']), 2)[1] ?? null,
            'dni' => fake()->unique()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'activo' => true,
            'primer_login' => false,
        ];
    }

    /**
     * Cuenta recién creada que aún debe cambiar su contraseña (ERS RF-05).
     */
    public function conPrimerLogin(): static
    {
        return $this->state(fn (array $attributes) => ['primer_login' => true]);
    }

    /**
     * Cuenta desactivada por el Supervisor (ERS RF-15).
     */
    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => ['activo' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
