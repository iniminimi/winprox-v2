<?php

namespace App\Http\Requests\Team;

use App\Support\Qr\Avery62x89StickerArtworkLayout;
use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantQrStickerSheetSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(QrStickerSheetTemplate $template): array
    {
        return match ($template) {
            QrStickerSheetTemplate::Avery62x89R => [
                'headerText' => ['nullable', 'string', 'max:'.Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS],
            ],
            default => [
                'headerText' => ['nullable', 'string', 'max:160'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(QrStickerSheetTemplate $template): array
    {
        return match ($template) {
            QrStickerSheetTemplate::Avery62x89R => [
                'headerText.max' => __('settings.errors.qr_sticker_header_max', [
                    'max' => Avery62x89StickerArtworkLayout::HEADER_TEXT_MAX_CHARS,
                ]),
            ],
            default => [],
        };
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor(QrStickerSheetTemplate::Avery62x89R);
    }
}
