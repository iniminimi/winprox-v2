<?php

use App\Actions\Retention\PruneClosedIssueMediaAction;
use App\Actions\Retention\PruneInactiveTenantFacilityDataAction;
use App\Enums\TaskStatus;
use App\Models\Issue;
use App\Models\IssuePhoto;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');
});

it('verwijdert foto\'s van oude gesloten meldingen maar laat de melding staan', function () {
    config(['data_retention.closed_issue_media_days' => 30]);

    $issue = Issue::factory()->create([
        'status' => TaskStatus::Closed,
        'updated_at' => now()->subDays(60),
    ]);

    $path = 'issue-photos/test.jpg';
    Storage::disk('public')->put($path, 'fake');

    IssuePhoto::factory()->create([
        'tenant_id' => $issue->tenant_id,
        'issue_id' => $issue->id,
        'path' => $path,
    ]);

    $stats = app(PruneClosedIssueMediaAction::class)->handle(dryRun: false);

    expect($stats['photos_removed'])->toBe(1)
        ->and(IssuePhoto::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(Issue::query()->withoutGlobalScopes()->find($issue->id))->not->toBeNull();
});

it('dry-run verwijdert geen foto\'s', function () {
    config(['data_retention.closed_issue_media_days' => 30]);

    $issue = Issue::factory()->create([
        'status' => TaskStatus::Closed,
        'updated_at' => now()->subDays(60),
    ]);

    $path = 'issue-photos/keep.jpg';
    Storage::disk('public')->put($path, 'fake');

    IssuePhoto::factory()->create([
        'tenant_id' => $issue->tenant_id,
        'issue_id' => $issue->id,
        'path' => $path,
    ]);

    app(PruneClosedIssueMediaAction::class)->handle(dryRun: true);

    expect(IssuePhoto::query()->withoutGlobalScopes()->count())->toBe(1)
        ->and(Storage::disk('public')->exists($path))->toBeTrue();
});

it('verwijdert meldingen van lang inactieve tenants', function () {
    config(['data_retention.inactive_tenant_days' => 90]);

    $tenant = Tenant::factory()->create([
        'is_active' => false,
        'billing_active_until' => now()->subDays(120),
    ]);

    $issue = Issue::factory()->create(['tenant_id' => $tenant->id]);
    $path = 'issue-photos/tenant.jpg';
    Storage::disk('public')->put($path, 'fake');
    IssuePhoto::factory()->create([
        'tenant_id' => $tenant->id,
        'issue_id' => $issue->id,
        'path' => $path,
    ]);

    $stats = app(PruneInactiveTenantFacilityDataAction::class)->handle(dryRun: false);

    expect($stats['tenants_scanned'])->toBe(1)
        ->and($stats['issues_removed'])->toBe(1)
        ->and(Issue::query()->withoutGlobalScopes()->find($issue->id))->toBeNull();
});

it('retention-prune command ondersteunt dry-run', function () {
    Artisan::call('winprox:retention-prune', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('Dry-run');
});
