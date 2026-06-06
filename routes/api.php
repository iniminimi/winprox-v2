<?php

use App\Http\Controllers\Api\V1\HookController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Middleware\CheckTokenAbilities;
use App\Http\Middleware\SetTenantFromToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('hooks/inbound', [HookController::class, 'inbound'])
        ->middleware('throttle:60,1')
        ->name('api.v1.hooks.inbound');

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

            Route::post('units/import', [UnitController::class, 'import'])
                ->middleware([CheckTokenAbilities::class.':units:create'])
                ->name('api.v1.units.import');
        });
    });
});
