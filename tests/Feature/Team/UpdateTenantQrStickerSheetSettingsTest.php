<?php

declare(strict_types=1);

use App\Actions\Team\UpdateTenantQrStickerSheetSettingsAction;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Enums\QrStickerCenterLogoMode;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;

afterEach(fn () => Tenancy::forget());

it('stores Avery 62x89 sticker header text in tenant_qr_sticker_sheet_settings', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $updatedTenant = app(UpdateTenantQrStickerSheetSettingsAction::class)->handle(
        $tenant,
        UpdateTenantQrStickerSheetSettingsData::fromValidated(
            QrStickerSheetTemplate::Avery62x89R,
            ['headerText' => 'Scan deze QR-code'],
        ),
        null,
    );

    $setting = $updatedTenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);

    expect($setting)->not->toBeNull()
        ->and($setting->header_text)->toBe('Scan deze QR-code')
        ->and($setting->template)->toBe(QrStickerSheetTemplate::Avery62x89R->value);

    expect(AuditLog::query()
        ->where('tenant_id', $tenant->id)
        ->where('action', 'tenant.qr_sticker_sheet_settings_updated')
        ->exists())->toBeTrue();
});

it('stores Avery 62x89 sticker layout config', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    app(UpdateTenantQrStickerSheetSettingsAction::class)->handle(
        $tenant,
        UpdateTenantQrStickerSheetSettingsData::fromValidated(
            QrStickerSheetTemplate::Avery62x89R,
            [
                'headerText' => 'Scan hier',
                'centerLogo' => QrStickerCenterLogoMode::Winprox->value,
                'cornerTenantLogo' => false,
                'showTenantAddress' => false,
            ],
        ),
        null,
    );

    $setting = TenantQrStickerSheetSetting::query()
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($setting?->layout_config)->toBe([
        'center_logo' => 'winprox',
        'corner_tenant_logo' => false,
        'tenant_address' => false,
    ]);
});

it('clears Avery 62x89 sticker header text when saved empty', function () {
    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    TenantQrStickerSheetSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'template' => QrStickerSheetTemplate::Avery62x89R->value,
        'header_text' => 'Oude tekst',
    ]);

    app(UpdateTenantQrStickerSheetSettingsAction::class)->handle(
        $tenant,
        UpdateTenantQrStickerSheetSettingsData::fromValidated(
            QrStickerSheetTemplate::Avery62x89R,
            [
                'headerText' => '',
                'centerLogo' => QrStickerCenterLogoMode::Tenant->value,
                'cornerTenantLogo' => true,
                'showTenantAddress' => true,
            ],
        ),
        null,
    );

    expect(TenantQrStickerSheetSetting::query()
        ->where('tenant_id', $tenant->id)
        ->where('template', QrStickerSheetTemplate::Avery62x89R->value)
        ->exists())->toBeFalse();
});
