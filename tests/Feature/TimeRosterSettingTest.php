<?php

declare(strict_types=1);

use App\Actions\Time\UpdateTenantTimeClockSecurityAction;
use App\Livewire\Locations\Show;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('zet het uurrooster uit en laat prikklok en aanmelden staan', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_team_roster' => true,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    app(UpdateTenantTimeClockSecurityAction::class)->handle($tenant, (int) $tenant->id, [
        'time_require_worker_pin' => false,
        'time_worker_passkeys' => true,
        'time_gps_on_clock' => false,
        'time_gps_visits' => false,
        'time_gps_visit_radius_meters' => null,
        'time_evacuation_list' => false,
        'time_backoffice_clock' => false,
        'time_team_roster' => true,
        'time_roster_enabled' => false,
    ], $admin->id);

    $tenant->refresh();
    expect($tenant->time_roster_enabled)->toBeFalse()
        ->and($tenant->time_team_roster)->toBeTrue()
        ->and($tenant->timeRosterEnabled())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'tenant.time_clock_security_updated')->exists())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('time.schedule.index'))
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('time.shift-types.index'))
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('time.holidays.index'))
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('time.staffing.index'))
        ->assertForbidden();
    $this->actingAs($admin)
        ->get(route('time.absence-requests.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->get(route('time.presence.index'))
        ->assertOk();
    $this->actingAs($admin)
        ->get(route('time.clock-points.index'))
        ->assertOk();
    $this->actingAs($admin)
        ->get(route('time.shifts.index'))
        ->assertOk();

    $this->withToken($admin->createToken('roster-off', ['time:read'])->plainTextToken)
        ->getJson('/api/v1/time/schedule')
        ->assertForbidden();
    $this->withToken($admin->createToken('clock-stays', ['time:read'])->plainTextToken)
        ->getJson('/api/v1/time/work-shifts')
        ->assertOk();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('href="'.route('time.schedule.index').'"', false);

    $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('id="settings-roster"', false)
        ->assertSee(__('settings.time_clock.roster_label'), false)
        ->assertDontSee(__('settings.time_clock.team_roster'), false);
});

it('laat het uurrooster aan wanneer de schakelaar aan staat', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    expect($tenant->timeRosterEnabled())->toBeTrue();

    $this->actingAs($admin)
        ->get(route('time.schedule.index'))
        ->assertOk();

    $settings = $this->actingAs($admin)
        ->get(route('settings.index'))
        ->assertOk()
        ->assertSee('id="settings-roster"', false)
        ->assertSee(__('settings.time_clock.roster_label'), false)
        ->assertSee(__('settings.time_clock.team_roster'), false)
        ->getContent();

    expect($settings)->toMatch(
        '/<button[^>]*class="wp-settings-section-toggle"[^>]*>[\s\S]*?id="settings-roster"[\s\S]*?<\/button>/',
    );
});

it('verbergt roostercode in portaal-opties wanneer het uurrooster uit staat', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_roster_enabled' => false,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Lift 1',
        'roster_code' => 'G1',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->assertDontSee('wire:model="unitRosterCode"', false)
        ->assertDontSee(__('locations.units.fields.roster_code_hint'), false)
        ->set('unitRosterCode', 'ZZ')
        ->set('unitName', 'Lift 2')
        ->call('saveUnit')
        ->assertHasNoErrors();

    expect($unit->fresh()->roster_code)->toBe('G1')
        ->and($unit->fresh()->name)->toBe('Lift 2');

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openBulkModal')
        ->assertSee(__('locations.bulk.advanced_portal.warning_no_roster'), false)
        ->assertDontSee(__('locations.bulk.advanced_portal.warning'), false);
});

it('toont roostercode in portaal-opties wanneer het uurrooster aan staat', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $unit = Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'roster_code' => 'G1',
    ]);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openEditUnit', $unit->id)
        ->assertSee('wire:model="unitRosterCode"', false)
        ->assertSee(__('locations.units.fields.roster_code_hint'), false);

    Livewire::actingAs($admin)
        ->test(Show::class, ['location' => $location])
        ->call('openBulkModal')
        ->assertSee(__('locations.bulk.advanced_portal.warning'), false)
        ->assertDontSee(__('locations.bulk.advanced_portal.warning_no_roster'), false);
});
