<?php

declare(strict_types=1);

namespace App\Data\Ops;

use App\Support\Ops\HardwareMac;
use InvalidArgumentException;

readonly class BindHardwareDeviceMacData
{
    public function __construct(
        public string $mac,
    ) {
    }

    /** @param array<string, mixed> $input */
    public static function fromValidated(array $input): self
    {
        $mac = HardwareMac::normalize((string) ($input['mac'] ?? ''));
        if (! HardwareMac::isValid($mac)) {
            throw new InvalidArgumentException('mac_invalid');
        }

        return new self(mac: $mac);
    }
}
