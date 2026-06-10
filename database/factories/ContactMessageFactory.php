<?php

namespace Database\Factories;

use App\Models\ContactMessage;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<ContactMessage> */
class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'message_id' => Str::uuid().'@winprox.app',
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'subject' => fake()->sentence(4),
            'message' => fake()->paragraph(),
            'direction' => 'inbound',
            'read_at' => null,
            'tenant_id' => null,
        ];
    }

    public function inbound(): static
    {
        return $this->state(fn () => ['direction' => 'inbound']);
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'outbound',
            'read_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state(fn () => ['read_at' => null]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function forTenant(Tenant|int|null $tenant = null): static
    {
        return $this->state(fn () => [
            'tenant_id' => $tenant instanceof Tenant ? $tenant->id : $tenant,
        ]);
    }
}
