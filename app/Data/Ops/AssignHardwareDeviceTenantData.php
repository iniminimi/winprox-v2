<?php

declare(strict_types=1);

namespace App\Data\Ops;

readonly class AssignHardwareDeviceTenantData
{
    public function __construct(
        public ?int $tenantId,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromValidated(array $input): self
    {
        $raw = $input['tenant_id'] ?? null;
        if ($raw === null || $raw === '') {
            return new self(tenantId: null);
        }

        return new self(tenantId: (int) $raw);
    }
}
