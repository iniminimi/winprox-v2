<?php

declare(strict_types=1);

use App\Actions\Dev\SeedTenantPresenceAction;
use App\Actions\Time\BuildTimePresenceSnapshotAction;
use App\Actions\Time\CountTimePresenceAttentionAction;
use App\Models\Tenant;

it('seed bulk aanwezigheid met pauze-shifts', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => false,
    ]);

    $result = app(SeedTenantPresenceAction::class)->handle($tenant, 12, 3);

    expect($result['open_shifts'])->toBe(12)
        ->and($result['on_break'])->toBe(3)
        ->and($result['present'])->toBe(9)
        ->and($result['alarms_seeded'])->toBe(0);

    $snapshot = app(BuildTimePresenceSnapshotAction::class)->handle($tenant->id);

    expect($snapshot->present)->toHaveCount(9)
        ->and($snapshot->onBreak)->toHaveCount(3);
});

it('seed demo-alarmen via oudere clock_in', function () {
    config(['time.long_shift_hours' => 8, 'time.stale_shift_hours' => 16]);

    $tenant = Tenant::factory()->create([
        'has_time_module' => false,
    ]);

    $result = app(SeedTenantPresenceAction::class)->handle($tenant, 6, 0, 3);

    expect($result['alarms_seeded'])->toBe(3)
        ->and(app(CountTimePresenceAttentionAction::class)->handle($tenant->id))->toBe(3);
});
