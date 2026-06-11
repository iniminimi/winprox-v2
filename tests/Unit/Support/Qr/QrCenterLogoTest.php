<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Support\Qr\QrCenterLogo;

it('returns null for tenant corner logo when no organisation logo is uploaded', function () {
    expect(QrCenterLogo::tenantLogoAbsolutePath(Tenant::factory()->make()))
        ->toBeNull();
});

it('falls back to winprox for qr center logo when tenant has no logo', function () {
    expect(QrCenterLogo::absolutePath(Tenant::factory()->make()))
        ->toBe(QrCenterLogo::winproxAbsolutePath());
});
