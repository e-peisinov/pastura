<?php

namespace Database\Factories;

use App\Enums\Nivel;
use App\Enums\Rol;
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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'rol' => Rol::Recorredor,
            'activo' => true,
        ];
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

    public function admin(): static
    {
        return $this->state(fn () => ['rol' => Rol::Admin]);
    }

    /** Dado de baja (D49): no ingresa y no pasa ningún Gate. */
    public function inactivo(): static
    {
        return $this->state(fn () => ['activo' => false]);
    }

    /** Con una fila propia en la matriz de permisos (D49), por encima del default del rol. */
    public function conNivel(string $modulo, Nivel $nivel): static
    {
        return $this->afterCreating(fn (User $usuario) => $usuario->asignarNivel($modulo, $nivel));
    }
}
