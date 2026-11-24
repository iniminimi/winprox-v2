<?php

declare(strict_types=1);

namespace App\Data\Ops;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceType;

readonly class RegisterHardwareDeviceData
{
    public function __construct(
        public string $serialNumber,
        public string $brand,
        public HardwareDeviceType $type,
        public ?string $model,
        public ?string $sku,
        public HardwareDeviceCondition $condition,
        public ?string $purchaseRef,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromValidated(array $input): self
    {
        return new self(
            serialNumber: trim((string) $input['serial_number']),
            brand: trim((string) ($input['brand'] ?? 'Seeed')),
            type: HardwareDeviceType::from((string) ($input['type'] ?? HardwareDeviceType::ClockDisplay->value)),
            model: self::nullableString($input['model'] ?? null) ?? 'Indicator D1',
            sku: self::nullableString($input['sku'] ?? null),
            condition: HardwareDeviceCondition::from((string) ($input['condition'] ?? HardwareDeviceCondition::New->value)),
            purchaseRef: self::nullableString($input['purchase_ref'] ?? null),
            notes: self::nullableString($input['notes'] ?? null),
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
