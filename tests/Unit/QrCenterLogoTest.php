<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Qr\QrCenterLogo;
use Illuminate\Support\Facades\Storage;

it('gebruikt het organisatielogo in het QR-centre wanneer aanwezig', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('tenant-logos/acme.png', 'fake-png');

    $tenant = Tenant::factory()->make([
        'logo_path' => 'tenant-logos/acme.png',
    ]);

    expect(QrCenterLogo::tenantLogoPublicUrl($tenant))->not->toBeNull()
        ->and(QrCenterLogo::publicUrl($tenant))->toBe(QrCenterLogo::tenantLogoPublicUrl($tenant))
        ->and(QrCenterLogo::absolutePath($tenant))->toBe(
            Storage::disk('public')->path('tenant-logos/acme.png'),
        );
});

it('valt terug op WinProx wanneer geen organisatielogo is', function (): void {
    $tenant = Tenant::factory()->make(['logo_path' => null]);

    expect(QrCenterLogo::publicUrl($tenant))->toBe(QrCenterLogo::winproxPublicUrl())
        ->and(QrCenterLogo::absolutePath($tenant))->toBe(
            \App\Support\Qr\QrCodePngWriter::winproxLogoPath(),
        );
});
