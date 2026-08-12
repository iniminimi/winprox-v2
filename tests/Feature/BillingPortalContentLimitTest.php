<?php

namespace Tests\Feature;

use App\Actions\Locations\CreateLocationAnnouncementAction;
use App\Actions\Locations\ToggleLocationAnnouncementActiveAction;
use App\Models\Announcement;
use App\Models\Document;
use App\Models\IssuePhoto;
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
        // Trial = 100 units → 100 documenten limiet
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        Document::factory()->count(100)->for($tenant)->create();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('document_org_limit_exceeded');

        $tenant->fresh()->assertCanAddDocument(null);
    }

    public function test_paid_plan_has_no_photos_org_limit(): void
    {
        // Foto's zijn onbeperkt in alle tiers — client-side verkleind, geen server-limiet.
        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'facility_10',
            'billing_active_until' => now()->addMonth(),
        ]);

        IssuePhoto::factory()->count(100)->create(['tenant_id' => $tenant->id]);

        // assertCanAddPhotos gooit géén exception: onbeperkt
        $tenant->fresh()->assertCanAddPhotos(1);
        $this->assertNull($tenant->fresh()->maxPhotosOrgLimit());
    }

    public function test_inactive_documents_still_count_toward_org_limit(): void
    {
        // Trial = 100 documenten limiet; 100 inactieve docs = limiet bereikt
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        Document::factory()->count(100)->for($tenant)->create(['is_active' => false]);

        $this->assertTrue($tenant->fresh()->isAtDocumentsOrgLimit());
    }

    public function test_active_announcements_per_unit_limit_is_enforced(): void
    {
        config(['billing.plans.facility_10.announcements_per_unit' => 2]);

        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'facility_10',
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
            'billing_plan' => 'facility_10',
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
        config(['billing.plans.facility_10.announcements_per_unit' => 2]);

        $tenant = Tenant::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'billing_plan' => 'facility_10',
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
