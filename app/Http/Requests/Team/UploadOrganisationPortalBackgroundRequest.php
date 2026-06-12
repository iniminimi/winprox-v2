<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrganisationPortalBackgroundRequest extends FormRequest
{
    public const MAX_KILOBYTES = 2048;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rules(): array
    {
        return [
            'portalBackground' => ['required', 'file', 'image', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        $maxMb = (string) (self::MAX_KILOBYTES / 1024);

        return [
            'portalBackground.required' => __('settings.errors.portal_background_required'),
            'portalBackground.uploaded' => __('settings.errors.portal_background_upload_failed', ['max' => $maxMb]),
            'portalBackground.file' => __('settings.errors.portal_background_upload_failed', ['max' => $maxMb]),
            'portalBackground.image' => __('settings.errors.portal_background_image'),
            'portalBackground.max' => __('settings.errors.portal_background_max', ['max' => $maxMb]),
        ];
    }
}
