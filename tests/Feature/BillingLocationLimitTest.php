<?php

namespace Tests\Feature;

use App\Actions\Locations\CreateLocationAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingLocationLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_location_throws_when_plan_limit_reached(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $user = User::factory()->for($tenant)->create();

        Location::factory()->count(10)->for($tenant)->create();

        $this->actingAs($user);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('location_limit_exceeded');

        app(CreateLocationAction::class)->handle([
            'name' => 'Locatie 11',
            'country_code' => 'BE',
        ], $tenant->id);
    }

    public function test_create_location_succeeds_below_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $user = User::factory()->for($tenant)->create();

        Location::factory()->count(9)->for($tenant)->create();

        $this->actingAs($user);

        $location = app(CreateLocationAction::class)->handle([
            'name' => 'Locatie 10',
            'country_code' => 'BE',
        ], $tenant->id);

        $this->assertSame('Locatie 10', $location->name);
        $this->assertSame(10, Location::query()->where('tenant_id', $tenant->id)->count());
    }
}
