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
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'jenis_kelamin' => fake()->randomElement(['Laki-laki', 'Perempuan']),
            'role' => 'personil',
            'jabatan' => fake()->randomElement([
                'analis',
                'preparator',
                'mekanik',
                'admin',
                'manajemen',
            ]),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Set user as admin role.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'admin',
            'jabatan' => 'admin',
        ]);
    }

    /**
     * Set user as manajemen role.
     */
    public function manajemen(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'manajemen',
            'jabatan' => 'manajemen',
        ]);
    }
}
