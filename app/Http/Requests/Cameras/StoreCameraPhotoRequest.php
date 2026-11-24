<?php

declare(strict_types=1);

namespace App\Http\Requests\Cameras;

use Illuminate\Foundation\Http\FormRequest;

class StoreCameraPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = max(1, (int) config('camera.max_upload_kb', 8192));

        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpeg,jpg,png,webp', 'max:'.$max],
        ];
    }
}
