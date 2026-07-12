<?php

declare(strict_types=1);

namespace App\Data\Esg;

use App\Enums\EsgIndicatorType;
use Carbon\CarbonImmutable;

readonly class RecordEsgMeasurementData
{
    public function __construct(
        public int $taskId,
        public int $esgIndicatorId,
        public CarbonImmutable $recordedAt,
        public ?float $valueNumeric = null,
        public ?bool $valueBoolean = null,
        public ?string $valueString = null,
        public ?array $valueJson = null,
        public ?int $correctsMeasurementId = null,
    ) {
    }

    /**
     * @param  array{
     *     task_id: int|string,
     *     esg_indicator_id: int|string,
     *     recorded_at: string,
     *     value_numeric?: float|int|string|null,
     *     value_boolean?: bool|int|string|null,
     *     value_string?: ?string,
     *     value_json?: ?array,
     *     corrects_measurement_id?: int|string|null
     * }  $input
     */
    public static function fromValidated(array $input): self
    {
        return new self(
            taskId: (int) $input['task_id'],
            esgIndicatorId: (int) $input['esg_indicator_id'],
            recordedAt: CarbonImmutable::parse($input['recorded_at']),
            valueNumeric: array_key_exists('value_numeric', $input) && $input['value_numeric'] !== null && $input['value_numeric'] !== ''
                ? (float) round((float) $input['value_numeric'])
                : null,
            valueBoolean: array_key_exists('value_boolean', $input) && $input['value_boolean'] !== null
                ? filter_var($input['value_boolean'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                : null,
            valueString: filled($input['value_string'] ?? null) ? (string) $input['value_string'] : null,
            valueJson: is_array($input['value_json'] ?? null) ? $input['value_json'] : null,
            correctsMeasurementId: filled($input['corrects_measurement_id'] ?? null)
                ? (int) $input['corrects_measurement_id']
                : null,
        );
    }

    public function valueForType(EsgIndicatorType $type): mixed
    {
        return match ($type) {
            EsgIndicatorType::Numeric => $this->valueNumeric,
            EsgIndicatorType::Boolean => $this->valueBoolean,
            EsgIndicatorType::String, EsgIndicatorType::Choice => $this->valueString,
            EsgIndicatorType::Json, EsgIndicatorType::MultiChoice => $this->valueJson,
        };
    }

    /**
     * @return array{
     *     value_numeric: ?float,
     *     value_boolean: ?bool,
     *     value_string: ?string,
     *     value_json: ?array
     * }
     */
    public function valueColumnsForInsert(EsgIndicatorType $type): array
    {
        return [
            'value_numeric' => $type === EsgIndicatorType::Numeric && $this->valueNumeric !== null
                ? (float) round($this->valueNumeric)
                : null,
            'value_boolean' => $type === EsgIndicatorType::Boolean ? $this->valueBoolean : null,
            'value_string' => $type === EsgIndicatorType::String || $type === EsgIndicatorType::Choice
                ? $this->valueString
                : null,
            'value_json' => $type === EsgIndicatorType::Json || $type === EsgIndicatorType::MultiChoice
                ? $this->valueJson
                : null,
        ];
    }
}
