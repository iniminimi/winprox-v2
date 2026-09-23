<?php

use App\Http\Controllers\Api\V1\AnnouncementController;
use App\Http\Controllers\Api\V1\EsgMeasurementController;
use App\Http\Controllers\Api\V1\HookController;
use App\Http\Controllers\Api\V1\IotEventController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\TranslationController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\UnitGpsReportController;
use App\Http\Controllers\Api\V1\UnitCheckController;
use App\Http\Controllers\Api\V1\UnitMeasurementController;
use App\Http\Controllers\Api\V1\PromoCampaignController;
use App\Http\Controllers\Api\V1\ReservationController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Controllers\Api\V1\WorkShiftController;
use App\Http\Controllers\Api\V1\TimeScheduleController;
use App\Http\Middleware\CheckTokenAbilities;
use App\Http\Middleware\SetTenantFromToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('hooks/inbound', [HookController::class, 'inbound'])
        ->middleware('throttle:60,1')
        ->name('api.v1.hooks.inbound');

    // IoT Connect ingest: gateway-token, buiten full Sanctum API (Facility + Corporate).
    Route::post('iot/events', [IotEventController::class, 'store'])
        ->middleware(['iot.gateway', 'throttle:120,1', 'idempotency'])
        ->name('api.v1.iot.events.store');

    Route::middleware(['auth:sanctum', SetTenantFromToken::class, 'api.access'])->group(function () {
        // Read endpoints (geen idempotency nodig)
        Route::get('issues', [IssueController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':issues:read'])
            ->name('api.v1.issues.index');
        Route::get('issues/{issue}', [IssueController::class, 'show'])
            ->middleware([CheckTokenAbilities::class.':issues:read'])
            ->name('api.v1.issues.show');

        Route::get('tasks', [TaskController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':tasks:read'])
            ->name('api.v1.tasks.index');
        Route::get('tasks/{task}', [TaskController::class, 'show'])
            ->middleware([CheckTokenAbilities::class.':tasks:read'])
            ->name('api.v1.tasks.show');

        Route::get('locations', [LocationController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':locations:read'])
            ->name('api.v1.locations.index');
        Route::get('units', [UnitController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':units:read'])
            ->name('api.v1.units.index');
        Route::get('teams', [TeamController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':teams:read'])
            ->name('api.v1.teams.index');
        Route::get('workers', [WorkerController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':workers:read'])
            ->name('api.v1.workers.index');
        Route::get('time/work-shifts', [WorkShiftController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':time:read'])
            ->name('api.v1.time.work-shifts.index');
        Route::get('time/schedule', [TimeScheduleController::class, 'show'])
            ->middleware([CheckTokenAbilities::class.':time:read'])
            ->name('api.v1.time.schedule.show');
        Route::get('time/shift-types', [TimeScheduleController::class, 'types'])
            ->middleware([CheckTokenAbilities::class.':time:read'])
            ->name('api.v1.time.shift-types.index');
        Route::get('announcements', [AnnouncementController::class, 'index'])
            ->middleware([CheckTokenAbilities::class.':locations:read'])
            ->name('api.v1.announcements.index');
        Route::get('announcements/{announcement}', [AnnouncementController::class, 'show'])
            ->middleware([CheckTokenAbilities::class.':locations:read'])
            ->name('api.v1.announcements.show');

        // Write endpoints (idempotency middleware)
        Route::middleware('idempotency')->group(function () {
            Route::post('issues', [IssueController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':issues:create'])
                ->name('api.v1.issues.store');
            Route::post('issues/{issue}/approve', [IssueController::class, 'approve'])
                ->middleware([CheckTokenAbilities::class.':issues:update'])
                ->name('api.v1.issues.approve');

            Route::post('tasks', [TaskController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':tasks:create'])
                ->name('api.v1.tasks.store');
            Route::post('tasks/{task}/start', [TaskController::class, 'start'])
                ->middleware([CheckTokenAbilities::class.':tasks:update'])
                ->name('api.v1.tasks.start');
            Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])
                ->middleware([CheckTokenAbilities::class.':tasks:update'])
                ->name('api.v1.tasks.complete');
            Route::post('tasks/{task}/status', [TaskController::class, 'updateStatus'])
                ->middleware([CheckTokenAbilities::class.':tasks:update'])
                ->name('api.v1.tasks.status');

            Route::post('locations/import', [LocationController::class, 'import'])
                ->middleware([CheckTokenAbilities::class.':locations:create'])
                ->name('api.v1.locations.import');
            Route::post('units/import', [UnitController::class, 'import'])
                ->middleware([CheckTokenAbilities::class.':units:create'])
                ->name('api.v1.units.import');
            Route::post('units/{unit}/gps-reports', [UnitGpsReportController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':units:update'])
                ->name('api.v1.units.gps-reports.store');
            Route::post('units/checks', [UnitCheckController::class, 'storeByExternalId'])
                ->middleware([CheckTokenAbilities::class.':units:update', 'idempotency'])
                ->name('api.v1.units.checks.store-by-external');
            Route::post('units/{unit}/checks', [UnitCheckController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':units:update'])
                ->name('api.v1.units.checks.store');
            Route::post('units/{unit}/measurements', [UnitMeasurementController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':units:update'])
                ->name('api.v1.units.measurements.store');
            Route::post('esg/measurements', [EsgMeasurementController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':esg:create'])
                ->name('api.v1.esg.measurements.store');
            Route::post('time/clock-in', [WorkShiftController::class, 'clockIn'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.clock-in');
            Route::post('time/manual-clock-in', [WorkShiftController::class, 'manualClockIn'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.manual-clock-in');
            Route::post('time/clock-out', [WorkShiftController::class, 'clockOut'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.clock-out');
            Route::post('time/work-visits/start', [WorkShiftController::class, 'startVisit'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.work-visits.start');
            Route::post('time/work-visits/end', [WorkShiftController::class, 'endVisit'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.work-visits.end');
            Route::post('time/work-shifts/{workShift}/apply-required-break', [WorkShiftController::class, 'applyRequiredBreak'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.work-shifts.apply-required-break');
            Route::post('time/workers/{worker}/release-clock-device', [WorkShiftController::class, 'releaseClockDevice'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.workers.release-clock-device');
            Route::put('time/schedule', [TimeScheduleController::class, 'save'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.schedule.save');
            Route::post('time/schedule/copy', [TimeScheduleController::class, 'copy'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.schedule.copy');
            Route::post('time/schedule/publish', [TimeScheduleController::class, 'publish'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.schedule.publish');
            Route::post('time/shift-types', [TimeScheduleController::class, 'storeType'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.shift-types.store');
            Route::patch('time/shift-types/{shiftType}', [TimeScheduleController::class, 'updateType'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.shift-types.update');
            Route::post('time/shift-types/{shiftType}/active', [TimeScheduleController::class, 'setTypeActive'])
                ->middleware([CheckTokenAbilities::class.':time:write'])
                ->name('api.v1.time.shift-types.active');

            Route::get('reservations', [ReservationController::class, 'index'])
                ->middleware([CheckTokenAbilities::class.':reservations:read'])
                ->name('api.v1.reservations.index');
            Route::post('reservations', [ReservationController::class, 'store'])
                ->middleware([CheckTokenAbilities::class.':reservations:write', 'idempotency'])
                ->name('api.v1.reservations.store');
            Route::patch('reservations/{reservation}', [ReservationController::class, 'update'])
                ->middleware([CheckTokenAbilities::class.':reservations:write', 'idempotency'])
                ->name('api.v1.reservations.update');
            Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy'])
                ->middleware([CheckTokenAbilities::class.':reservations:write', 'idempotency'])
                ->name('api.v1.reservations.destroy');
        });

        // Translation sync endpoints (superuser-only, authorized via UserPolicy::runTranslationSync)
        Route::get('translations/export', [TranslationController::class, 'export'])
            ->name('api.v1.translations.export');
        Route::post('translations/import', [TranslationController::class, 'import'])
            ->middleware('idempotency')
            ->name('api.v1.translations.import');
        Route::get('translations/status', [TranslationController::class, 'status'])
            ->name('api.v1.translations.status');
    });

    // Platform promo-campagnes (superuser, geen tenant-scope / api.access)
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('promo-campaigns', [PromoCampaignController::class, 'index'])
            ->name('api.v1.promo-campaigns.index');
        Route::get('promo-campaigns/{promoCampaign}', [PromoCampaignController::class, 'show'])
            ->name('api.v1.promo-campaigns.show');

        Route::middleware('idempotency')->group(function () {
            Route::post('promo-campaigns', [PromoCampaignController::class, 'store'])
                ->name('api.v1.promo-campaigns.store');
            Route::patch('promo-campaigns/{promoCampaign}', [PromoCampaignController::class, 'update'])
                ->name('api.v1.promo-campaigns.update');
            Route::delete('promo-campaigns/{promoCampaign}', [PromoCampaignController::class, 'destroy'])
                ->name('api.v1.promo-campaigns.destroy');
            Route::post('promo-campaigns/{promoCampaign}/queue-emails', [PromoCampaignController::class, 'queueEmails'])
                ->name('api.v1.promo-campaigns.queue-emails');
            Route::post('promo-campaigns/{promoCampaign}/pause-sending', [PromoCampaignController::class, 'pauseSending'])
                ->name('api.v1.promo-campaigns.pause-sending');
            Route::post('promo-campaigns/{promoCampaign}/resume-sending', [PromoCampaignController::class, 'resumeSending'])
                ->name('api.v1.promo-campaigns.resume-sending');
        });
    });
});
