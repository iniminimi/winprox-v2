<?php

declare(strict_types=1);

use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\ShiftTypeColor;
use App\Enums\ShiftTypeKind;
use App\Enums\WorkerNotificationType;
use App\Livewire\Public\TimePortal;
use App\Models\ClockPoint;
use App\Models\InternalTeam;
use App\Models\Tenant;
use App\Models\Worker;
use App\Models\WorkerNotification;
use App\Support\Tenancy;
use Carbon\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

afterEach(fn () => Tenancy::forget());

function schedulePortalTenant(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $team = InternalTeam::factory()->create(['tenant_id' => $tenant->id]);
    $clockPoint = ClockPoint::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => 'Poort Noord',
        'qr_token' => 'schedule-clock-'.$tenant->id,
    ]);
    $worker = Worker::factory()->create([
        'tenant_id' => $tenant->id,
        'internal_team_id' => $team->id,
        'first_name' => 'Jan',
        'last_name' => 'Janssen',
        'field_icon_slug' => 'heart',
    ]);

    return [$tenant, $team, $clockPoint, $worker];
}

function signInScheduleWorker(ClockPoint $clockPoint): mixed
{
    return Livewire::test(TimePortal::class, ['token' => $clockPoint->qr_token])
        ->set('first_name', 'Jan')
        ->set('last_name', 'Janssen')
        ->call('identifyWorker')
        ->set('sign_in_icon_slug', 'heart')
        ->call('signInWithIcon');
}

it('toont de tegel Mijn rooster na aanmelden', function () {
    [, , $clockPoint] = schedulePortalTenant();

    signInScheduleWorker($clockPoint)
        ->assertSee(__('time.portal.schedule.tile'), false)
        ->assertSet('scheduleListOpen', false);
});

it('toont alleen published eigen diensten en zet de badge weg', function () {
    // Vaste "nu" tijdens de rooster-writes: de vaste testweek is vergrendeld.
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('VL', 'Verlof', null, null, 0, ShiftTypeColor::Rose, true, ShiftTypeKind::Leave),
        null,
    );

    $cells = [];
    $monday = Carbon::parse($week);
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $date === $week ? 'VL' : '',
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        null,
    );

    $component = signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertDontSeeHtml('wire:click="signOut"')
        ->assertSee(__('time.portal.schedule.empty'), false);

    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        null,
    );

    expect(WorkerNotification::query()->where('worker_id', $worker->id)->whereNull('read_at')->count())->toBe(1);

    signInScheduleWorker($clockPoint)
        ->assertSeeHtml('wp-pill--new')
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertSee(__('time.schedule.types.kinds.leave'), false)
        ->assertSee('14/09', false)
        ->assertSet('scheduleMonth', '2026-09-01')
        ->assertDontSee('wp-list', false);

    expect(WorkerNotification::query()->where('worker_id', $worker->id)->whereNull('read_at')->count())->toBe(0);

    $this->travelBack();
});

it('legt op Mijn rooster uit welke dagen de badge telde', function () {
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    $monday = Carbon::parse($week);
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        null,
    );

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => in_array($i, [0, 2], true) ? 'D1' : '',
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        null,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        null,
    );

    // Planner past woensdag aan → tweede ongelezen melding (roster_changed).
    $changedDate = $monday->copy()->addDays(2);
    WorkerNotification::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'type' => WorkerNotificationType::RosterChanged,
        'reference_id' => $changedDate->toDateString(),
    ]);

    expect(WorkerNotification::query()->where('worker_id', $worker->id)->whereNull('read_at')->count())->toBe(2);

    $publishedLabel = Carbon::parse($week)->locale(app()->getLocale())->translatedFormat('l j F');
    $changedLabel = $changedDate->copy()->locale(app()->getLocale())->isoFormat('dd').' '.$changedDate->format('d/m');

    signInScheduleWorker($clockPoint)
        ->assertSeeHtml('wp-pill--new')
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertSee(__('time.portal.schedule.published_notice', ['date' => $publishedLabel]), false)
        ->assertSee(__('time.portal.schedule.changed_notice', ['days' => $changedLabel]), false)
        ->assertSeeHtml('wp-portal-schedule__row--changed')
        ->call('closeSchedule')
        ->assertSet('scheduleListOpen', false)
        ->assertDontSeeHtml('wp-pill--new');

    expect(WorkerNotification::query()->where('worker_id', $worker->id)->whereNull('read_at')->count())->toBe(0);

    // Heropenen: meldingen zijn gelezen — geen markeringen meer.
    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertDontSeeHtml('wp-portal-schedule__notice')
        ->assertDontSeeHtml('wp-portal-schedule__row--changed');

    $this->travelBack();
});

