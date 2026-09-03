<?php

declare(strict_types=1);

use App\Actions\Time\RetryPresenceSubmissionAction;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\PresenceSubmissionStatus;
use App\Enums\PresenceType;
use App\Jobs\SubmitPresenceSubmissionJob;
use App\Livewire\Time\PresenceSubmissionsIndex;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

it('laat een admin het CIAO-inzendingenscherm openen', function () {
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

    $this->actingAs($admin)
        ->get(route('time.ciao.index'))
        ->assertOk()
        ->assertSee(__('time.ciao.title'), false);
});

it('toont mislukte inzending en laat opnieuw in de wachtrij zetten', function () {
    Queue::fake();

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
    $location = Location::factory()->create([
        'tenant_id' => $tenant->id,
        'street' => 'Teststraat',
        'house_number' => '1',
        'postal_code' => '1000',
        'city' => 'Brussel',
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'first_name' => 'Jan',
        'last_name' => 'Jansen',
        'ssin' => '90010100123',
    ]);

    $submission = PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'location_id' => $location->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Failed,
        'error_message' => 'rsz_not_created',
    ]);

    $this->actingAs($admin)
        ->get(route('time.ciao.index'))
        ->assertOk()
        ->assertSee('Jan Jansen', false)
        ->assertSee(__('time.ciao.status.failed'), false);

    Livewire::actingAs($admin)
        ->test(PresenceSubmissionsIndex::class)
        ->call('retry', $submission->id)
        ->assertHasNoErrors();

    expect($submission->fresh()->status)->toBe(PresenceSubmissionStatus::Pending)
        ->and($submission->fresh()->error_message)->toBeNull();

    Queue::assertPushed(SubmitPresenceSubmissionJob::class);
});

it('retry via Action weigert submitted inzendingen', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'presence_compliance_enabled' => true,
    ]);
    Tenancy::actAs($tenant->id);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id]);
    $submission = PresenceSubmission::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'source_event' => PresenceSourceEvent::ClockIn,
        'presence_type' => PresenceType::In,
        'scope' => PresenceComplianceScope::CiaoCleaning,
        'registration_at' => now(),
        'status' => PresenceSubmissionStatus::Submitted,
        'rsz_id' => 1,
    ]);

    expect(fn () => app(RetryPresenceSubmissionAction::class)->handle($submission))
        ->toThrow(InvalidArgumentException::class, 'presence_submission_not_retryable');
});

it('toont CIAO-nav op Time-schermen', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    $this->actingAs($admin)
        ->get(route('time.presence.index'))
        ->assertOk()
        ->assertSee(__('time.nav.ciao'), false);
});
