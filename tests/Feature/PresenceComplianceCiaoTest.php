<?php

use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\EndWorkBreakAction;
use App\Actions\Time\StartWorkBreakAction;
use App\Actions\Time\SubmitPresenceBatchAction;
use App\Actions\Time\UpdatePresenceComplianceSettingsAction;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Enums\PresenceType;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Jobs\SubmitPresenceSubmissionJob;

function ciaoTenantReady(): array
{
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
        'enterprise_number' => '0123456789',
        'presence_rsz_client_id' => 'test-client',
        'presence_rsz_private_key' => "-----BEGIN PRIVATE KEY-----\nMIIEowIBAAKCAQEA0Z3VS5JJcds3xfn/ygWyF7PtvEj7pK8P0qK9nF0=\n-----END PRIVATE KEY-----",
    ]);
    Tenancy::actAs($tenant->id);

    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Teststraat',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Brussel',
        'contractual_relationship_reference' => '1Y1003SQ5VSSZ',
    ]);

    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'ssin' => '90010100123',
    ]);

    return [$tenant, $worker, $clockPoint, $location];
}

it('enqueued geen presence als compliance uit staat', function () {
    Queue::fake();
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    Queue::assertNotPushed(SubmitPresenceSubmissionJob::class);
    expect(PresenceSubmission::query()->count())->toBe(0);
});

it('maakt IN submission bij clock-in wanneer CIAO aan staat', function () {
    Queue::fake();
    [$tenant, $worker, $clockPoint] = ciaoTenantReady();

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);

    Queue::assertPushed(SubmitPresenceSubmissionJob::class);
    $submission = PresenceSubmission::query()->first();
    expect($submission)->not->toBeNull()
        ->and($submission->source_event)->toBe(PresenceSourceEvent::ClockIn)
        ->and($submission->presence_type)->toBe(PresenceType::In)
        ->and($submission->work_shift_id)->toBe($shift->id)
        ->and($submission->status)->toBe(PresenceSubmissionStatus::Pending);
});

it('mapped break start/end en clock-out naar OUT/IN/OUT', function () {
    Queue::fake();
    [$tenant, $worker, $clockPoint] = ciaoTenantReady();

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $break = app(StartWorkBreakAction::class)->handle($worker, $shift);
    app(EndWorkBreakAction::class)->handle($worker, $shift->fresh());
    app(ClockOutAction::class)->handle($worker, $clockPoint);

    $types = PresenceSubmission::query()->orderBy('id')->pluck('presence_type')->map->value->all();
    $events = PresenceSubmission::query()->orderBy('id')->pluck('source_event')->map->value->all();

    expect($events)->toBe(['clock_in', 'break_start', 'break_end', 'clock_out'])
        ->and($types)->toBe(['IN', 'OUT', 'IN', 'OUT']);
});

it('skipped submission zonder NISS', function () {
    config(['rsz.static_access_token' => 'test-token']);
    [$tenant, $worker, $clockPoint] = ciaoTenantReady();
    $worker->update(['ssin' => null]);

    Queue::fake();
    $shift = app(ClockInAction::class)->handle($worker->fresh(), $clockPoint);
    $submission = PresenceSubmission::query()->firstOrFail();

    app(SubmitPresenceBatchAction::class)->handle($submission);

    expect($submission->fresh()->status)->toBe(PresenceSubmissionStatus::Skipped)
        ->and($submission->fresh()->error_message)->toBe('ssin_missing_or_invalid');
});

it('stuurt registerInBulk bij geldige data', function () {
    config(['rsz.static_access_token' => 'test-token', 'rsz.use_simulation' => true]);
    Http::fake([
        '*/presenceRegistrations/registerInBulk' => Http::response([
            'items' => [[
                'createdPresenceRegistration' => [
                    'id' => 17611,
                    'validity' => 'pending',
                    'remarks' => [],
                ],
            ]],
        ], 200),
    ]);

    [$tenant, $worker, $clockPoint] = ciaoTenantReady();
    Queue::fake();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    $submission = PresenceSubmission::query()->firstOrFail();

    $fresh = app(SubmitPresenceBatchAction::class)->handle($submission);

    expect($fresh->status)->toBe(PresenceSubmissionStatus::Submitted)
        ->and($fresh->rsz_id)->toBe(17611)
        ->and($fresh->rsz_validity)->toBe('pending');

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), 'registerInBulk')
            && ($data['items'][0]['type'] ?? null) === 'IN'
            && ($data['items'][0]['ssin'] ?? null) === '90010100123'
            && ($data['items'][0]['contractualRelationshipReference'] ?? null) === '1Y1003SQ5VSSZ';
    });
});

it('slaat presence-instellingen op via Action', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $updated = app(UpdatePresenceComplianceSettingsAction::class)->handle($tenant, [
        'presence_compliance_enabled' => true,
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
        'enterprise_number' => '0123456789',
        'presence_rsz_client_id' => 'cid-1',
        'presence_rsz_private_key' => "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----",
    ], (int) $admin->id);

    expect($updated->presenceComplianceEnabled())->toBeTrue()
        ->and($updated->enterprise_number)->toBe('0123456789')
        ->and($updated->presence_rsz_client_id)->toBe('cid-1');
});
