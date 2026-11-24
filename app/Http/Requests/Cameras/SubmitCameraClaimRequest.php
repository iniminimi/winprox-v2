<?php

declare(strict_types=1);

namespace App\Http\Requests\Cameras;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCameraClaimRequest extends FormRequest
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
        return [
            'code' => ['required', 'string', 'max:9'],
            'device_hint' => ['required', 'string', 'max:64'],
        ];
    }
}
