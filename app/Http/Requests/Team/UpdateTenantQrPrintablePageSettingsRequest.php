<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
use App\Support\Qr\Avery62x89StickerArtworkLayout;
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
            'headerText' => ['nullable', 'string', 'max:'.Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS],
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
            QrPrintablePageStockBackgroundCatalog::presetKeys(),
        );
    }
}
