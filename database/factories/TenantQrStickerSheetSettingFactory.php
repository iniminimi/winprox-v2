<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantQrStickerSheetSetting;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantQrStickerSheetSetting>
 */
class TenantQrStickerSheetSettingFactory extends Factory
{
    protected $model = TenantQrStickerSheetSetting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'template' => QrStickerSheetTemplate::Avery62x89R->value,
            'header_text' => null,
            'background_path' => null,
            'layout_config' => null,
        ];
    }

    public function forTemplate(QrStickerSheetTemplate $template): static
    {
        return $this->state(fn (): array => [
            'template' => $template->value,
        ]);
    }
}
