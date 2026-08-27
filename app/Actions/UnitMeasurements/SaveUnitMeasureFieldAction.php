<?php

declare(strict_types=1);

namespace App\Actions\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;
use App\Models\UnitMeasureField;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaveUnitMeasureFieldAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{
     *     name: string,
     *     type: string,
     *     unit_of_measure?: ?string,
     *     min_value?: float|int|string|null,
     *     max_value?: float|int|string|null,
     *     options?: list<string>|null,
     *     is_active?: bool
     * }  $data
     */
    public function handle(
        array $data,
        int $tenantId,
        ?UnitMeasureField $field = null,
        ?int $actorUserId = null,
    ): UnitMeasureField {
        $type = UnitMeasureFieldType::from((string) $data['type']);
        $name = trim((string) $data['name']);
        $unitOfMeasure = $type->usesUnitOfMeasure()
            ? (filled($data['unit_of_measure'] ?? null) ? trim((string) $data['unit_of_measure']) : null)
            : null;
        $options = $type->usesOptionList()
            ? $this->normalizeOptions($data['options'] ?? [])
            : null;

        if ($type->usesOptionList() && ($options === null || $options === [])) {
            throw ValidationException::withMessages([
                'options' => [__('unit_measurements.errors.options_required')],
            ]);
        }

        if ($field !== null && $field->exists && $field->type !== $type && $field->hasMeasurements()) {
            throw ValidationException::withMessages([
                'type' => [__('unit_measurements.errors.type_locked')],
            ]);
        }

        if ($field !== null && $field->exists && $type->usesOptionList()) {
            $this->assertOptionsStillCoverInUse($field, $options ?? []);
        }

        $minValue = $type === UnitMeasureFieldType::Numeric && array_key_exists('min_value', $data) && $data['min_value'] !== null && $data['min_value'] !== ''
            ? (float) $data['min_value']
            : null;
        $maxValue = $type === UnitMeasureFieldType::Numeric && array_key_exists('max_value', $data) && $data['max_value'] !== null && $data['max_value'] !== ''
            ? (float) $data['max_value']
            : null;

        if ($minValue !== null && $maxValue !== null && $minValue > $maxValue) {
            throw ValidationException::withMessages([
                'max_value' => [__('unit_measurements.errors.min_max_order')],
            ]);
        }

        Validator::make(
            ['name' => $name],
            [
                'name' => [
                    'required',
                    'string',
                    'max:120',
                    Rule::unique('unit_measure_fields', 'name')
                        ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                        ->ignore($field?->id),
                ],
            ],
            [
                'name.unique' => __('unit_measurements.errors.name_unique'),
            ],
        )->validate();

        $payload = [
            'tenant_id' => $tenantId,
            'name' => $name,
            'type' => $type,
            'unit_of_measure' => $unitOfMeasure,
            'min_value' => $minValue,
            'max_value' => $maxValue,
            'options' => $options,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
        ];

        if ($field === null) {
            $field = UnitMeasureField::query()->create($payload);
            $action = 'unit_measure_field.created';
        } else {
            $field->update($payload);
            $field = $field->fresh();
            $action = 'unit_measure_field.updated';
        }

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: $action,
            modelType: UnitMeasureField::class,
            modelId: (int) $field->id,
            payload: [
                'id' => $field->id,
                'name' => $field->name,
                'type' => $field->type->value,
            ],
        );

        return $field;
    }

    /**
     * @param  list<mixed>|null  $options
     * @return list<string>
     */
    private function normalizeOptions(?array $options): array
    {
        if ($options === null) {
            return [];
        }

        $normalized = [];
        foreach ($options as $option) {
            if (! is_string($option) && ! is_numeric($option)) {
                continue;
            }
            $trimmed = trim((string) $option);
            if ($trimmed !== '' && ! in_array($trimmed, $normalized, true)) {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

    /**
     * @param  list<string>  $options
     */
    private function assertOptionsStillCoverInUse(UnitMeasureField $field, array $options): void
    {
        foreach ($field->choiceOptionsWithMeasurements() as $inUse) {
            if (! in_array($inUse, $options, true)) {
                throw ValidationException::withMessages([
                    'options' => [__('unit_measurements.errors.option_in_use', ['option' => $inUse])],
                ]);
            }
        }
    }
}
