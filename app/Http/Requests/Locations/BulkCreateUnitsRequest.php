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
     * Shared portal flags for a whole bulk (same fields as StoreUnitRequest, minus unique-per-unit).
     *
     * @return array<string, array<int, mixed>>
     */
    public static function portalRuleSet(?int $tenantId = null): array
    {
        $unitRules = StoreUnitRequest::ruleSet(null, null, $tenantId);

        return [
            'category_id' => $unitRules['category_id'],
            'unit_check_list_id' => $unitRules['unit_check_list_id'],
            'public_reports_enabled' => $unitRules['public_reports_enabled'],
            'allow_reservations' => $unitRules['allow_reservations'],
            'allow_unit_checks' => $unitRules['allow_unit_checks'],
            'allow_unit_measurements' => $unitRules['allow_unit_measurements'],
            'measure_field_ids' => $unitRules['measure_field_ids'],
            'measure_field_ids.*' => $unitRules['measure_field_ids.*'],
            'require_reporter_contact' => $unitRules['require_reporter_contact'],
            'require_reporter_email_verification' => $unitRules['require_reporter_email_verification'],
            'latitude' => $unitRules['latitude'],
            'longitude' => $unitRules['longitude'],
        ];
    }

    /**
     * Livewire property map for {@see portalRuleSet()}.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function livewirePortalRuleSet(?int $tenantId = null): array
    {
        $map = [
            'category_id' => 'bulkCategoryId',
            'unit_check_list_id' => 'bulkCheckListId',
            'public_reports_enabled' => 'bulkPublicReportsEnabled',
            'allow_reservations' => 'bulkAllowReservations',
            'allow_unit_checks' => 'bulkAllowUnitChecks',
            'allow_unit_measurements' => 'bulkAllowUnitMeasurements',
            'measure_field_ids' => 'bulkMeasureFieldIds',
            'measure_field_ids.*' => 'bulkMeasureFieldIds.*',
            'require_reporter_contact' => 'bulkRequireReporterContact',
            'require_reporter_email_verification' => 'bulkRequireReporterEmailVerification',
            'latitude' => 'bulkLatitude',
            'longitude' => 'bulkLongitude',
        ];

        $rules = self::portalRuleSet($tenantId);
        $mapped = [];
        foreach ($map as $from => $to) {
            $mapped[$to] = $rules[$from];
        }

        return $mapped;
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
