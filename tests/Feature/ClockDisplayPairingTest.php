<?php

declare(strict_types=1);

use App\Actions\Time\ConfirmClockDisplayClaimAction;
use App\Actions\Time\DenyClockDisplayClaimAction;
use App\Actions\Time\ExpireClockDisplayClaimsAction;
use App\Actions\Time\IssueClockDisplayPairingCodeAction;
use App\Actions\Time\ResolveClockPointPortalTokenAction;
use App\Actions\Time\RotateClockPointDisplaySecretAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Actions\Time\UnlinkClockPointDisplayAction;
use App\Enums\ClockDisplayClaimStatus;
use App\Models\AuditLog;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy;
use App\Support\Time\ClockDisplayQr;
use App\Support\Time\ClockPointPortalTokenResolution;

afterEach(fn () => Tenancy::forget());

function displayTenant(): Tenant
{
    return Tenant::factory()->create(['has_time_module' => true]);
}

function displayPoint(Tenant $tenant): ClockPoint
{
    return ClockPoint::factory()->create(['tenant_id' => $tenant->id]);
}

it('geeft een 8-teken Crockford-code met TTL en auditeert de uitgifte', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, $admin->id);

    expect($code)->toMatch('/^[0-9A-HJKMNP-TV-Z]{8}$/');
    $point->refresh();
    expect($point->display_pairing_code)->toBe($code)
        ->and($point->display_pairing_expires_at->isFuture())->toBeTrue();
    expect(AuditLog::where('action', 'clock_point.display_pairing_issued')->exists())->toBeTrue();
});

it('maakt een pending claim aan met een geldige code', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);

    expect(fn () => app(SubmitClockDisplayClaimAction::class)->handle('00000000', 'esp32-aabbcc', null))
        ->toThrow(InvalidArgumentException::class, 'pairing_code_invalid');

    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', '10.0.0.1');
    expect($claim->status)->toBe(ClockDisplayClaimStatus::Pending)
        ->and($claim->isPending())->toBeTrue()
        ->and($claim->clock_point_id)->toBe($point->id);
});

it('is idempotent voor hetzelfde device en conflicteert voor een ander', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);

    $first = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    $retry = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);

    expect($retry->id)->toBe($first->id);

    expect(fn () => app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-ddeeff', null))
        ->toThrow(InvalidArgumentException::class, 'claim_pending');

    expect(ClockDisplayClaim::where('clock_point_id', $point->id)->count())->toBe(1);
});

it('bevestigt een claim en schrijft display-credentials op het punt', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', '10.0.0.1');

    $confirmed = app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);

    $point->refresh();
    expect($confirmed->status)->toBe(ClockDisplayClaimStatus::Confirmed)
        ->and($confirmed->issued_token)->toStartWith('wpclk_')
        ->and($point->hasLinkedDisplay())->toBeTrue()
        ->and(strlen($point->display_id))->toBe(24)
        ->and($point->display_secret)->not->toBeNull()
        ->and($point->display_last_seen_at)->not->toBeNull()
        ->and($point->display_pairing_code)->toBeNull();
    expect(AuditLog::where('action', 'clock_point.display_claim_confirmed')->exists())->toBeTrue();
});

it('weigert een claim als hij niet meer pending is', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $claim = ClockDisplayClaim::factory()->forPoint($point)->create([
        'status' => ClockDisplayClaimStatus::Denied->value,
    ]);

    expect(fn () => app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null))
        ->toThrow(InvalidArgumentException::class, 'claim_not_pending');
});

it('doodt open pending-claims bij het heruitgeven van een code (code_reissued)', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);

    app(IssueClockDisplayPairingCodeAction::class)->handle($point->fresh(), $tenant->id, null);

    $claim->refresh();
    expect($claim->status)->toBe(ClockDisplayClaimStatus::Denied)
        ->and($claim->denied_reason)->toBe('code_reissued')
        ->and($claim->isPending())->toBeFalse();
    expect(AuditLog::where('action', 'clock_point.display_claim_denied')->exists())->toBeTrue();

    // Stale admin-tab: de oude claim kan niet meer bevestigd worden.
    expect(fn () => app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null))
        ->toThrow(InvalidArgumentException::class, 'claim_not_pending');
});

