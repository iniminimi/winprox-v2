<?php

use App\Actions\Time\ClockInAction;
use App\Actions\Time\ClockOutAction;
use App\Actions\Time\ForceCloseWorkShiftAction;
use App\Enums\BreakType;
use App\Enums\PresenceComplianceScope;
use App\Enums\PresenceSourceEvent;
use App\Enums\WorkShiftStatus;
use App\Livewire\Pages\Team as TeamPage;
use App\Livewire\Time\ShiftsIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Location;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkBreak;
use App\Models\Worker;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function requiredBreakTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => User::ROLE_ADMIN,
    ]);

    return [$tenant, $admin];
}

it('slaagt team-minimumpauze op via het teamformulier', function () {
    [$tenant, $admin] = requiredBreakTenant();

    Livewire::actingAs($admin)
        ->test(TeamPage::class)
        ->call('openCreateTeam')
        ->set('teamName', 'Schoonmaak')
        ->set('teamRequiredBreakMinutes', 30)
        ->call('saveTeam')
        ->assertHasNoErrors();

    $team = InternalTeam::query()->where('name', 'Schoonmaak')->first();
    expect($team)->not->toBeNull()
        ->and($team->required_break_minutes)->toBe(30);
});

it('past automatisch 30 minuten pauze toe bij uitklokken zonder live pauze', function () {
    [$tenant] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(8)]);

    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($closed->total_break_minutes)->toBe(30)
        ->and(WorkBreak::query()->where('work_shift_id', $closed->id)->count())->toBe(0);
});

it('behoudt een langere geklokte pauze boven het teamminimum', function () {
    [$tenant] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(8)]);
    $shift->breaks()->create([
        'tenant_id' => $tenant->id,
        'started_at' => now()->subMinutes(50),
        'ended_at' => now()->subMinutes(5),
        'break_type' => BreakType::Break,
    ]);

    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($closed->total_break_minutes)->toBe(45);
});

it('past geen teamminimum toe op een te korte dienst', function () {
    [$tenant] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subMinutes(20)]);

    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($closed->total_break_minutes)->toBe(0);
});

it('laat pauzeminuten ongewijzigd als het team geen minimum heeft', function () {
    [$tenant] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => null,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(8)]);

    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);

    expect($closed->total_break_minutes)->toBe(0);
});

it('past het teamminimum toe bij geforceerd sluiten', function () {
    [$tenant, $admin] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(8)]);

    $closed = app(ForceCloseWorkShiftAction::class)->handle(
        $shift->fresh(),
        'Vergeten uit te klokken',
        (int) $tenant->id,
        $admin->id,
    );

    expect($closed->status)->toBe(WorkShiftStatus::ForceClosed)
        ->and($closed->total_break_minutes)->toBe(30);
});

it('stuurt geen extra CIAO-pauze bij een toegepast teamminimum', function () {
    Queue::fake();
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
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'ssin' => '90010100123',
    ]);

    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update(['clock_in_at' => now()->subHours(8)]);
    $closed = app(ClockOutAction::class)->handle($worker, $clockPoint);

    $events = PresenceSubmission::query()->orderBy('id')->pluck('source_event')->map->value->all();

    expect($closed->total_break_minutes)->toBe(30)
        ->and(WorkBreak::query()->where('work_shift_id', $closed->id)->count())->toBe(0)
        ->and($events)->toBe([
            PresenceSourceEvent::ClockIn->value,
            PresenceSourceEvent::ClockOut->value,
        ]);
});

it('laat een admin het teamminimum manueel toepassen met auditlog', function () {
    [$tenant, $admin] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);

    $shift = WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(8),
        'clock_out_at' => now(),
        'total_break_minutes' => 0,
    ]);

    Livewire::actingAs($admin)
        ->test(ShiftsIndex::class)
        ->call('applyRequiredBreak', $shift->id)
        ->assertHasNoErrors();

    expect($shift->fresh()->total_break_minutes)->toBe(30);

    expect(DB::table('audit_logs')
        ->where('action', 'work_shift.required_break_applied')
        ->where('model_id', $shift->id)
        ->where('user_id', $admin->id)
        ->exists())->toBeTrue();
});

it('past het teamminimum toe via de API', function () {
    [$tenant, $admin] = requiredBreakTenant();
    $team = InternalTeam::factory()->create([
        'tenant_id' => $tenant->id,
        'required_break_minutes' => 30,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'is_active' => true,
    ]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $shift = WorkShift::factory()->closed()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'internal_team_id' => $team->id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'clock_out_clock_point_id' => $clockPoint->id,
        'clock_in_at' => now()->subHours(8),
        'clock_out_at' => now(),
        'total_break_minutes' => 0,
    ]);
    $token = $admin->createToken('test', ['time:write'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/time/work-shifts/'.$shift->id.'/apply-required-break')
        ->assertOk()
        ->assertJsonPath('data.total_break_minutes', 30);
});
