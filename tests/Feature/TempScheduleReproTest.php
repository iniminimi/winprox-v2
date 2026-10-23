<?php

declare(strict_types=1);

use App\Actions\Time\PublishWeekAction;
use App\Actions\Time\SavePlannedShiftsAction;
use App\Actions\Time\SaveShiftTypeAction;
use App\Data\Time\PublishWeekData;
use App\Data\Time\SavePlannedShiftsData;
use App\Data\Time\SaveShiftTypeData;
use App\Enums\ShiftTypeColor;
use App\Enums\WorkerNotificationType;
use App\Models\WorkerNotification;
use App\Support\Tenancy;
use Carbon\Carbon;

it('repro: melding in andere maand dan huidige shifts', function () {
    $this->travelTo(Carbon::parse('2026-09-14 12:00:00'));
    [$tenant, $team, $clockPoint, $worker] = schedulePortalTenant();
    $week = Carbon::parse('2026-09-14')->startOfWeek(Carbon::MONDAY)->toDateString();
    $monday = Carbon::parse($week);
    app(SaveShiftTypeAction::class)->handle($tenant, new SaveShiftTypeData('D1', 'Dagdienst', '07:00', '15:00', 30, ShiftTypeColor::Emerald), null);

    $cells = [];
    for ($i = 0; $i < 7; $i++) {
        $date = $monday->copy()->addDays($i)->toDateString();
        $cells[] = ['worker_id' => $worker->id, 'date' => $date, 'raw' => 'D1'];
    }
    app(SavePlannedShiftsAction::class)->handle($tenant, new SavePlannedShiftsData($week, [$worker->id], $cells), null);
    app(PublishWeekAction::class)->handle($tenant, new PublishWeekData($week, [$worker->id]), null);

    // Correctie op een dag in OKTOBER (andere maand dan het rooster)
    WorkerNotification::create([
        'tenant_id' => $tenant->id,
        'worker_id' => $worker->id,
        'type' => WorkerNotificationType::RosterChanged,
        'reference_id' => '2026-10-21',
    ]);

    $this->travelBack();

    signInScheduleWorker($clockPoint)
        ->call('openSchedule')
        ->assertSet('scheduleListOpen', true)
        ->assertSet('scheduleMonth', '2026-10-01')   // sprong naar oktober!
        ->assertSee(__('time.portal.schedule.changed_notice', ['days' => 'wo 21/10']), false)
        ->assertSee(__('time.portal.schedule.empty'), false); // "Niets gepland in deze maand."
});
