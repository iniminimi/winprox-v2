<?php

declare(strict_types=1);

use App\Actions\Team\UpdateTenantQrStickerSheetSettingsAction;
use App\Data\Team\UpdateTenantQrStickerSheetSettingsData;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
                'tenantLogo' => QrStickerTenantLogoPlacement::TopRight->value,
                'tenantAddress' => QrStickerTenantLogoPlacement::None->value,
            ],
        ),
        null,
    );

    $setting = TenantQrStickerSheetSetting::query()
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($setting?->layout_config)->toBe([
        'tenant_logo' => 'top_right',
        'tenant_address' => 'none',
    ]);
});

it('preserves Avery 62x89 sticker background when saving layout settings', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $backgroundPath = UploadedFile::fake()->image('sticker-bg.png', 732, 1051)->store(
        'tenant-qr-sticker-backgrounds/'.$tenant->id.'/avery_62x89_r',
        'public',
    );

    TenantQrStickerSheetSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'template' => QrStickerSheetTemplate::Avery62x89R->value,
        'background_path' => $backgroundPath,
    ]);

    app(UpdateTenantQrStickerSheetSettingsAction::class)->handle(
        $tenant,
        UpdateTenantQrStickerSheetSettingsData::fromValidated(
            QrStickerSheetTemplate::Avery62x89R,
            [
                'headerText' => 'Scan deze QR-code en kom terecht in ons Portaal.',
                'tenantLogo' => QrStickerTenantLogoPlacement::BottomRight->value,
                'tenantAddress' => QrStickerTenantLogoPlacement::BottomLeft->value,
            ],
        ),
        null,
    );

    $setting = TenantQrStickerSheetSetting::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($setting?->background_path)->toBe($backgroundPath)
        ->and($setting?->header_text)->toBe('Scan deze QR-code en kom terecht in ons Portaal.');
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
                'tenantLogo' => QrStickerTenantLogoPlacement::BottomRight->value,
                'tenantAddress' => QrStickerTenantLogoPlacement::BottomLeft->value,
            ],
        ),
        null,
    );

    expect(TenantQrStickerSheetSetting::query()
        ->where('tenant_id', $tenant->id)
        ->where('template', QrStickerSheetTemplate::Avery62x89R->value)
        ->exists())->toBeFalse();
});

it('keeps Avery 62x89 sticker background when header text is cleared', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $backgroundPath = UploadedFile::fake()->image('sticker-bg.png', 732, 1051)->store(
        'tenant-qr-sticker-backgrounds/'.$tenant->id.'/avery_62x89_r',
        'public',
    );

    TenantQrStickerSheetSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'template' => QrStickerSheetTemplate::Avery62x89R->value,
        'header_text' => 'Oude tekst',
        'background_path' => $backgroundPath,
    ]);

    app(UpdateTenantQrStickerSheetSettingsAction::class)->handle(
        $tenant,
        UpdateTenantQrStickerSheetSettingsData::fromValidated(
            QrStickerSheetTemplate::Avery62x89R,
            [
                'headerText' => '',
                'tenantLogo' => QrStickerTenantLogoPlacement::BottomRight->value,
                'tenantAddress' => QrStickerTenantLogoPlacement::BottomLeft->value,
            ],
        ),
        null,
    );

    $setting = TenantQrStickerSheetSetting::query()
        ->withoutGlobalScope('tenant')
        ->where('tenant_id', $tenant->id)
        ->first();

    expect($setting)->not->toBeNull()
        ->and($setting->background_path)->toBe($backgroundPath)
        ->and($setting->header_text)->toBeNull();
});
