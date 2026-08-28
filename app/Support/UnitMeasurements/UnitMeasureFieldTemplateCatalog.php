<?php

declare(strict_types=1);

namespace App\Support\UnitMeasurements;

use App\Enums\UnitMeasureFieldType;
use InvalidArgumentException;

final class UnitMeasureFieldTemplateCatalog
{
    /** @var list<string> */
    public const KEYS = [
        'odometer',
        'temperature',
        'engine_hours',
        'status',
        'fuel_level_pct',
    ];

    public static function isValidKey(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function menuItems(): array
    {
        $items = [];

        foreach (self::KEYS as $key) {
            $items[] = [
                'key' => $key,
                'label' => (string) __("unit_measurements.fields.templates.{$key}.name"),
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     name: string,
     *     type: string,
     *     unitOfMeasure: string,
     *     minValue: ?string,
     *     maxValue: ?string,
     *     choiceOptions: list<string>
     * }
     */
    public static function formDefaults(string $key): array
    {
        if (! self::isValidKey($key)) {
            throw new InvalidArgumentException('Unknown unit measure field template: '.$key);
        }

        return match ($key) {
            'odometer' => [
                'name' => (string) __('unit_measurements.fields.templates.odometer.name'),
                'type' => UnitMeasureFieldType::Numeric->value,
                'unitOfMeasure' => 'km',
                'minValue' => '0',
                'maxValue' => null,
                'choiceOptions' => ['', ''],
            ],
            'temperature' => [
                'name' => (string) __('unit_measurements.fields.templates.temperature.name'),
                'type' => UnitMeasureFieldType::Numeric->value,
                'unitOfMeasure' => '°C',
                'minValue' => '-30',
                'maxValue' => '60',
                'choiceOptions' => ['', ''],
            ],
            'engine_hours' => [
                'name' => (string) __('unit_measurements.fields.templates.engine_hours.name'),
                'type' => UnitMeasureFieldType::Numeric->value,
                'unitOfMeasure' => 'h',
                'minValue' => '0',
                'maxValue' => null,
                'choiceOptions' => ['', ''],
            ],
            'status' => [
                'name' => (string) __('unit_measurements.fields.templates.status.name'),
                'type' => UnitMeasureFieldType::Choice->value,
                'unitOfMeasure' => '',
                'minValue' => null,
                'maxValue' => null,
                'choiceOptions' => self::translatedOptions('status', ['ok', 'defect', 'maintenance', 'out_of_service']),
            ],
            'fuel_level_pct' => [
                'name' => (string) __('unit_measurements.fields.templates.fuel_level_pct.name'),
                'type' => UnitMeasureFieldType::Numeric->value,
                'unitOfMeasure' => '%',
                'minValue' => '0',
                'maxValue' => '100',
                'choiceOptions' => ['', ''],
            ],
        };
    }

    /**
     * @param  list<string>  $optionKeys
     * @return list<string>
     */
    private static function translatedOptions(string $templateKey, array $optionKeys): array
    {
        $options = [];

        foreach ($optionKeys as $optionKey) {
            $options[] = (string) __("unit_measurements.fields.templates.{$templateKey}.options.{$optionKey}");
        }

        return $options;
    }
}
