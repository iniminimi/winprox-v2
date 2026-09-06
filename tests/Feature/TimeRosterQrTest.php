<?php

declare(strict_types=1);

use App\Actions\Time\ClockInAction;
use App\Actions\Time\EnsureTimeRosterQrTokenAction;
use App\Livewire\Public\TimeRosterPortal;
use App\Livewire\Time\PresenceIndex;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function rosterTenantWithPeople(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Crèche']);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Ingang',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $adminWorker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'user_id' => $admin->id,
        'first_name' => 'Ann',
        'last_name' => 'Admin',
        'field_icon_slug' => 'star',
    ]);

    $tenant = app(EnsureTimeRosterQrTokenAction::class)->handle($tenant, $admin->id);

    return [$tenant, $admin, $clockPoint, $worker, $adminWorker];
}

it('toont de aanwezigheids-QR op Time aanwezigheid', function () {
    [$tenant, $admin] = rosterTenantWithPeople();

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertSee(__('time.roster.qr_title'), false)
        ->assertSee(__('time.roster.qr_hint'), false);

    $this->actingAs($admin)
        ->get(route('time.presence.qr'))
        ->assertOk()
        ->assertSee(__('time.roster.qr_title'), false);
});

it('weigert een onbekende aanwezigheids-QR', function () {
    $this->get(route('public.time-roster', 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'))
        ->assertNotFound();
});

it('toont geen namen voor een anonieme scan', function () {
    [$tenant, , $clockPoint, $worker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    Livewire::test(TimeRosterPortal::class, ['token' => $tenant->time_roster_qr_token])
        ->assertSee(__('time.roster.identify_hint'), false)
        ->assertDontSee('Jan Janssen', false)
        ->assertDontSee('Ann Admin', false);
});

it('toont ingeklokte uitvoerders en beheer na icoon en audit-bevestiging', function () {
    [$tenant, , $clockPoint, $worker, $adminWorker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(ClockInAction::class)->handle($adminWorker, $clockPoint);

    Livewire::test(TimeRosterPortal::class, ['token' => $tenant->time_roster_qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSee(__('time.roster.ack_label'), false)
        ->assertDontSee('Ann Admin', false)
        ->set('acknowledged', true)
        ->call('acknowledgeView')
        ->assertSee('Jan Janssen', false)
        ->assertSee('Ann Admin', false)
        ->assertSee(__('time.roster.role.admin'), false)
        ->assertSee(__('time.roster.role.worker'), false);

    expect(AuditLog::query()
        ->where('action', 'time.roster.viewed')
        ->where('tenant_id', $tenant->id)
        ->where('model_id', $tenant->id)
        ->exists())->toBeTrue();

    $payload = AuditLog::query()
        ->where('action', 'time.roster.viewed')
        ->where('tenant_id', $tenant->id)
        ->value('payload');

    expect($payload['first_name'] ?? null)->toBe('Jan')
        ->and($payload['last_name'] ?? null)->toBe('Janssen')
        ->and($payload['worker_id'] ?? null)->toBe($worker->id)
        ->and($payload['viewed_at'] ?? null)->not->toBeEmpty();
});

it('eist de audit-checkbox voor de lijst', function () {
    [$tenant, , $clockPoint, $worker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    Livewire::test(TimeRosterPortal::class, ['token' => $tenant->time_roster_qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->call('acknowledgeView')
        ->assertHasErrors(['acknowledged'])
        ->assertDontSee('Jan Janssen', false);
});

it('isoleert de aanwezigheidslijst per tenant', function () {
    [$tenantA, , $clockPointA, $workerA] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($workerA, $clockPointA);

    $tenantB = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenantB->id);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenantB->id]);
    $workerB = Worker::factory()->create([
        'tenant_id' => $tenantB->id,
        'internal_team_id' => $teamB->id,
        'first_name' => 'Bram',
        'last_name' => 'Buiten',
        'field_icon_slug' => 'leaf',
    ]);
    $tenantB = app(EnsureTimeRosterQrTokenAction::class)->handle($tenantB);

    Livewire::test(TimeRosterPortal::class, ['token' => $tenantB->time_roster_qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->assertHasErrors(['identify'])
        ->set('first_name', 'Bram')
        ->set('last_name', 'Buiten')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'leaf')
        ->call('signInWithIcon')
        ->set('acknowledged', true)
        ->call('acknowledgeView')
        ->assertDontSee('Jan Janssen', false);
});

it('verbergt de roster-QR zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.presence.qr'))
        ->assertForbidden();
});
