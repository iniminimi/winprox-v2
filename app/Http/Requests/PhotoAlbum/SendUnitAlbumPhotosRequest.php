<?php

declare(strict_types=1);

namespace App\Http\Requests\PhotoAlbum;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class SendUnitAlbumPhotosRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('send', \App\Models\UnitAlbumPhoto::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(): array
    {
        return [
            'unit_id' => ['required', 'integer'],
            'photo_ids' => ['required', 'array', 'min:1', 'max:'.max(1, (int) config('unit_photo_album.max_photos_per_day', 24))],
            'photo_ids.*' => ['integer', 'distinct'],
            'email' => ['required', 'string', 'email:rfc', 'max:255'],
            'description' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function validationMessages(): array
    {
        return [
            'photo_ids.required' => __('photo_album.errors.photos_required'),
            'photo_ids.min' => __('photo_album.errors.photos_required'),
            'email.required' => __('photo_album.errors.email_required'),
            'email.email' => __('photo_album.errors.email_invalid'),
            'description.max' => __('photo_album.errors.description_max'),
        ];
    }
}
