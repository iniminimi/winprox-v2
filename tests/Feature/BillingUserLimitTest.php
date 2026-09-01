<?php

namespace Tests\Feature;

use App\Actions\Billing\ActivateSubscriptionPlanAction;
use App\Actions\Team\CreateColleagueAction;
use App\Actions\Team\CreateWorkerAction;
use App\Actions\Team\SetColleagueActiveAction;
use App\Actions\Team\SetWorkerActiveAction;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingUserLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_trial_tenant_has_fifty_seat_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);

        $this->assertSame(50, $tenant->maxSeatsLimit());
    }

    public function test_winprox_ten_plan_has_ten_seat_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_10', 'manual');

        $this->assertSame(10, $tenant->fresh()->maxSeatsLimit());
    }

    public function test_legacy_facility_plan_has_unlimited_seats(): void
    {
        $tenant = Tenant::factory()->create([
            'billing_plan' => 'facility_250',
            'billing_active_until' => now()->addMonth(),
        ]);

        $this->assertNull($tenant->maxSeatsLimit());
    }

    public function test_current_seats_count_includes_admins_employees_and_workers(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(2)->for($tenant)->create();
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        Worker::factory()->count(3)->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

        $this->assertSame(6, $tenant->fresh()->currentSeatsCount());
    }

    public function test_inactive_users_and_workers_do_not_count_toward_seats(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->inactive()->for($tenant)->create();
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
            'is_active' => false,
        ]);

        $this->assertSame(1, $tenant->fresh()->currentSeatsCount());
    }

    public function test_create_colleague_blocked_when_seat_limit_reached(): void
    {
        Notification::fake();

        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(9)->for($tenant)->create();

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_10', 'manual');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seat_limit_exceeded');

        app(CreateColleagueAction::class)->handle([
            'name' => 'Tiende',
            'email' => 'tiende@example.test',
            'locale' => 'nl',
            'role' => User::ROLE_ADMIN,
            'password' => 'wachtwoord123',
            'send_account_email' => false,
        ], $tenant->id);
    }

    public function test_create_worker_blocked_when_seat_limit_reached(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        Worker::factory()->count(9)->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
        ]);

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_10', 'manual');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seat_limit_exceeded');

        app(CreateWorkerAction::class)->handle($team, [
            'first_name' => 'Tiende',
            'last_name' => 'Worker',
        ]);
    }

    public function test_reactivating_colleague_respects_seat_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();
        User::factory()->employee()->count(9)->for($tenant)->create();
        $inactive = User::factory()->employee()->inactive()->for($tenant)->create();

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_10', 'manual');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seat_limit_exceeded');

        app(SetColleagueActiveAction::class)->handle($inactive, true);
    }

    public function test_reactivating_worker_respects_seat_limit(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        Worker::factory()->count(9)->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
        ]);
        $inactive = Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
            'is_active' => false,
        ]);

        app(ActivateSubscriptionPlanAction::class)->handle($admin, $tenant, 'winprox_10', 'manual');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('seat_limit_exceeded');

        app(SetWorkerActiveAction::class)->handle($inactive, true);
    }
}
