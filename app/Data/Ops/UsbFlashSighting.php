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

    public function message(): string
    {
        return match ($this->status) {
            'ok' => __('ops.devices.flash_ok', ['type' => $this->typeLabel, 'mac' => $this->macDisplay]),
            'failed' => __('ops.devices.flash_failed', ['type' => $this->typeLabel, 'mac' => $this->macDisplay]),
            'found' => __('ops.devices.flash_confirm', ['type' => $this->typeLabel, 'mac' => $this->macDisplay]),
            default => __('ops.devices.flash_'.$this->status),
        };
    }
}
