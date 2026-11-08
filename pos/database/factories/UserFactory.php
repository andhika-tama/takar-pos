<?php

namespace Database\Factories;

use App\Enums\Role;
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
            'username' => 'user'.fake()->unique()->randomNumber(6, true),
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Kasir,
            'active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function owner(): static
    {
        return $this->state(['role' => Role::Owner]);
    }

    public function kasir(): static
    {
        return $this->state(['role' => Role::Kasir]);
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
