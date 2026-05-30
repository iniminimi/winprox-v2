<?php

namespace Tests\Feature;

use App\Livewire\Public\LocationPortal;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationPortalTest extends TestCase
{
    use RefreshDatabase;
    public function test_location_portal_creates_issue_without_unit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $location = Location::factory()->for($tenant)->create(['is_active' => true]);

        Livewire::test(LocationPortal::class, ['token' => $location->location_qr_token])
            ->set('description', 'Lek in gang')
            ->call('submitReport')
            ->assertHasNoErrors();

        $issue = Issue::query()->where('location_id', $location->id)->first();
        $this->assertNotNull($issue);
        $this->assertNull($issue->unit_id);
        $this->assertSame('qr_location', $issue->source->value);
    }

    public function test_location_portal_lists_active_units(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $location = Location::factory()->for($tenant)->create(['is_active' => true]);
        $unit = Unit::factory()->for($location)->for($tenant)->create(['name' => 'Lift A', 'is_active' => true]);

        Livewire::test(LocationPortal::class, ['token' => $location->location_qr_token])
            ->assertSee('Lift A')
            ->assertSeeHtml(route('public.unit-portal', $unit->qr_token));
    }
}
