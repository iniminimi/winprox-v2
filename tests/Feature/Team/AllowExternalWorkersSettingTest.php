<?php

use App\Actions\Time\UpdateTenantTimeClockSecurityAction;
use App\Livewire\Pages\Settings;
use App\Livewire\Pages\Team;
use App\Models\AuditLog;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Livewire\Livewire;

function externalWorkersTenant(bool $allow): array
{
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'time_allow_external_workers' => $allow,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);

    return [$tenant, $admin, $team];
}

it('staat externe uitvoerders standaard uit', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);

    expect((bool) $tenant->fresh()->time_allow_external_workers)->toBeFalse()
        ->and($tenant->fresh()->allowsExternalWorkers())->toBeFalse();
});

it('verbergt het externe-vakje in de uitvoerder-popup wanneer uit', function () {
    [, $admin, $team] = externalWorkersTenant(false);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->assertDontSeeHtml('wire:model.live="workerIsExternal"')
        ->assertDontSeeHtml('id="workerCompanyName"');
});

it('toont het externe-vakje in de uitvoerder-popup wanneer aan', function () {
    [, $admin, $team] = externalWorkersTenant(true);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openAddWorker', $team->id)
        ->assertSeeHtml('wire:model.live="workerIsExternal"')
        ->set('workerIsExternal', true)
        ->assertSeeHtml('id="workerCompanyName"');
});

it('behoudt een bestaande externe uitvoerder bij opslaan wanneer uit', function () {
    [, $admin, $team] = externalWorkersTenant(false);
    $worker = Worker::factory()->external('Elektro Peeters')->create([
        'tenant_id' => $team->tenant_id,
        'internal_team_id' => $team->id,
    ]);

    Livewire::actingAs($admin)
        ->test(Team::class)
        ->call('openEditWorker', $worker->id)
        ->call('saveWorker')
        ->assertHasNoErrors();

    $fresh = $worker->fresh();
    expect($fresh->is_external)->toBeTrue()
        ->and($fresh->company_name)->toBe('Elektro Peeters');
});

it('slaat de optie op via prikklok-beveiliging met audit', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $updated = app(UpdateTenantTimeClockSecurityAction::class)->handle(
        $tenant,
        (int) $tenant->id,
        ['time_allow_external_workers' => true],
        (int) $admin->id,
    );

    expect($updated->time_allow_external_workers)->toBeTrue()
        ->and($updated->allowsExternalWorkers())->toBeTrue();

    $log = AuditLog::query()->where('tenant_id', $tenant->id)->latest('id')->first();
    expect($log?->payload['time_allow_external_workers'] ?? null)->toBeTrue();
});

it('zet de optie aan en uit via de instellingenpagina', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSet('timeAllowExternalWorkers', false)
        ->assertSee(__('settings.time_clock.allow_external_workers'))
        ->set('timeAllowExternalWorkers', true)
        ->call('saveTimeClockSecurity')
        ->assertHasNoErrors();

    expect($tenant->fresh()->time_allow_external_workers)->toBeTrue();
});
