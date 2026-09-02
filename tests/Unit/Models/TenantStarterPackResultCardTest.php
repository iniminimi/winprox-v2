<?php

declare(strict_types=1);

use App\Models\Tenant;
use Illuminate\Support\Carbon;

it('toont de starttemplate-resultaatkaart binnen de zichtbaarheidsperiode', function () {
    $tenant = Tenant::factory()->create([
        'starter_pack_key' => 'hotel',
        'starter_pack_applied_at' => now()->subDays(3),
        'starter_pack_payload' => ['team_ids' => []],
    ]);

    expect($tenant->shouldShowStarterPackResultCard())->toBeTrue();
});

it('verbergt de starttemplate-resultaatkaart na de zichtbaarheidsperiode', function () {
    Carbon::setTestNow('2026-09-02 12:00:00');

    $tenant = Tenant::factory()->create([
        'starter_pack_key' => 'hotel',
        'starter_pack_applied_at' => now()->subDays(8),
        'starter_pack_payload' => ['team_ids' => []],
    ]);

    expect($tenant->shouldShowStarterPackResultCard())->toBeFalse();

    Carbon::setTestNow();
});

it('verbergt de starttemplate-resultaatkaart na handmatig sluiten', function () {
    $tenant = Tenant::factory()->create([
        'starter_pack_key' => 'hotel',
        'starter_pack_applied_at' => now(),
        'starter_pack_payload' => ['team_ids' => []],
        'starter_pack_result_dismissed_at' => now(),
    ]);

    expect($tenant->shouldShowStarterPackResultCard())->toBeFalse();
});
