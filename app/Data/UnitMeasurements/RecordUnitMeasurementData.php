<?php

declare(strict_types=1);

namespace App\Data\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;
use App\Enums\UnitMeasurementSource;
use Carbon\CarbonImmutable;

readonly class RecordUnitMeasurementData
{
    public function __construct(
        public int $unitMeasureFieldId,
        public UnitMeasurementSource $source,
        public CarbonImmutable $recordedAt,
        public ?float $valueNumeric = null,
        public ?bool $valueBoolean = null,
        public ?string $valueString = null,
    ) {}

    /**
     * @param  array{
     *     unit_measure_field_id: int|string,
     *     source?: string,
     *     recorded_at: string,
     *     value_numeric?: float|int|string|null,
     *     value_boolean?: bool|int|string|null,
     *     value_string?: ?string
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            unitMeasureFieldId: (int) $input['unit_measure_field_id'],
            source: UnitMeasurementSource::from((string) ($input['source'] ?? UnitMeasurementSource::Api->value)),
            recordedAt: CarbonImmutable::parse($input['recorded_at']),
            valueNumeric: array_key_exists('value_numeric', $input) && $input['value_numeric'] !== null && $input['value_numeric'] !== ''
                ? (float) $input['value_numeric']
                : null,
            valueBoolean: array_key_exists('value_boolean', $input) && $input['value_boolean'] !== null
                ? filter_var($input['value_boolean'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            valueString: filled($input['value_string'] ?? null) ? (string) $input['value_string'] : null,
        );
    }

    public function valueForType(UnitMeasureFieldType $type): mixed
    {
        return match ($type) {
            UnitMeasureFieldType::Numeric => $this->valueNumeric,
            UnitMeasureFieldType::Boolean => $this->valueBoolean,
            UnitMeasureFieldType::String, UnitMeasureFieldType::Choice => $this->valueString,
        };
    }

    /**
     * @return array{
     *     value_numeric: ?float,
     *     value_boolean: ?bool,
     *     value_string: ?string
     * }
     */
    public function valueColumnsForInsert(UnitMeasureFieldType $type): array
    {
        return [
            'value_numeric' => $type === UnitMeasureFieldType::Numeric ? $this->valueNumeric : null,
            'value_boolean' => $type === UnitMeasureFieldType::Boolean ? $this->valueBoolean : null,
            'value_string' => $type === UnitMeasureFieldType::String || $type === UnitMeasureFieldType::Choice
                ? $this->valueString
                : null,
        ];
    }
}
