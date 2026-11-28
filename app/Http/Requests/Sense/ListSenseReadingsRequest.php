<?php

declare(strict_types=1);

namespace App\Http\Requests\Sense;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

class ListSenseReadingsRequest extends FormRequest
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
            'from' => ['required', 'date_format:Y-m-d'],
            'until' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function bounds(string $from, string $until): array
    {
        $validated = validator(
            ['from' => $from, 'until' => $until],
            self::rulesFor(),
        )->validate();

        $start = CarbonImmutable::parse($validated['from'], config('app.timezone'))->startOfDay();
        $endDay = CarbonImmutable::parse($validated['until'], config('app.timezone'))->startOfDay();

        if ($start->diffInDays($endDay) > 30) {
            throw ValidationException::withMessages([
                'until' => __('sense.readings.period_max'),
            ]);
        }

        return [$start, $endDay->endOfDay()];
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    public static function screenIds(): array
    {
        return [
            self::modelId(request()->route('unit')),
            self::modelId(request()->route('clockPoint')),
        ];
    }

    private static function modelId(mixed $value): ?int
    {
        if ($value instanceof Model) {
            return (int) $value->getKey();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
