<?php

use App\Actions\TenantPurge\PurgeUnverifiedTenantRegistrationsAction;
use App\Livewire\Platform\Tenants as PlatformTenants;
use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Platform\SupportTenantContext;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

afterEach(function () {
    Tenancy::forget();
    SupportTenantContext::stop();
});

it('wist zelfregistraties die na zeven dagen nog niet bevestigd zijn', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    $stale = Tenant::factory()->create([
        'name' => 'Valse Aanmelding',
        'created_at' => now()->subDays(8),
        'trial_ends_at' => now()->addDays(22),
    ]);
    User::factory()->admin()->unverified()->create(['tenant_id' => $stale->id]);

    $fresh = Tenant::factory()->create(['created_at' => now()->subDays(2)]);
    User::factory()->admin()->unverified()->create(['tenant_id' => $fresh->id]);

    $real = Tenant::factory()->create(['created_at' => now()->subDays(30)]);
    User::factory()->admin()->create(['tenant_id' => $real->id]);

    $stats = app(PurgeUnverifiedTenantRegistrationsAction::class)->handle();

    expect($stats['deleted'])->toBe(1)
        ->and(Tenant::query()->find($stale->id))->toBeNull()
        ->and(Tenant::query()->find($fresh->id))->not->toBeNull()
        ->and(Tenant::query()->find($real->id))->not->toBeNull()
        ->and(AuditLog::query()
            ->whereNull('tenant_id')
            ->where('action', 'tenant_purge.unused_deleted')
            ->exists())->toBeTrue();

    // Geen afsluitmail naar een adres dat waarschijnlijk niet bestaat.
    Mail::assertNothingSent();
});

it('laat een betalende organisatie ongemoeid, ook zonder bevestigd e-mailadres', function () {
    Mail::fake();
    Storage::fake('local');

    $paying = Tenant::factory()->create([
        'created_at' => now()->subDays(60),
        'billing_plan' => 'facility',
    ]);
    User::factory()->admin()->unverified()->create(['tenant_id' => $paying->id]);

    $stats = app(PurgeUnverifiedTenantRegistrationsAction::class)->handle();

    expect($stats['deleted'])->toBe(0)
        ->and(Tenant::query()->find($paying->id))->not->toBeNull();
});

it('laat de superuser een vals account wissen na het typen van de organisatienaam', function () {
    Mail::fake();
    Storage::fake('local');
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['name' => 'Vals NV']);
    User::factory()->admin()->unverified()->create(['tenant_id' => $tenant->id]);
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)->test(PlatformTenants::class)
        ->call('openDeleteConfirm', $tenant->id)
        ->set('deleteConfirmName', 'Verkeerde Naam')
        ->call('deleteTenant')
        ->assertHasErrors('deleteConfirmName');

    expect(Tenant::query()->find($tenant->id))->not->toBeNull();

    Livewire::actingAs($superuser)->test(PlatformTenants::class)
        ->call('openDeleteConfirm', $tenant->id)
        ->set('deleteConfirmName', 'Vals NV')
        ->call('deleteTenant')
        ->assertHasNoErrors();

    expect(Tenant::query()->find($tenant->id))->toBeNull()
        ->and(AuditLog::query()
            ->where('action', 'tenant_purge.unused_deleted')
            ->where('user_id', $superuser->id)
            ->exists())->toBeTrue();
});

it('toont in het bevestigingsvenster welke gegevens verdwijnen', function () {
    $tenant = Tenant::factory()->create(['name' => 'Te Wissen BV']);
    User::factory()->admin()->unverified()->create(['tenant_id' => $tenant->id]);
    Location::factory()->create(['tenant_id' => $tenant->id]);
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)->test(PlatformTenants::class)
        ->call('openDeleteConfirm', $tenant->id)
        ->assertSee(__('mail.tenant_purge.completed.count.users', ['count' => 1]))
        ->assertSee(__('mail.tenant_purge.completed.count.locations', ['count' => 1]))
        ->assertDontSee(__('mail.tenant_purge.completed.count.issues', ['count' => 0]));
});

it('geeft alleen de superuser recht om een account te wissen', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);
    $superuser = User::factory()->superuser()->create();

    expect($admin->can('deleteUnusedTenant', $tenant))->toBeFalse()
        ->and($superuser->can('deleteUnusedTenant', $tenant))->toBeTrue();
});
