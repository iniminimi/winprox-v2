<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\QrPrintablePageStockBackgroundCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTenantQrPrintablePageSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(): array
    {
        return [
            'preset' => ['required', 'string', Rule::in(self::allowedPresetKeys())],
            'tenantLogo' => ['required', 'string', Rule::enum(QrStickerTenantLogoPlacement::class)],
            'tenantAddress' => ['required', 'string', Rule::enum(QrStickerTenantLogoPlacement::class)],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return list<string>
     */
    public static function allowedPresetKeys(): array
    {
        return array_merge(
            array_map(
                static fn (QrPrintablePageBackgroundPreset $preset): string => $preset->value,
                QrPrintablePageBackgroundPreset::cases(),
            ),
            QrPrintablePageStockBackgroundCatalog::presetKeys(),
        );
    }
}
