<?php

namespace App\Http\Requests\Locations;

use App\Actions\Locations\BulkCreateUnitsAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BulkCreateUnitsRequest extends FormRequest
{
    public const MAX_UNITS = BulkCreateUnitsAction::MAX_UNITS;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public static function ruleSet(): array
    {
        return [
            'ranges' => ['required', 'array', 'min:1'],
            'ranges.*.start' => ['required', 'string', 'max:20', 'regex:/^\d+$/'],
            'ranges.*.count' => ['required', 'integer', 'min:1', 'max:'.self::MAX_UNITS],
            'ranges.*.padding' => ['nullable', 'integer', 'min:1', 'max:20'],
            'ranges.*.prefix' => ['nullable', 'string', 'max:30'],
            'ranges.*.suffix' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * Livewire property map: bulkRanges.* → ranges.*
     *
     * @return array<string, array<int, mixed>>
     */
    public static function livewireRuleSet(): array
    {
        $rules = [];
        foreach (self::ruleSet() as $key => $rule) {
            $rules[str_replace('ranges', 'bulkRanges', $key)] = $rule;
        }

        return $rules;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return self::ruleSet();
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var list<array<string, mixed>> $ranges */
            $ranges = array_values($this->input('ranges', []));
            self::assertRangesConsistent($validator, $ranges, 'ranges');
        });
    }

    /**
     * Shared after-checks for HTTP and Livewire.
     *
     * @param  list<array<string, mixed>>  $ranges
     */
    public static function assertRangesConsistent(Validator $validator, array $ranges, string $errorKey): void
    {
        $total = 0;

        foreach ($ranges as $index => $range) {
            $start = trim((string) ($range['start'] ?? ''));
            $paddingRaw = $range['padding'] ?? null;
            if ($paddingRaw !== null && $paddingRaw !== '' && (int) $paddingRaw < strlen($start)) {
                $validator->errors()->add(
                    $errorKey.'.'.$index.'.padding',
                    __('locations.bulk.errors.padding'),
                );
            }

            $total += (int) ($range['count'] ?? 0);
        }

        if ($total > self::MAX_UNITS) {
            $validator->errors()->add($errorKey, __('locations.bulk.errors.too_many'));

            return;
        }

        if ($validator->errors()->isNotEmpty()) {
            return;
        }

        $action = app(BulkCreateUnitsAction::class);
        $names = $action->namesFromRanges($ranges);
        if ($action->duplicateNames($names) !== []) {
            $validator->errors()->add($errorKey, __('locations.bulk.errors.duplicates'));
        }
    }
}
