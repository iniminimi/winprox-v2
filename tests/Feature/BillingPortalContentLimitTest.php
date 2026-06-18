<?php

namespace Tests\Feature;

use App\Actions\Locations\CreateLocationAnnouncementAction;
use App\Actions\Locations\ToggleLocationAnnouncementActiveAction;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class BillingPortalContentLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_tenant_cannot_exceed_document_org_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        Document::factory()->count(10)->for($tenant)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('document_org_limit_exceeded');

        $tenant->fresh()->assertCanAddDocument(null);
    }

    public function test_paid_plan_enforces_documents_per_unit_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'micro',
            'billing_active_until' => now()->addMonth(),
        ]);
        $location = Location::factory()->for($tenant)->create();
        $unit = Unit::factory()->for($location)->for($tenant)->create();

        Document::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'unit_id' => $unit->id,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('document_unit_limit_exceeded');

        $tenant->fresh()->assertCanAddDocument($unit->id);
    }

    public function test_inactive_documents_still_count_toward_org_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        Document::factory()->count(10)->for($tenant)->create(['is_active' => false]);

        $this->assertTrue($tenant->fresh()->isAtDocumentsOrgLimit());
    }

    public function test_active_announcements_per_unit_limit_is_enforced(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'micro',
            'billing_active_until' => now()->addMonth(),
        ]);
        $user = User::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();
        $unit = Unit::factory()->for($location)->for($tenant)->create();

        $this->actingAs($user);

        $create = app(CreateLocationAnnouncementAction::class);

        foreach (['Eerste', 'Tweede'] as $description) {
            $create->handle($location, [
                'description' => $description,
                'unit_id' => $unit->id,
                'is_active' => true,
                'expires_at' => null,
            ], $tenant->id, $user->id);
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('announcement_unit_limit_exceeded');

        $create->handle($location, [
            'description' => 'Derde',
            'unit_id' => $unit->id,
            'is_active' => true,
            'expires_at' => null,
        ], $tenant->id, $user->id);
    }

    public function test_inactive_announcements_do_not_count_toward_unit_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'micro',
            'billing_active_until' => now()->addMonth(),
        ]);
        $user = User::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();
        $unit = Unit::factory()->for($location)->for($tenant)->create();

        Announcement::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => false,
        ]);

        $announcement = app(CreateLocationAnnouncementAction::class)->handle($location, [
            'description' => 'Nog actief',
            'unit_id' => $unit->id,
            'is_active' => true,
            'expires_at' => null,
        ], $tenant->id, $user->id);

        $this->assertInstanceOf(Announcement::class, $announcement);
    }

    public function test_toggle_announcement_active_respects_unit_limit(): void
    {
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'micro',
            'billing_active_until' => now()->addMonth(),
        ]);
        $user = User::factory()->for($tenant)->create();
        $location = Location::factory()->for($tenant)->create();
        $unit = Unit::factory()->for($location)->for($tenant)->create();

        Announcement::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $inactive = Announcement::factory()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'unit_id' => $unit->id,
            'is_active' => false,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('announcement_unit_limit_exceeded');

        app(ToggleLocationAnnouncementActiveAction::class)->handle($inactive, $user->id);
    }
}
