<?php

use App\Actions\Platform\TogglePresenceComplianceAction;
use App\Actions\Platform\ToggleTimeModuleAction;
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
use App\Livewire\Pages\Settings;
use App\Livewire\Platform\Tenants as PlatformTenants;
use App\Models\AuditLog;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use App\Jobs\SubmitPresenceSubmissionJob;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

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
    Event::fake([\App\Events\Time\PresenceSubmissionSkipped::class]);
    [$tenant, $worker, $clockPoint] = ciaoTenantReady();
    $worker->update(['ssin' => null]);

    Queue::fake();
    $shift = app(ClockInAction::class)->handle($worker->fresh(), $clockPoint);
    $submission = PresenceSubmission::query()->firstOrFail();

    app(SubmitPresenceBatchAction::class)->handle($submission);

    expect($submission->fresh()->status)->toBe(PresenceSubmissionStatus::Skipped)
        ->and($submission->fresh()->error_message)->toBe('ssin_missing_or_invalid');

    Event::assertDispatched(\App\Events\Time\PresenceSubmissionSkipped::class);
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
    Event::fake([\App\Events\Time\PresenceSubmissionSubmitted::class]);

    [$tenant, $worker, $clockPoint] = ciaoTenantReady();
    Queue::fake();
    app(ClockInAction::class)->handle($worker, $clockPoint);
    $submission = PresenceSubmission::query()->firstOrFail();

    $fresh = app(SubmitPresenceBatchAction::class)->handle($submission);

    expect($fresh->status)->toBe(PresenceSubmissionStatus::Submitted)
        ->and($fresh->rsz_id)->toBe(17611)
        ->and($fresh->rsz_validity)->toBe('pending');

    Event::assertDispatched(\App\Events\Time\PresenceSubmissionSubmitted::class);

    Http::assertSent(function ($request) {
        $data = $request->data();

        return str_contains($request->url(), 'registerInBulk')
            && ($data['items'][0]['type'] ?? null) === 'IN'
            && ($data['items'][0]['ssin'] ?? null) === '90010100123'
            && ($data['items'][0]['contractualRelationshipReference'] ?? null) === '1Y1003SQ5VSSZ';
    });
});

it('slaat presence-instellingen op via Action', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
    ]);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $updated = app(UpdatePresenceComplianceSettingsAction::class)->handle($tenant, [
        'presence_compliance_scope' => PresenceComplianceScope::CiaoCleaning->value,
        'enterprise_number' => '0123456789',
        'presence_rsz_client_id' => 'cid-1',
        'presence_rsz_private_key' => "-----BEGIN PRIVATE KEY-----\nabc\n-----END PRIVATE KEY-----",
    ], (int) $admin->id);

    expect($updated->presenceComplianceEnabled())->toBeTrue()
        ->and($updated->enterprise_number)->toBe('0123456789')
        ->and($updated->presence_rsz_client_id)->toBe('cid-1');
});

it('weigert tenant-update zolang CIAO vergrendeld is', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);

    expect(fn () => app(UpdatePresenceComplianceSettingsAction::class)->handle($tenant, [
        'enterprise_number' => '0123456789',
    ]))->toThrow(InvalidArgumentException::class, 'presence_compliance_locked');

    expect($tenant->fresh()->presence_compliance_enabled)->toBeFalse();
});

it('schakelt CIAO in via platform-action', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $superuser = User::factory()->superuser()->create();

    app(TogglePresenceComplianceAction::class)->handle($tenant, (int) $superuser->id);

    expect($tenant->fresh()->presence_compliance_enabled)->toBeTrue()
        ->and($tenant->fresh()->presence_compliance_scope)->toBe(PresenceComplianceScope::CiaoCleaning->value)
        ->and(AuditLog::query()->where('action', 'tenant.presence_compliance_toggled')->exists())->toBeTrue();
});

it('weigert CIAO zonder Time-module', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);

    expect(fn () => app(TogglePresenceComplianceAction::class)->handle($tenant))
        ->toThrow(InvalidArgumentException::class, 'time_module_disabled');
});

it('zet CIAO uit wanneer Time uit gaat', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
    ]);

    app(ToggleTimeModuleAction::class)->handle($tenant);

    expect($tenant->fresh()->has_time_module)->toBeFalse()
        ->and($tenant->fresh()->presence_compliance_enabled)->toBeFalse();
});

it('toont vergrendeld CIAO-kader op instellingen', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    Livewire::actingAs($admin)
        ->test(Settings::class)
        ->assertSee(__('settings.presence.request_hint'), false)
        ->assertSee(__('contact.email'), false)
        ->assertDontSee(__('settings.presence.enabled_note'), false)
        ->call('savePresenceCompliance')
        ->assertHasErrors(['presenceComplianceEnabled']);

    expect($tenant->fresh()->presence_compliance_enabled)->toBeFalse();
});

it('schakelt CIAO in via platform Livewire', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(PlatformTenants::class)
        ->assertSee(__('platform.ciao_module'), false)
        ->call('togglePresenceCompliance', $tenant->id);

    expect($tenant->fresh()->presence_compliance_enabled)->toBeTrue();
});

it('weigert CIAO via platform zonder Time', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => false]);
    $superuser = User::factory()->superuser()->create();

    Livewire::actingAs($superuser)
        ->test(PlatformTenants::class)
        ->call('togglePresenceCompliance', $tenant->id)
        ->assertSee(__('platform.errors.ciao_requires_time'), false);

    expect($tenant->fresh()->presence_compliance_enabled)->toBeFalse();
});

it('weigert bouw-scope zolang construction flag uit staat', function () {
    config(['rsz.construction_scope_enabled' => false]);
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
    ]);

    expect(fn () => app(UpdatePresenceComplianceSettingsAction::class)->handle($tenant, [
        'presence_compliance_scope' => PresenceComplianceScope::CiaoConstruction->value,
    ]))->toThrow(InvalidArgumentException::class, 'presence_scope_unavailable');
});

it('enqueue met CiaoConstruction wanneer flag aan staat', function () {
    config(['rsz.construction_scope_enabled' => true]);
    Queue::fake();

    [$tenant, $worker, $clockPoint] = ciaoTenantReady();
    $tenant->update([
        'presence_compliance_scope' => PresenceComplianceScope::CiaoConstruction->value,
    ]);

    app(ClockInAction::class)->handle($worker, $clockPoint);

    $submission = PresenceSubmission::query()->first();
    expect($submission)->not->toBeNull()
        ->and($submission->scope)->toBe(PresenceComplianceScope::CiaoConstruction)
        ->and($submission->presence_type)->toBe(PresenceType::In);
});

it('exposeert time.presence webhook-events', function () {
    expect(\App\Models\WebhookEndpoint::AVAILABLE_EVENTS)->toContain(
        'time.presence.submitted',
        'time.presence.failed',
        'time.presence.skipped',
    );
});
