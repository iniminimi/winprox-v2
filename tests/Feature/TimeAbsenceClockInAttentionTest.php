<?php

declare(strict_types=1);

use App\Actions\Dashboard\BuildDashboardStatsAction;
use App\Actions\Time\BuildTimePresenceDashboardAction;
use App\Actions\Time\ClockInAction;
use App\Enums\HolidayAgreementEffect;
use App\Enums\ShiftTypeKind;
use App\Enums\TimePresenceAttentionType;
use App\Enums\WorkShiftStatus;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\Tenant;
use App\Models\TenantHolidayAgreement;
use App\Models\Worker;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function absenceClockInSetup(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);

    return [$tenant, $clockPoint, $worker];
}

function clockInAt(Worker $worker, ClockPoint $clockPoint, string $local): void
{
    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    $shift->update([
        'clock_in_at' => Carbon::parse($local, 'Europe/Brussels'),
    ]);
}

it('merkt een open shift op een volledige verlofdag', function () {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Europe/Brussels'));
    [$tenant, $clockPoint, $worker] = absenceClockInSetup();
    clockInAt($worker, $clockPoint, '2026-10-14 09:15:00');
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
    ]);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    $item = $dashboard->attentionItems->first(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn);

    expect($item)->not->toBeNull()
        ->and($item->absenceKind)->toBe('leave')
        ->and($item->message())->toBe(__('time.presence.attention.absence_clock_in_detail', [
            'kind' => __('time.presence.attention.absence_kinds.leave'),
        ]));

    $stats = app(BuildDashboardStatsAction::class)->handle($tenant->id, true, false);
    $tileKeys = collect($stats->kpiTiles())->pluck('key')->all();
    expect($stats->timeAttention)->toBe(1)
        ->and($tileKeys)->toContain('time_attention')
        ->and($tileKeys)->not->toContain('absence_clock_in');
});

it('merkt een getimed ziekteblok alleen als de inklok erin valt', function () {
    [$tenant, $clockPoint, $worker] = absenceClockInSetup();
    clockInAt($worker, $clockPoint, '2026-10-14 10:00:00');
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'kind' => ShiftTypeKind::Sick,
        'start_time' => '08:00',
        'end_time' => '12:00',
    ]);

    $covered = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($covered->attentionItems->first(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn)?->absenceKind)->toBe('sick');

    $worker->workShifts()->update([
        'status' => WorkShiftStatus::Closed,
        'clock_out_at' => Carbon::parse('2026-10-14 10:30:00', 'Europe/Brussels'),
    ]);
    clockInAt($worker, $clockPoint, '2026-10-14 13:00:00');

    $after = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($after->attentionItems->contains(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn))->toBeFalse();
});

it('merkt een feestdag en slaat een gewone werkdag of vervangingsdag over', function () {
    [$tenant, $clockPoint, $worker] = absenceClockInSetup();

    clockInAt($worker, $clockPoint, '2026-05-01 08:00:00');
    $holiday = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($holiday->attentionItems->first(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn)?->absenceKind)->toBe('holiday');

    TenantHolidayAgreement::query()->create([
        'tenant_id' => $tenant->id,
        'holiday_date' => '2026-05-01',
        'effect' => HolidayAgreementEffect::None,
    ]);
    $worked = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($worked->attentionItems->contains(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn))->toBeFalse();

    $worker->workShifts()->update([
        'status' => WorkShiftStatus::Closed,
        'clock_out_at' => Carbon::parse('2026-05-01 12:00:00', 'Europe/Brussels'),
    ]);
    clockInAt($worker, $clockPoint, '2026-11-02 09:00:00');
    $replacement = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($replacement->attentionItems->contains(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn))->toBeFalse();
});

it('telt een gewone werkplanning niet als afwezigheid', function () {
    [$tenant, $clockPoint, $worker] = absenceClockInSetup();
    clockInAt($worker, $clockPoint, '2026-10-14 09:00:00');
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-10-14',
        'kind' => ShiftTypeKind::Work,
        'start_time' => '08:00',
        'end_time' => '16:00',
    ]);

    $dashboard = app(BuildTimePresenceDashboardAction::class)->handle($tenant->id);
    expect($dashboard->attentionItems->contains(fn ($row) => $row->type === TimePresenceAttentionType::AbsenceClockIn))->toBeFalse();

    $stats = app(BuildDashboardStatsAction::class)->handle($tenant->id, true, false);
    expect(collect($stats->kpiTiles())->pluck('key')->all())->not->toContain('absence_clock_in');
});

it('kleurt het gsm-portaal rood bij inklokken tijdens verlof', function () {
    [$tenant, $clockPoint, $worker] = absenceClockInSetup();
    $worker->update([
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);
    $clockPoint->update(['qr_token' => 'absence-clock-'.$tenant->id]);
    $shift = app(ClockInAction::class)->handle($worker, $clockPoint);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => $shift->clock_in_at->timezone((string) config('app.timezone'))->toDateString(),
        'kind' => ShiftTypeKind::Leave,
        'start_time' => null,
        'end_time' => null,
    ]);

    Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon')
        ->assertSeeHtml('wp-portal-now--absence')
        ->assertDontSeeHtml('wp-portal-now--clocked')
        ->assertSee(__('time.portal.clock.absence_clock_in', [
            'kind' => __('time.presence.attention.absence_kinds.leave'),
        ]));
});
