<?php

declare(strict_types=1);

use App\Actions\Time\ValidateClockDisplayPresenceTokenAction;
use App\Enums\ClockDisplayMode;
use App\Models\ClockPoint;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Tenancy;
use App\Support\Time\ClockDisplayPresenceToken;

afterEach(fn () => Tenancy::forget());

function presenceWorld(): array
{
    $tenant = Tenant::factory()->create(['has_time_module' => true]);
    Tenancy::actAs($tenant->id);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    // Echte display_ids zijn Str::random (alfanumeriek), geen pure hex.
    $point = ClockPoint::factory()->forLocation($location)->create([
        'display_id' => 'k9zuar33m2plqwx7vbn4c1de',
        'display_secret' => str_repeat('cd', 32),
        'display_clock_mode' => ClockDisplayMode::Ble,
    ]);

    return [$tenant, $point];
}

function presenceHex(ClockPoint $point, int $ts, int $boot, int $counter): string
{
    return bin2hex(ClockDisplayPresenceToken::buildBinary(
        (string) $point->display_id,
        (string) $point->display_secret,
        $ts,
        $boot,
        $counter,
    ));
}

it('aanvaardt een geldig presence-token één keer', function () {
    [$tenant, $point] = presenceWorld();
    $hex = presenceHex($point, time(), 0x11111111, 1);

    $result = app(ValidateClockDisplayPresenceTokenAction::class)->handle($hex, $tenant->id);
    expect($result['clock_point']->id)->toBe($point->id);

    expect(fn () => app(ValidateClockDisplayPresenceTokenAction::class)->handle($hex, $tenant->id))
        ->toThrow(InvalidArgumentException::class, 'presence_token_replay');
});

it('weigert een verlopen token en een online-scherm', function () {
    [$tenant, $point] = presenceWorld();
    $ttl = max(10, (int) config('time.presence_token_ttl_seconds', 90));
    $drift = max(0, (int) config('time.presence_token_drift_seconds', 120));
    $old = presenceHex($point, time() - $ttl - $drift - 30, 1, 2);
    expect(fn () => app(ValidateClockDisplayPresenceTokenAction::class)->handle($old, $tenant->id))
        ->toThrow(InvalidArgumentException::class, 'presence_token_expired');

    $point->update(['display_clock_mode' => ClockDisplayMode::Online]);
    $hex = presenceHex($point->fresh(), time(), 2, 3);
    expect(fn () => app(ValidateClockDisplayPresenceTokenAction::class)->handle($hex, $tenant->id))
        ->toThrow(InvalidArgumentException::class, 'presence_token_mode');
});

it('laat twee tellers van dezelfde boot naast elkaar toe', function () {
    [$tenant, $point] = presenceWorld();
    $a = presenceHex($point, time(), 99, 1);
    $b = presenceHex($point, time(), 99, 2);
    app(ValidateClockDisplayPresenceTokenAction::class)->handle($a, $tenant->id);
    $second = app(ValidateClockDisplayPresenceTokenAction::class)->handle($b, $tenant->id);
    expect($second['counter'])->toBe(2);
});
