<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name'              => fake()->name(),
            'email'             => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password'          => static::$password ??= Hash::make('password'),
            'remember_token'    => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }

    public function creator(): static
    {
        return $this->state(fn () => ['role' => UserRole::Creator]);
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => UserRole::Admin]);
    }

    public function blocked(?User $by = null, ?string $reason = null): static
    {
        return $this->state(fn () => [
            'blocked_at'     => now(),
            'blocked_by'     => $by?->id,
            'blocked_reason' => $reason,
        ]);
    }
}
