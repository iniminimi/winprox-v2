<?php

use App\Actions\Time\BuildWorkShiftWeekGridAction;
use App\Enums\BreakType;
use App\Enums\ClockSource;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkShiftStatus;
use App\Livewire\Time\ShiftsIndex;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\PlannedShift;
use App\Models\ShiftType;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkBreak;
use App\Models\WorkShift;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Livewire;

function weekViewSetup(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => User::ROLE_ADMIN]);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $worker = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $team->id]);
    $clockPoint = ClockPoint::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Hoofdingang']);

    return [$tenant, $admin, $worker, $clockPoint];
}

function weekViewShift(Worker $worker, ClockPoint $clockPoint, string $in, ?string $out, int $breakMinutes = 0): WorkShift
{
    return WorkShift::factory()->create([
        'tenant_id' => $worker->tenant_id,
        'worker_id' => $worker->id,
        'internal_team_id' => $worker->internal_team_id,
        'clock_in_clock_point_id' => $clockPoint->id,
        'status' => $out === null ? WorkShiftStatus::Open : WorkShiftStatus::Closed,
        'clock_in_at' => Carbon::parse($in),
        'clock_out_at' => $out === null ? null : Carbon::parse($out),
        'clock_out_source' => $out === null ? null : ClockSource::ClockPointQr,
        'total_break_minutes' => $breakMinutes,
    ]);
}

it('bouwt per dag een blok met pauze, geplande dienst en totalen', function () {
    [$tenant, , $worker, $clockPoint] = weekViewSetup();
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));

    $shift = weekViewShift($worker, $clockPoint, '2026-11-17 08:00:00', '2026-11-17 16:00:00', 30);
    WorkBreak::query()->create([
        'tenant_id' => $tenant->id,
        'work_shift_id' => $shift->id,
        'started_at' => Carbon::parse('2026-11-17 12:00:00'),
        'ended_at' => Carbon::parse('2026-11-17 12:30:00'),
        'break_type' => BreakType::Break,
    ]);
    $type = ShiftType::factory()->create(['tenant_id' => $tenant->id, 'kind' => ShiftTypeKind::Work, 'code' => 'VR', 'is_active' => true]);
    PlannedShift::factory()->create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'work_date' => '2026-11-17',
        'kind' => ShiftTypeKind::Work,
        'shift_type_id' => $type->id,
        'start_time' => '07:00',
        'end_time' => '15:00',
    ]);

    $grid = app(BuildWorkShiftWeekGridAction::class)->handle($tenant->id, $worker->id, '2026-11-19');

    expect($grid['weekStart'])->toBe('2026-11-16')
        ->and($grid['days'])->toHaveCount(7)
        ->and($grid['workedMinutes'])->toBe(450)
        ->and($grid['firstHour'])->toBe(6)
        ->and($grid['days'][2]['isToday'])->toBeTrue();

    $tuesday = $grid['days'][1];
    expect($tuesday['workedMinutes'])->toBe(450)
        ->and($tuesday['blocks'])->toHaveCount(1)
        ->and($tuesday['blocks'][0]['start'])->toBe('08:00')
        ->and($tuesday['blocks'][0]['end'])->toBe('16:00')
        ->and($tuesday['blocks'][0]['place'])->toBe('Hoofdingang')
        ->and($tuesday['blocks'][0]['topPct'])->toBe(33.333)
        ->and($tuesday['blocks'][0]['heightPct'])->toBe(33.333)
        ->and($tuesday['blocks'][0]['breaks'])->toBe([['topPct' => 50.0, 'heightPct' => 6.25]])
        ->and($tuesday['planned'][0]['label'])->toBe('VR 07:00–15:00')
        ->and($tuesday['planned'][0]['topPct'])->toBe(29.167);
});

it('verdeelt een nachtdienst over twee dagen en telt ze bij de dag van inklokken', function () {
    [$tenant, , $worker, $clockPoint] = weekViewSetup();
    $this->travelTo(Carbon::parse('2026-11-20 10:00:00'));

    weekViewShift($worker, $clockPoint, '2026-11-16 22:00:00', '2026-11-17 06:00:00');

    $grid = app(BuildWorkShiftWeekGridAction::class)->handle($tenant->id, $worker->id, '2026-11-16');

    expect($grid['days'][0]['blocks'][0]['topPct'])->toBe(91.667)
        ->and($grid['days'][0]['blocks'][0]['heightPct'])->toBe(8.333)
        ->and($grid['days'][0]['workedMinutes'])->toBe(480)
        ->and($grid['days'][1]['blocks'][0]['topPct'])->toBe(0.0)
        ->and($grid['days'][1]['blocks'][0]['heightPct'])->toBe(25.0)
        ->and($grid['days'][1]['workedMinutes'])->toBe(0);
});

