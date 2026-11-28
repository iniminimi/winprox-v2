<?php

declare(strict_types=1);

namespace App\Data\Ops;

final class UsbFlashSighting
{
    public function __construct(
        public string $status,
        public string $typeLabel = '',
        public string $macDisplay = '',
        public string $mac = '',
    ) {}
}
