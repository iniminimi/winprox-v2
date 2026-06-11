<?php

declare(strict_types=1);

use App\Actions\Team\RemoveTenantQrStickerSheetBackgroundAction;
use App\Actions\Team\UploadTenantQrStickerSheetBackgroundAction;
use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrStickerBackground;
use App\Support\Qr\QrStickerSheetTemplate;
use App\Support\Tenancy;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

afterEach(fn () => Tenancy::forget());

it('stores a custom Avery 62x89 sticker background for the tenant', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $background = UploadedFile::fake()->image('sticker-bg.png', 732, 1051);

    $updatedTenant = app(UploadTenantQrStickerSheetBackgroundAction::class)->handle(
        $tenant,
        QrStickerSheetTemplate::Avery62x89R,
        $background,
        null,
    );

    $setting = $updatedTenant->qrStickerSheetSetting(QrStickerSheetTemplate::Avery62x89R);

    expect($setting)->not->toBeNull()
        ->and($setting->background_path)->not->toBeNull()
        ->and(Storage::disk('public')->exists((string) $setting->background_path))->toBeTrue();

    expect(QrStickerBackground::absolutePathForTemplate(
        QrStickerSheetTemplate::Avery62x89R,
        $setting,
    ))->not->toBe(QrStickerBackground::defaultAvery62x89AbsolutePath());
});

it('removes a custom Avery 62x89 sticker background', function () {
    Storage::fake('public');

    $tenant = Tenant::factory()->create(['trial_ends_at' => now()->addDays(5)]);
    Tenancy::actAs($tenant->id);

    $path = UploadedFile::fake()->image('sticker-bg.png')->store(
        'tenant-qr-sticker-backgrounds/'.$tenant->id.'/avery_62x89_r',
        'public',
    );

    TenantQrStickerSheetSetting::factory()->create([
        'tenant_id' => $tenant->id,
        'template' => QrStickerSheetTemplate::Avery62x89R->value,
        'background_path' => $path,
    ]);

    app(RemoveTenantQrStickerSheetBackgroundAction::class)->handle(
        $tenant,
        QrStickerSheetTemplate::Avery62x89R,
        null,
    );

    expect(Storage::disk('public')->exists($path))->toBeFalse()
        ->and(TenantQrStickerSheetSetting::query()->where('tenant_id', $tenant->id)->exists())->toBeFalse();
});
