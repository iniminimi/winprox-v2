<?php

use App\Actions\Issues\ApproveIssueAction;
use App\Actions\Issues\CreateIssueAction;
use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('schrijft een audit-log bij issue.created', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create(['tenant_id' => $tenant->id]);

    app(CreateIssueAction::class)->handle([
        'location_id' => $location->id,
        'description' => 'Testmelding voor audit.',
        'source' => 'manager',
    ]);

    $log = AuditLog::query()->where('action', 'issue.created')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->tenant_id)->toBe($tenant->id)
        ->and($log->model_type)->toBe(Issue::class);
});

it('schrijft approved_by als user_id bij issue.approved', function () {
    $tenant = Tenant::factory()->create();
    Tenancy::actAs($tenant->id);

    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $issue = Issue::factory()->create(['tenant_id' => $tenant->id, 'approved_at' => null]);

    app(ApproveIssueAction::class)->handle($issue, $admin);

    $log = AuditLog::query()->where('action', 'issue.approved')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id);
});

it('schrijft audit bij organisatie-update', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->admin()->for($tenant)->create();

    app(\App\Actions\Team\UpdateOrganisationAction::class)->handle(
        $tenant,
        ['name' => 'Nieuwe Naam BV'],
        $admin->id,
    );

    $log = AuditLog::query()
        ->where('action', 'tenant.organisation_updated')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id);
});

it('schrijft audit bij unit aanmaken', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(14)]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->for($tenant)->create();
    $location = Location::factory()->for($tenant)->create();

    app(\App\Actions\Locations\CreateUnitAction::class)->handle(
        $location,
        ['name' => 'Lift B'],
        $tenant->id,
        $admin->id,
    );

    $log = AuditLog::query()->where('action', 'unit.created')->latest('id')->first();

    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($admin->id);
});
