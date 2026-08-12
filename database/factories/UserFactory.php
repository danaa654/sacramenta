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
            'remember_token' => Str::random(10),
            'role' => User::ROLE_STAFF,
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

    /**
     * A Super Admin — full system access, including Manage Users. Can
     * also delete reservations, correct locked/completed records, and
     * override schedule conflicts. See App\Policies\ReservationPolicy
     * and App\Policies\UserPolicy. Kept as ->admin() (not renamed) so
     * existing tests/call sites don't need updating.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_SUPER_ADMIN,
        ]);
    }

    /**
     * An Administrator — full day-to-day reservation/scheduling access,
     * but cannot Manage Users, change roles, or touch system settings.
     */
    public function administrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => User::ROLE_ADMINISTRATOR,
        ]);
    }
}