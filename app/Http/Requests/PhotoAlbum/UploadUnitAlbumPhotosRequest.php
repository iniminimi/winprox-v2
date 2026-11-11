<?php

declare(strict_types=1);

namespace App\Http\Requests\PhotoAlbum;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Albumfoto's vanuit het unit-QR-portaal (Livewire-veldnamen).
 * De unit is de gescande QR; er is geen unitkeuze.
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
            'albumPhotos.required' => __('portal.album.errors.photos_required'),
            'albumPhotos.min' => __('portal.album.errors.photos_required'),
            'albumPhotos.max' => __('portal.album.errors.day_full', ['max' => (int) config('unit_photo_album.max_photos_per_day', 24)]),
            'albumPhotos.*.image' => __('portal.report.errors.photos_image'),
            'albumPhotos.*.max' => __('portal.report.errors.photos_size'),
        ];
    }
}
