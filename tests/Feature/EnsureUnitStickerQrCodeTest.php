<?php

declare(strict_types=1);

use App\Actions\QrCodes\EnsureUnitStickerQrCodeAction;
use App\Models\Location;
use App\Models\QrCode;
use App\Models\Tenant;
use App\Models\Unit;
use App\Models\User;
use App\Support\Qr\LocationQrPackStickerEntries;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('provisions a linked qr code with sticker number for unit sticker export', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    $unit = Unit::factory()->withQrToken('qr-sticker-provision')->create([
        'tenant_id' => $tenant->id,
    ]);

    $qrCode = app(EnsureUnitStickerQrCodeAction::class)->handle($unit, (int) $tenant->id, null);

    expect($qrCode->unit_id)->toBe($unit->id)
        ->and($qrCode->sticker_number)->not->toBe('')
        ->and($qrCode->display_sticker_number)->toStartWith('Winprox-');
});

it('static qr-pack stickers include winprox sticker number in branded footer', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    $tenant = Tenant::factory()->create([
        'trial_ends_at' => now()->addDays(5),
        'qr_sticker_avery_62x89_header_text' => 'Scan deze QR-code',
    ]);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $location = Location::factory()->create(['tenant_id' => $tenant->id]);
    Unit::factory()->withQrToken('qr-static-footer-number')->create([
        'tenant_id' => $tenant->id,
        'location_id' => $location->id,
        'name' => 'Kopieerapparaat Xerox',
        'description' => 'Verdieping 0',
        'is_active' => true,
    ]);

    expect(QrCode::where('tenant_id', $tenant->id)->count())->toBe(0);

    $response = $this->actingAs($user)->get(route('locations.qr-pack', [
        'location' => $location,
        'template' => 'avery_62x89_r',
    ]));

    $response->assertOk();

    $qrCode = QrCode::where('tenant_id', $tenant->id)->first();
    expect($qrCode)->not->toBeNull();

    $entries = LocationQrPackStickerEntries::forLocation(
        $location->fresh(['units.qrCodes']),
        app(EnsureUnitStickerQrCodeAction::class),
        (int) $user->id,
    );

    expect($entries)->toHaveCount(1)
        ->and($entries[0]->stickerNumber)->toBe($qrCode->display_sticker_number);
});
