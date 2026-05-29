<?php

use App\Http\Controllers\Api\V1\HookController;
use App\Http\Controllers\Api\V1\IssueController;
use App\Http\Controllers\Api\V1\LocationController;
use App\Http\Controllers\Api\V1\TaskController;
use App\Http\Controllers\Api\V1\TeamController;
use App\Http\Controllers\Api\V1\UnitController;
use App\Http\Controllers\Api\V1\WorkerController;
use App\Http\Middleware\SetTenantFromToken;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::post('hooks/inbound', [HookController::class, 'inbound'])
        ->middleware('throttle:60,1')
        ->name('api.v1.hooks.inbound');

    Route::middleware(['auth:sanctum', SetTenantFromToken::class])->group(function () {
        Route::get('issues', [IssueController::class, 'index'])->name('api.v1.issues.index');
        Route::get('issues/{issue}', [IssueController::class, 'show'])->name('api.v1.issues.show');
        Route::post('issues', [IssueController::class, 'store'])->name('api.v1.issues.store');
        Route::post('issues/{issue}/approve', [IssueController::class, 'approve'])->name('api.v1.issues.approve');

        Route::get('tasks', [TaskController::class, 'index'])->name('api.v1.tasks.index');
        Route::get('tasks/{task}', [TaskController::class, 'show'])->name('api.v1.tasks.show');
        Route::post('tasks', [TaskController::class, 'store'])->name('api.v1.tasks.store');
        Route::post('tasks/{task}/start', [TaskController::class, 'start'])->name('api.v1.tasks.start');
        Route::post('tasks/{task}/complete', [TaskController::class, 'complete'])->name('api.v1.tasks.complete');
        Route::post('tasks/{task}/status', [TaskController::class, 'updateStatus'])->name('api.v1.tasks.status');

        Route::get('locations', [LocationController::class, 'index'])->name('api.v1.locations.index');
        Route::get('units', [UnitController::class, 'index'])->name('api.v1.units.index');
        Route::get('teams', [TeamController::class, 'index'])->name('api.v1.teams.index');
        Route::get('workers', [WorkerController::class, 'index'])->name('api.v1.workers.index');
    });
});
