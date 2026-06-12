<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class UploadOrganisationLogoRequest extends FormRequest
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
            'orgLogo' => ['required', 'file', 'image', 'max:'.self::MAX_KILOBYTES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messagesFor(): array
    {
        $maxMb = (string) (self::MAX_KILOBYTES / 1024);

        return [
            'orgLogo.required' => __('settings.errors.org_logo_required'),
            'orgLogo.uploaded' => __('settings.errors.org_logo_upload_failed', ['max' => $maxMb]),
            'orgLogo.file' => __('settings.errors.org_logo_upload_failed', ['max' => $maxMb]),
            'orgLogo.image' => __('settings.errors.org_logo_image'),
            'orgLogo.max' => __('settings.errors.org_logo_max', ['max' => $maxMb]),
        ];
    }
}