it('vervangt een gekoppeld scherm expliciet bij re-pair (unlinked + confirmed audit)', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim1 = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-oud001', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim1, $tenant->id, null);

    $point->refresh();
    $oldSecret = $point->display_secret;
    $oldTokenHash = $point->display_token_hash;
    $displayId = $point->display_id;

    $code2 = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim2 = app(SubmitClockDisplayClaimAction::class)->handle($code2, 'esp32-nieuw1', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim2, $tenant->id, null);

    $point->refresh();
    expect($point->display_id)->toBe($displayId)
        ->and($point->display_secret)->not->toBe($oldSecret)
        ->and($point->display_token_hash)->not->toBe($oldTokenHash)
        ->and($point->display_device_hint)->toBe('esp32-nieuw1');

    $auditActions = AuditLog::where('model_id', $point->id)
        ->whereIn('action', ['clock_point.display_unlinked', 'clock_point.display_claim_confirmed'])
        ->pluck('action');
    expect($auditActions)->toContain('clock_point.display_unlinked');
});

it('ontkoppelt een scherm en houdt display_id voor het audit-spoor', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);

    $point->refresh();
    $displayId = $point->display_id;

    app(UnlinkClockPointDisplayAction::class)->handle($point, $tenant->id, null);

    $point->refresh();
    expect($point->hasLinkedDisplay())->toBeFalse()
        ->and($point->display_secret)->toBeNull()
        ->and($point->display_token_hash)->toBeNull()
        ->and($point->display_id)->toBe($displayId);
    expect(AuditLog::where('action', 'clock_point.display_unlinked')->exists())->toBeTrue();
});

it('roteert secret en device-token en maakt het oude toestel onbruikbaar', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    $claim = app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);
    $oldToken = $claim->issued_token;

    $point->refresh();
    $oldSecret = $point->display_secret;
    app(RotateClockPointDisplaySecretAction::class)->handle($point, $tenant->id, null);

    $point->refresh();
    expect($point->display_secret)->not->toBe($oldSecret)
        ->and($point->matchesDisplayToken($oldToken))->toBeFalse();
    expect(AuditLog::where('action', 'clock_point.display_secret_rotated')->exists())->toBeTrue();
});

it('laat verlopen pending-claims expireren via de cleanup en de predicate', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $claim = ClockDisplayClaim::factory()->forPoint($point)->create([
        'expires_at' => now()->subMinute(),
    ]);

    // Predicate faalt direct — ook zonder dat de cleanup al liep.
    expect($claim->isPending())->toBeFalse()
        ->and($claim->status)->toBe(ClockDisplayClaimStatus::Pending);

    $expired = app(ExpireClockDisplayClaimsAction::class)->handle();
    expect($expired)->toBe(1)
        ->and($claim->refresh()->status)->toBe(ClockDisplayClaimStatus::Expired);
});

it('stelt max claims per punt per uur in als cooldown', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $submit = app(SubmitClockDisplayClaimAction::class);
    $deny = app(DenyClockDisplayClaimAction::class);
    $issue = app(IssueClockDisplayPairingCodeAction::class);

    for ($i = 0; $i < 5; $i++) {
        $code = $issue->handle($point->fresh(), $tenant->id, null);
        $claim = $submit->handle($code, "esp32-000{$i}", null);
        $deny->handle($claim, $tenant->id, null);
    }

    $code = $issue->handle($point->fresh(), $tenant->id, null);
    expect(fn () => $submit->handle($code, 'esp32-0006', null))
        ->toThrow(InvalidArgumentException::class, 'claim_rate_limited');
});

it('lost een dynamische display-token op binnen het venster en blokkeert een verlopen', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);
    $point->refresh();

    $resolver = app(ResolveClockPointPortalTokenAction::class);
    $window = ClockDisplayQr::currentWindow();

    $valid = $point->display_id.ClockDisplayQr::dynFor($point->display_secret, $window);
    expect(strlen($valid))->toBe(64);
    expect($resolver->handle($valid)->status)->toBe(ClockPointPortalTokenResolution::STATUS_CURRENT);

    // ±1 venster binnen tolerantie.
    $prev = $point->display_id.ClockDisplayQr::dynFor($point->display_secret, $window - 1);
    expect($resolver->handle($prev)->status)->toBe(ClockPointPortalTokenResolution::STATUS_CURRENT);

    // Buiten tolerantie (stale foto van het scherm) → blocked, niet notFound.
    $stale = $point->display_id.ClockDisplayQr::dynFor($point->display_secret, $window - 5);
    $resolution = $resolver->handle($stale);
    expect($resolution->status)->toBe(ClockPointPortalTokenResolution::STATUS_BLOCKED)
        ->and($resolution->clockPoint->id)->toBe($point->id);

    // Onbekende display_id → notFound.
    expect($resolver->handle(str_repeat('a', 64))->status)
        ->toBe(ClockPointPortalTokenResolution::STATUS_NOT_FOUND);

    // Statische QR blijft gewoon werken.
    expect($resolver->handle($point->qr_token)->status)
        ->toBe(ClockPointPortalTokenResolution::STATUS_CURRENT);
});

