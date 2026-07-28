<?php

declare(strict_types=1);

namespace App\Http\Requests\Time;

use App\Support\Qr\QrStickerSheetTemplate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class DownloadClockPointQrPackRequest extends FormRequest
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
            'template' => [
                'required',
                'string',
                Rule::in([
                    QrStickerSheetTemplate::A6Print->value,
                    QrStickerSheetTemplate::A5Print->value,
                    QrStickerSheetTemplate::A4Print->value,
                ]),
            ],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::rulesFor();
    }

    public function template(): QrStickerSheetTemplate
    {
        return QrStickerSheetTemplate::from((string) $this->validated('template'));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response($validator->errors()->first('template') ?: 'Invalid template.', 422),
        );
    }
}
