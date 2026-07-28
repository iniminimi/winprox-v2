<?php

declare(strict_types=1);

namespace App\Http\Requests\Team;

use App\Enums\QrPrintablePageBackgroundPreset;
use App\Enums\QrStickerTenantLogoPlacement;
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
            'preset' => ['required', 'string', Rule::enum(QrPrintablePageBackgroundPreset::class)],
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
}
