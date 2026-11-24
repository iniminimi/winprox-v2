<?php

declare(strict_types=1);

namespace App\Http\Requests\Sense;

use Illuminate\Foundation\Http\FormRequest;

class RecordSenseReadingRequest extends FormRequest
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
        $reading = config('sense.reading');

        return [
            'temperature' => ['required', 'numeric', 'between:'.$reading['temperature']['min'].','.$reading['temperature']['max']],
            'humidity' => ['required', 'numeric', 'between:'.$reading['humidity']['min'].','.$reading['humidity']['max']],
            'co2' => ['required', 'integer', 'between:'.$reading['co2']['min'].','.$reading['co2']['max']],
            'voc' => ['required', 'integer', 'between:'.$reading['voc']['min'].','.$reading['voc']['max']],
            'recorded_at' => ['required', 'date'],
        ];
    }
}
