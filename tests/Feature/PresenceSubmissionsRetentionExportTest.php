<?php

declare(strict_types=1);

use App\Actions\Retention\PrunePresenceSubmissionsAction;
use App\Actions\Time\ExportPresenceSubmissionsAction;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Enums\PresenceType;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Artisan;

afterEach(fn () => Tenancy::forget());

function ciaoExportTenant(): array
{
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
    ]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
    ]);
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Site A',
    ]);

    return [$tenant, $admin, $worker, $location];
}

it('exporteert CIAO-inzendingen als CSV zonder NISS', function () {
    [$tenant, $admin, $worker, $location] = ciaoExportTenant();

    PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'location_id' => $location->id,
        'source_event' => PresenceSourceEvent::VisitStart,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 42,
        'rsz_validity' => 'pending',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('time.ciao.export'))
        ->assertOk()
        ->assertHeader('content-disposition');

    $csv = $response->streamedContent();

    expect($csv)->toContain('Jan Jansen')
        ->and($csv)->toContain('42')
        ->and($csv)->not->toContain('90010100123');
});

it('toont print-rapport voor CIAO-inzendingen', function () {
    [$tenant, $admin, $worker, $location] = ciaoExportTenant();

    PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'location_id' => $location->id,
        'source_event' => PresenceSourceEvent::VisitEnd,
        'presence_type' => PresenceType::Out,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 99,
        'rsz_validity' => 'pending',
    ]);

    $this->actingAs($admin)
        ->get(route('time.ciao.print'))
        ->assertOk()
        ->assertSee('Jan Jansen', false)
        ->assertSee('RSZ #99', false);
});

it('verwijdert oude submitted CIAO-inzendingen maar laat failed staan', function () {
    config(['data_retention.presence_submissions_months' => 12]);

    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id]);

    $old = PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now()->subMonths(13),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 1,
    ]);
    $recent = PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now()->subMonths(2),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 2,
    ]);
    $failed = PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now()->subMonths(18),
        'status' => PresenceSubmissionStatus::Failed,
        'error_message' => 'rsz_not_created',
    ]);

    $stats = app(PrunePresenceSubmissionsAction::class)->handle(dryRun: false);

    expect($stats['removed'])->toBe(1)
        ->and(PresenceSubmission::query()->find($old->id))->toBeNull()
        ->and(PresenceSubmission::query()->find($recent->id))->not->toBeNull()
        ->and(PresenceSubmission::query()->find($failed->id))->not->toBeNull();
});

it('export-action respecteert statusfilter', function () {
    [$tenant, , $worker] = ciaoExportTenant();

    PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 1,
    ]);
    PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockOut,
        'presence_type' => PresenceType::Out,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Failed,
        'error_message' => 'rsz_not_created',
    ]);

    $result = app(ExportPresenceSubmissionsAction::class)->handle(
        (int) $tenant->id,
        PresenceSubmissionStatus::Failed,
    );

    expect($result->rows)->toHaveCount(1)
        ->and($result->rows->first()->status)->toBe(PresenceSubmissionStatus::Failed);
});

it('retention-prune vermeldt CIAO-inzendingen', function () {
    Artisan::call('winprox:retention-prune', ['--dry-run' => true]);

    expect(Artisan::output())->toContain('CIAO submissions');
});
