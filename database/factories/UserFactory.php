<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'tenant_id' => \App\Models\Tenant::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'locale' => 'nl',
            'notify_on_new_issue_email' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_superuser' => false,
            'is_active' => true,
            'role' => \App\Models\User::ROLE_ADMIN,
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
     * Gedeactiveerde gebruiker (kan niet inloggen).
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Medewerker (operationeel; geen accountbeheer/bedrijfsgegevens).
     */
    public function employee(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => \App\Models\User::ROLE_EMPLOYEE,
        ]);
    }

    /**
     * Beheerder (kan alles binnen de tenant).
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => \App\Models\User::ROLE_ADMIN,
        ]);
    }

    /**
     * Platform-superuser zonder tenant.
     */
    public function superuser(): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => null,
            'is_superuser' => true,
        ]);
    }
}
