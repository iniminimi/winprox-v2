<?php

namespace Tests\Feature;

use App\Actions\Locations\CreateLocationAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingLocationLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_location_succeeds_without_limit(): void
    {
        // Locaties zijn onbeperkt in alle tiers.
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $user = User::factory()->for($tenant)->create();

        Location::factory()->count(50)->for($tenant)->create();

        $this->actingAs($user);

        $location = app(CreateLocationAction::class)->handle([
            'name' => 'Locatie 51',
            'country_code' => 'BE',
        ], $tenant->id);

        $this->assertSame('Locatie 51', $location->name);
        $this->assertNull($tenant->fresh()->maxLocationsLimit());
    }
}