it('toont geen diensten van een andere uitvoerder of tenant', function () {
    [$tenant, , $worker, $clockPoint] = weekViewSetup();
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    $colleague = Worker::factory()->create(['tenant_id' => $tenant->id, 'internal_team_id' => $worker->internal_team_id]);
    weekViewShift($colleague, $clockPoint, '2026-11-17 08:00:00', '2026-11-17 16:00:00');

    $grid = app(BuildWorkShiftWeekGridAction::class)->handle($tenant->id, $worker->id, '2026-11-17');
    $otherTenant = app(BuildWorkShiftWeekGridAction::class)->handle($tenant->id + 999, $colleague->id, '2026-11-17');

    expect(collect($grid['days'])->sum(fn ($day) => count($day['blocks'])))->toBe(0)
        ->and(collect($otherTenant['days'])->sum(fn ($day) => count($day['blocks'])))->toBe(0);
});

it('laat zaterdag en zondag weg tot weekends aan staat', function () {
    [$tenant, $admin, $worker, $clockPoint] = weekViewSetup();
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    weekViewShift($worker, $clockPoint, '2026-11-21 10:00:00', '2026-11-21 14:00:00');
    weekViewShift($worker, $clockPoint, '2026-11-18 08:00:00', '2026-11-18 12:00:00');

    $grid = app(BuildWorkShiftWeekGridAction::class)->handle($tenant->id, $worker->id, '2026-11-18', null, false);

    expect(array_column($grid['days'], 'date'))->toBe([
        '2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20',
    ])->and($grid['workedMinutes'])->toBe(240);

    Livewire::actingAs($admin)
        ->withQueryParams([
            'view' => 'week',
            'worker' => $worker->id,
            'from' => '2026-11-16',
            'to' => '2026-11-22',
        ])
        ->test(ShiftsIndex::class)
        ->assertSet('showWeekends', false)
        ->assertDontSee('10:00 – 14:00')
        ->assertSee('08:00 – 12:00')
        ->set('showWeekends', true)
        ->assertSee('10:00 – 14:00')
        ->call('setView', 'list')
        ->assertSee('21-11-2026')
        ->set('showWeekends', false)
        ->assertDontSee('21-11-2026')
        ->assertSee('18-11-2026');
});

it('vraagt in de weekweergave eerst een uitvoerder', function () {
    [, $admin] = weekViewSetup();

    Livewire::actingAs($admin)
        ->test(ShiftsIndex::class)
        ->call('setView', 'week')
        ->assertSet('view', 'week')
        ->assertSee(__('time.shifts.week.pick_worker'));
});

it('toont de week van een uitvoerder en bladert per week', function () {
    [, $admin, $worker, $clockPoint] = weekViewSetup();
    $this->travelTo(Carbon::parse('2026-11-18 10:00:00'));
    weekViewShift($worker, $clockPoint, '2026-11-17 08:00:00', '2026-11-17 16:00:00', 30);
    weekViewShift($worker, $clockPoint, '2026-11-18 07:30:00', null);

    Livewire::actingAs($admin)
        ->withQueryParams(['view' => 'week', 'worker' => $worker->id])
        ->test(ShiftsIndex::class)
        ->assertSee('08:00 – 16:00')
        ->assertSee('07:30 – '.__('time.shifts.week.now'))
        ->assertSee('Hoofdingang')
        ->assertSeeHtml('wire:click="openCorrection(')
        ->assertSeeHtml('wire:click="openForceClose(')
        ->assertDontSee(__('time.shifts.empty'))
        ->call('previousWeek')
        ->assertSet('weekStart', '2026-11-09')
        ->assertDontSee('08:00 – 16:00')
        ->call('thisWeek')
        ->assertSet('weekStart', '2026-11-16')
        ->assertSee('08:00 – 16:00');
});
