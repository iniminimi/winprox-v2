<?php

namespace Tests\Feature;

use App\Actions\Portal\ResolveWorkerIdentityForTenantAction;
use App\Actions\Team\CreateColleagueAction;
use App\Actions\Team\CreateLinkedWorkerForUserAction;
use App\Actions\Time\ClockInAction;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_worker_does_not_double_count_seats(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        $user = User::factory()->employee()->for($tenant)->create();

        app(CreateLinkedWorkerForUserAction::class)->handle($user, $team);

        $this->assertSame(1, $tenant->fresh()->currentSeatsCount());
    }

    public function test_worker_identity_is_scoped_to_clock_point_location(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $locationA = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'A']);
        $locationB = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'B']);
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        $worker = Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
            'first_name' => 'Anna',
            'last_name' => 'Test',
        ]);
        $worker->locations()->sync([$locationA->id]);

        $foundAtA = app(ResolveWorkerIdentityForTenantAction::class)->handle(
            $tenant->id,
            'Anna',
            'Test',
            (int) $locationA->id,
        );
        $notFoundAtB = app(ResolveWorkerIdentityForTenantAction::class)->handle(
            $tenant->id,
            'Anna',
            'Test',
            (int) $locationB->id,
        );

        $this->assertSame('found', $foundAtA['status']->value);
        $this->assertSame('not_found', $notFoundAtB['status']->value);
    }

    public function test_floater_team_can_clock_at_any_location(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14), 'has_time_module' => true]);
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);
        $team = InternalTeam::factory()->create([
            'tenant_id' => $tenant->id,
            'clocks_all_locations' => true,
        ]);
        $worker = Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
        ]);
        $clockPoint = ClockPoint::factory()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
        ]);

        $shift = app(ClockInAction::class)->handle($worker, $clockPoint);

        $this->assertSame($worker->id, $shift->worker_id);
    }

    public function test_clock_in_denied_when_worker_not_assigned_to_location(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14), 'has_time_module' => true]);
        $location = Location::factory()->create(['tenant_id' => $tenant->id]);
        $otherLocation = Location::factory()->create(['tenant_id' => $tenant->id]);
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
        $worker = Worker::factory()->create([
            'tenant_id' => $tenant->id,
            'internal_team_id' => $team->id,
        ]);
        $worker->locations()->sync([$otherLocation->id]);
        $clockPoint = ClockPoint::factory()->create([
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('worker_location_not_allowed');

        app(ClockInAction::class)->handle($worker, $clockPoint);
    }

    public function test_employee_user_gets_location_scope(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $locationA = Location::factory()->create(['tenant_id' => $tenant->id]);
        $locationB = Location::factory()->create(['tenant_id' => $tenant->id]);
        $employee = User::factory()->employee()->for($tenant)->create();
        $employee->locations()->sync([$locationA->id]);

        $this->assertTrue($employee->canAccessLocation((int) $locationA->id));
        $this->assertFalse($employee->canAccessLocation((int) $locationB->id));
    }

    public function test_create_colleague_with_linked_worker_counts_one_seat(): void
    {
        $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
        $admin = User::factory()->admin()->for($tenant)->create();
        $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

        app(CreateColleagueAction::class)->handle([
            'name' => 'Leiding',
            'email' => 'leiding@example.test',
            'locale' => 'nl',
            'role' => User::ROLE_EMPLOYEE,
            'password' => 'wachtwoord123',
            'send_account_email' => false,
            'punch_clock_team_id' => $team->id,
        ], $tenant->id, $admin->id);

        $this->assertSame(2, $tenant->fresh()->currentSeatsCount());
    }
}
