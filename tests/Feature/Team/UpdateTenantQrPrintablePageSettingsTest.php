<?php

use App\Actions\Team\UpdateTenantQrPrintablePageSettingsAction;
use App\Actions\Team\UploadTenantQrStickerSheetBackgroundAction;
use App\Actions\Team\RemoveTenantQrStickerSheetBackgroundAction;
use App\Data\Team\UpdateTenantQrPrintablePageSettingsData;
use App\Enums\QrPrintablePageBackgroundPreset;
use App\Models\Tenant;
use App\Support\Qr\QrPrintablePageBackground;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Qr\Word\QrStickerWordExporter;
use App\Support\Qr\QrCodePngWriter;
use App\Support\Qr\QrStickerEntry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('saves a shared printable page background preset for a6 a5 and a4', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);

    $updated = app(UpdateTenantQrPrintablePageSettingsAction::class)->handle(
        $tenant,
        new UpdateTenantQrPrintablePageSettingsData(QrPrintablePageBackgroundPreset::Green),
        actorUserId: null,
    );

    $setting = $updated->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
    expect($setting)->not->toBeNull()
        ->and($setting->layout_config['background_preset'] ?? null)->toBe('green');

    foreach ([QrStickerSheetTemplate::A6Print, QrStickerSheetTemplate::A5Print, QrStickerSheetTemplate::A4Print] as $template) {
        $path = QrPrintablePageBackground::absolutePathForTemplate($template, $setting);
        expect($path)->toEndWith('QR_printable_green.png');
    }
});

it('lets a custom printable upload override the preset for all paper sizes', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);

    app(UpdateTenantQrPrintablePageSettingsAction::class)->handle(
        $tenant,
        new UpdateTenantQrPrintablePageSettingsData(QrPrintablePageBackgroundPreset::Multi),
        actorUserId: null,
    );

    $upload = UploadedFile::fake()->image('custom-bg.png', 400, 600);
    $updated = app(UploadTenantQrStickerSheetBackgroundAction::class)->handle(
        $tenant,
        QrStickerSheetTemplate::printablePageSettings(),
        $upload,
        actorUserId: null,
    );

    $setting = $updated->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
    expect($setting?->background_path)->not->toBeNull();

    $path = QrPrintablePageBackground::absolutePathForTemplate(QrStickerSheetTemplate::A4Print, $setting);
    expect($path)->toBe($setting->backgroundAbsolutePath());

    $cleared = app(RemoveTenantQrStickerSheetBackgroundAction::class)->handle(
        $tenant,
        QrStickerSheetTemplate::printablePageSettings(),
        actorUserId: null,
    );
    $after = $cleared->qrStickerSheetSetting(QrStickerSheetTemplate::printablePageSettings());
    expect($after?->background_path)->toBeNull()
        ->and(QrPrintablePageBackground::absolutePathForTemplate(QrStickerSheetTemplate::A6Print, $after))
        ->toEndWith('QR_printable_multi_color.png');
});

it('exports a6 printable docx using the shared green preset', function () {
    if (! QrCodePngWriter::canGenerate()) {
        test()->markTestSkipped('PHP gd or imagick extension required for QR PNG generation.');
    }

    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    app(UpdateTenantQrPrintablePageSettingsAction::class)->handle(
        $tenant,
        new UpdateTenantQrPrintablePageSettingsData(QrPrintablePageBackgroundPreset::Green),
        actorUserId: null,
    );

    $binary = app(QrStickerWordExporter::class)->buildDocxBinaryFromEntries(
        [
            new QrStickerEntry(
                unitLabel: 'Winprox-TEST-00001',
                reportUrl: 'https://example.test/qr/demo',
                stickerNumber: 'Winprox-TEST-00001',
                locationUnitLabel: 'Hal A - Poort 1',
            ),
        ],
        QrStickerSheetTemplate::A6Print,
        null,
        $tenant->fresh()->load('qrStickerSheetSettings'),
    );

    expect($binary)->not->toBe('')
        ->and(substr($binary, 0, 2))->toBe('PK');
});
