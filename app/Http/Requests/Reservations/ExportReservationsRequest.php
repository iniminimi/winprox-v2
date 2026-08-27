<?php

declare(strict_types=1);

namespace App\Http\Requests\Reservations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportReservationsRequest extends FormRequest
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
        return self::rulesFor();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rulesFor(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['upcoming', 'pending', 'confirmed', 'past', 'all'])],
            'location' => ['nullable', 'integer', 'min:1'],
            'q' => ['nullable', 'string', 'max:200'],
        ];
    }
}
