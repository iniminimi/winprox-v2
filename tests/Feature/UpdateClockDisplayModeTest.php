<?php

declare(strict_types=1);

use App\Actions\Time\UpdateClockDisplayModeAction;
use App\Enums\ClockDisplayMode;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('slaat de klokmodus op zonder audit-fout', function () {
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $user = User::factory()->create();
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    $point = ClockPoint::factory()->forLocation($location)->create([
        'display_id' => str_repeat('ab', 12),
        'display_secret' => str_repeat('cd', 32),
        'display_clock_mode' => ClockDisplayMode::Online,
    ]);

    $updated = app(UpdateClockDisplayModeAction::class)->handle(
        $point,
        $tenant->id,
        $user->id,
        ClockDisplayMode::Ble,
    );

    expect($updated->display_clock_mode)->toBe(ClockDisplayMode::Ble);
});
