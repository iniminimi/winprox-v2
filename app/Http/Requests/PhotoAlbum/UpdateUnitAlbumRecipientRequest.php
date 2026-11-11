<?php

declare(strict_types=1);

namespace App\Http\Requests\PhotoAlbum;

use App\Support\Validation\TextDescriptionLimits;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUnitAlbumRecipientRequest extends FormRequest
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
        return self::rulesFor();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function rulesFor(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:'.TextDescriptionLimits::MAX],
        ];
    }
}
