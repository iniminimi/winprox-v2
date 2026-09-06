<?php

declare(strict_types=1);

use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Time\AcknowledgeTimeRosterViewAction;
use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Actions\Time\ClockInAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Enums\TimePresenceAttentionType;
use App\Livewire\Public\TimePortal;
use App\Livewire\Time\AlarmsIndex;
use App\Livewire\Time\PresenceIndex;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Route;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
        'qr_token' => 'roster-clock-'.$tenant->id,
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

    return [$tenant, $admin, $clockPoint, $worker, $adminWorker];
}

function signInClockPointWorker($clockPoint, string $first, string $last, string $icon)
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', $first)
        ->set('last_name', $last)
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', $icon)
        ->call('signInWithIcon');
}

it('toont geen aparte aanwezigheids-QR op Time aanwezigheid', function () {
    [, $admin] = rosterTenantWithPeople();

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertDontSee('wp-time-roster-qr', false)
        ->assertDontSee(route('time.presence.index').'/qr', false);

    expect(Route::has('time.presence.qr'))->toBeFalse()
        ->and(Route::has('public.time-roster'))->toBeFalse();
});

it('toont de evacuatietegel na aanmelden op Clock Point', function () {
    [, , $clockPoint] = rosterTenantWithPeople();

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertSee(__('time.roster.tile'), false)
        ->assertSee(__('time.roster.tile_sub'), false)
        ->assertDontSee(__('time.roster.ack_title'), false)
        ->assertDontSee(__('portal.worker.no_open_tasks'), false)
        ->assertSet('rosterListOpen', false);
});

it('toont ingeklokte uitvoerders en beheer na tegel en audit-bevestiging', function () {
    [$tenant, , $clockPoint, $worker, $adminWorker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    app(ClockInAction::class)->handle($adminWorker, $clockPoint);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertDontSee('Ann Admin', false)
        ->call('openRoster')
        ->assertSee(__('time.roster.ack_label'), false)
        ->assertDontSee('Ann Admin', false)
        ->set('rosterAcknowledged', true)
        ->call('acknowledgeRoster')
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
    [, , $clockPoint, $worker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->call('openRoster')
        ->call('acknowledgeRoster')
        ->assertHasErrors(['rosterAcknowledged'])
        ->assertSet('rosterListOpen', false);
});

it('weigert de lijst zonder audit via Livewire-state', function () {
    [, , $clockPoint, $worker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    $component = signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart');

    expect(fn () => $component->set('rosterListOpen', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $component->assertSet('rosterListOpen', false)
        ->assertDontSee('Ann Admin', false);
});

it('isoleert de aanwezigheidslijst per tenant', function () {
    [, , $clockPointA, $workerA] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($workerA, $clockPointA);

    $tenantB = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenantB->id);
    $teamB = InternalTeam::factory()->create(['tenant_id' => $tenantB->id]);
    $clockPointB = ClockPoint::factory()->create([
        'tenant_id' => $tenantB->id,
        'qr_token' => 'roster-clock-b-'.$tenantB->id,
    ]);
    $workerB = Worker::factory()->create([
        'tenant_id' => $tenantB->id,
        'internal_team_id' => $teamB->id,
        'first_name' => 'Bram',
        'last_name' => 'Buiten',
        'field_icon_slug' => 'leaf',
    ]);
    app(ClockInAction::class)->handle($workerB, $clockPointB);

    signInClockPointWorker($clockPointB, 'Bram', 'Buiten', 'leaf')
        ->call('openRoster')
        ->set('rosterAcknowledged', true)
        ->call('acknowledgeRoster')
        ->assertSee('Bram Buiten', false)
        ->assertDontSee('Jan Janssen', false);
});

it('verbergt de evacuatietegel zonder time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'qr_token' => 'no-time-clock',
    ]);
    Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->assertDontSeeHtml('wire:click="openRoster"');
});

it('licht aandacht op na raadpleging van de evacuatielijst', function () {
    [$tenant, $admin, $clockPoint, $worker] = rosterTenantWithPeople();
    app(ClockInAction::class)->handle($worker, $clockPoint);

    signInClockPointWorker($clockPoint, 'Jan', 'Janssen', 'heart')
        ->call('openRoster')
        ->set('rosterAcknowledged', true)
        ->call('acknowledgeRoster');

    expect(app(CountTimePresenceAttentionAction::class)->handle($tenant->id))->toBe(1);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($dashboard->kpis->attention)->toBe(1)
        ->and($dashboard->attentionItems->first()->type)->toBe(TimePresenceAttentionType::RosterViewed)
        ->and($dashboard->attentionItems->first()->rosterView?->displayName)->toBe('Jan Janssen');

    Livewire::actingAs($admin)
        ->test(PresenceIndex::class)
        ->assertSee('wp-kpi--alert', false);

    Livewire::actingAs($admin)
        ->test(AlarmsIndex::class)
        ->assertSee('Jan Janssen', false)
        ->assertSee(__('time.alarms.type_roster_viewed'), false)
        ->assertSee(__('time.presence.attention.roster_viewed'), false);

    $stats = app(BuildDashboardStatsAction::class)->handle($tenant->id, true, false);
    expect($stats->timeAttention)->toBe(1)
        ->and(collect($stats->kpiTiles())->pluck('key')->all())->toContain('time_attention');
});

it('telt evacuatielijst-raadplegingen van gisteren niet als aandacht', function () {
    [$tenant, , , $worker] = rosterTenantWithPeople();
    app(AcknowledgeTimeRosterViewAction::class)->handle($worker, $tenant->id);

    AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'time.roster.viewed')
        ->update(['created_at' => now()->subDay()]);

    expect(app(CountTimePresenceAttentionAction::class)->handle($tenant->id))->toBe(0);
});
