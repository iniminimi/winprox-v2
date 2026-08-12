<?php

namespace Tests\Feature;

use App\Actions\Locations\CreateUnitAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingUnitLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_unit_throws_when_plan_limit_reached(): void
    {
        // Trial = 100 units limiet; na 100 units gooit de action een exception.
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $user = User::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();

        Unit::factory()->count(100)->for($location)->for($tenant)->create();

        $this->actingAs($user);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('unit_limit_exceeded');

        app(CreateUnitAction::class)->handle($location, [
            'name' => 'Unit 101',
            'type' => 'other',
        ], $tenant->id);
    }

    public function test_create_unit_succeeds_below_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $user = User::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();

        Unit::factory()->count(99)->for($location)->for($tenant)->create();

        $this->actingAs($user);

        $unit = app(CreateUnitAction::class)->handle($location, [
            'name' => 'Unit 100',
            'type' => 'other',
        ], $tenant->id);

        $this->assertSame('Unit 100', $unit->name);
        $this->assertSame(100, Unit::query()->where('tenant_id', $tenant->id)->count());
    }
}