it('opent het publieke portaal met een geldige dynamische token', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);
    $point->refresh();

    $token = $point->display_id.ClockDisplayQr::dynFor($point->display_secret, ClockDisplayQr::currentWindow());
    $this->get('/time/'.$token)->assertOk();
});

it('behandelt de claim- en status-endpoints end-to-end', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    Tenancy::forget();

    $formatted = substr($code, 0, 4).'-'.substr($code, 4);
    $this->postJson('/api/v1/time/clock-displays/claim', [
        'code' => $formatted,
        'device_hint' => 'esp32-aabbcc',
    ])->assertCreated()->assertJsonPath('status', 'pending');

    $claim = ClockDisplayClaim::where('clock_point_id', $point->id)->sole();

    $this->getJson('/api/v1/time/clock-displays/claim-status/'.$claim->claim_token)
        ->assertOk()->assertJsonPath('status', 'pending');

    Tenancy::actAs($tenant->id);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);
    Tenancy::forget();

    $status = $this->getJson('/api/v1/time/clock-displays/claim-status/'.$claim->claim_token)
        ->assertOk()
        ->assertJsonPath('status', 'confirmed')
        ->assertJsonStructure(['device_token', 'display_id', 'display_secret', 'server_time', 'portal_url_template'])
        ->json();

    $this->getJson('/api/v1/time/clock-displays/ping')
        ->assertUnauthorized();

    $ping = $this->getJson('/api/v1/time/clock-displays/ping', [
        'X-WinProx-Clock-Key' => $status['device_token'],
    ])->assertOk()->assertJsonPath('state', 'active')->json();

    expect($ping['server_time'])->not->toBeNull()
        ->and($ping['rotation_seconds'])->toBe(30);

    $point->refresh();
    expect($point->display_last_seen_at)->not->toBeNull();
});

it('geeft 404 op de koppelroute voor een Checkmate-tenant', function () {
    $tenant = Tenant::factory()->create([
        'has_time_module' => true,
        'checkmate_mode' => true,
    ]);
    $point = displayPoint($tenant);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    $this->actingAs($admin)
        ->get(route('time.clock-displays.pair', $point))
        ->assertNotFound();

    // Een gewone tenant kan er wél bij.
    $normal = displayTenant();
    $point2 = displayPoint($normal);
    $admin2 = User::factory()->admin()->create(['tenant_id' => $normal->id]);
    $this->actingAs($admin2)
        ->get(route('time.clock-displays.pair', $point2))
        ->assertOk();
});

it('bewaart aan-uren en stuurt ze mee in de ping-config', function () {
    $tenant = displayTenant();
    Tenancy::actAs($tenant->id);
    $point = displayPoint($tenant);
    $admin = User::factory()->admin()->create(['tenant_id' => $tenant->id]);

    app(App\Actions\Time\UpdateClockDisplayScheduleAction::class)
        ->handle($point, $tenant->id, $admin->id, '06:00', '19:00');

    $point->refresh();
    expect(substr((string) $point->display_on_from, 0, 5))->toBe('06:00')
        ->and(substr((string) $point->display_on_until, 0, 5))->toBe('19:00');
    expect(AuditLog::where('action', 'clock_point.display_schedule_updated')->exists())->toBeTrue();

    // Één open uiteinde is ongeldig.
    expect(fn () => app(App\Actions\Time\UpdateClockDisplayScheduleAction::class)
        ->handle($point, $tenant->id, $admin->id, '06:00', null))
        ->toThrow(InvalidArgumentException::class, 'schedule_incomplete');

    // Leeg/leeg = weer altijd aan.
    app(App\Actions\Time\UpdateClockDisplayScheduleAction::class)
        ->handle($point, $tenant->id, $admin->id, null, null);
    expect($point->refresh()->display_on_from)->toBeNull();

    // Ping: gekoppeld scherm krijgt de velden mee.
    $code = app(IssueClockDisplayPairingCodeAction::class)->handle($point, $tenant->id, null);
    $claim = app(SubmitClockDisplayClaimAction::class)->handle($code, 'esp32-aabbcc', null);
    app(ConfirmClockDisplayClaimAction::class)->handle($claim, $tenant->id, null);
    Tenancy::forget();

    app(App\Actions\Time\UpdateClockDisplayScheduleAction::class)
        ->handle($point->fresh(), $tenant->id, $admin->id, '06:00', '19:00');
    $token = $claim->fresh()->issued_token;

    $this->getJson('/api/v1/time/clock-displays/ping', [
        'X-WinProx-Clock-Key' => $token,
    ])->assertOk()
        ->assertJsonPath('display_on_from', '06:00')
        ->assertJsonPath('display_on_until', '19:00');
});
