<?php

declare(strict_types=1);

namespace App\Data\Ops;

use App\Enums\HardwareDeviceCondition;
use App\Enums\HardwareDeviceStatus;
use App\Enums\HardwareDeviceType;

readonly class UpdateHardwareDeviceData
{
    public function __construct(
        public string $brand,
        public HardwareDeviceType $type,
        public ?string $model,
        public ?string $sku,
        public HardwareDeviceStatus $status,
        public HardwareDeviceCondition $condition,
        public ?string $purchaseRef,
        public ?string $notes,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromValidated(array $input): self
    {
        return new self(
            brand: trim((string) $input['brand']),
            type: HardwareDeviceType::from((string) $input['type']),
            model: self::nullableString($input['model'] ?? null),
            sku: self::nullableString($input['sku'] ?? null),
            status: HardwareDeviceStatus::from((string) $input['status']),
            condition: HardwareDeviceCondition::from((string) $input['condition']),
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