it('weigert de roosterlijst via Livewire-state', function () {
    [, , $clockPoint] = schedulePortalTenant();
    $component = signInScheduleWorker($clockPoint);

    expect(fn () => $component->set('scheduleListOpen', true))
        ->toThrow(CannotUpdateLockedPropertyException::class);
});

it('toont de groepsnaam uit de snapshot op Mijn rooster', function () {
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $location = \App\Models\Location::factory()->create(['tenant_id' => $tenant->id]);
    \App\Models\Unit::factory()->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Groep 1',
        'roster_code' => 'G1',
    ]);
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        null,
    );

    $cells = [];
    $monday = Carbon::parse($week);
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $date === $week ? 'D1/G1' : '',
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        null,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        null,
    );

    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSee('Groep 1', false)
        ->assertSee('Dagdienst', false)
        ->assertSee('07:00-15:00', false)
        ->assertSee('14/09', false)
        ->assertSee(__('time.portal.schedule.columns.date'), false)
        ->assertSee(__('time.portal.schedule.columns.duty'), false)
        ->assertSee(__('time.portal.schedule.columns.hours'), false);

    $this->travelBack();
});

it('slaagt lege dagen over in het maandoverzicht van Mijn rooster', function () {
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst 1', '08:00', '17:00', 30, ShiftTypeColor::Emerald),
        null,
    );

    $cells = [];
    $monday = Carbon::parse($week);
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => in_array($i, [0, 4], true) ? 'D1' : '', // ma + vr
        ];
    }
    $nextMonday = $monday->copy()->addWeek()->toDateString();
    $nextWeekCells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = Carbon::parse($nextMonday)->addDays($i)->toDateString();
        $nextWeekCells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => $i === 0 ? 'D1' : '',
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        null,
    );
    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($nextMonday, [$worker->id], $nextWeekCells),
        null,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        null,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($nextMonday, [$worker->id]),
        null,
    );

    $this->travelTo(Carbon::parse('2026-09-14')->setTime(10, 0));

    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSee('Dagdienst 1', false)
        ->assertSee('08:00-17:00', false)
        ->assertSee('14/09', false)
        ->assertSee('18/09', false)
        ->assertSee('21/09', false)
        ->assertDontSee('15/09', false)
        ->assertSeeHtml('wp-portal-schedule__table')
        ->assertSeeHtml('wp-portal-schedule__row--week-start')
        ->assertSeeHtml('wp-portal-schedule__row--today');
});

it('springt naar de meldingsmaand als daar published diensten staan', function () {
    // Beheerder corrigeerde achteraf het september-rooster; nu is oktober.
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    app(SaveShiftTypeAction::class)->handle(
        $tenant,
        new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Emerald),
        null,
    );

    $cells = [];
    $monday = Carbon::parse($week);
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = [
            'worker_id' => $worker->id,
            'date' => $date,
            'raw' => in_array($i, [0, 2], true) ? 'D1' : '', // ma 14/09 + wo 16/09
        ];
    }

    app(SavePlannedShiftsAction::class)->handle(
        $tenant,
        new SavePlannedShiftsData($week, [$worker->id], $cells),
        null,
    );
    app(PublishWeekAction::class)->handle(
        $tenant,
        new PublishWeekData($week, [$worker->id]),
        null,
    );

    // Achteraf-correctie: 2 meldingen voor septemberdagen met een dienst.
    foreach (['2026-09-14:1', '2026-09-16:2'] as $reference) {
        WorkerNotification::create([
            'tenant_id' => $tenant->id,
            'worker_id' => $worker->id,
            'type' => WorkerNotificationType::RosterCorrected,
            'reference_id' => $reference,
        ]);
    }

    $this->travelTo(Carbon::parse('2026-10-05 10:00:00'));

    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertSet('scheduleMonth', '2026-09-01')
        ->assertSee('14/09', false)
        ->assertSee('16/09', false)
        ->assertSee('Dagdienst', false)
        ->assertSeeHtml('wp-portal-schedule__row--changed')
        ->assertDontSee(__('time.portal.schedule.empty'), false);

    $this->travelBack();
});

it('blijft op de huidige maand als de meldingsmaand geen diensten heeft', function () {
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, , $clockPoint, $worker] = schedulePortalTenant();

    // Melding voor oktober — de worker heeft daar geen published diensten.
    WorkerNotification::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'type' => WorkerNotificationType::RosterCorrected,
        'reference_id' => '2026-10-05:1',
    ]);

    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertSet('scheduleMonth', '2026-09-01')
        ->assertSee(__('time.portal.schedule.empty'), false);

    $this->travelBack();
});
