<?php

declare(strict_types=1);

namespace App\Http\Requests\PhotoAlbum;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Albumfoto's vanuit het Clock Point-portaal (Livewire-veldnamen).
 */
class UploadUnitAlbumPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::portalRuleSet();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function portalRuleSet(): array
    {
        return [
            'albumUnitId' => ['required', 'integer'],
            'albumPhotos' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('unit_photo_album.max_photos_per_day', 24))],
            'albumPhotos.*' => ['image', 'max:10240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function portalValidationMessages(): array
    {
        return [
            'albumUnitId.required' => __('time.portal.album.errors.unit_required'),
            'albumPhotos.required' => __('time.portal.album.errors.photos_required'),
            'albumPhotos.min' => __('time.portal.album.errors.photos_required'),
            'albumPhotos.max' => __('time.portal.album.errors.day_full'),
            'albumPhotos.*.image' => __('portal.report.errors.photos_image'),
            'albumPhotos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
