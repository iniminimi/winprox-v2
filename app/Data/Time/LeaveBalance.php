<?php

namespace App\Data\Time;

final class LeaveBalance
{
    public function __construct(
        public ?int $entitlementHalves,
        public int $approvedHalves,
        public int $plannedHalves,
    ) {}

    public function remainingHalves(): ?int
    {
        if ($this->entitlementHalves === null) {
            return null;
        }

        return $this->entitlementHalves - $this->approvedHalves - $this->plannedHalves;
    }

    public static function formatHalves(int $halves): string
    {
        $negative = $halves < 0;
        $halves = abs($halves);
        $whole = intdiv($halves, 2);
        $text = ($halves % 2) === 0 ? (string) $whole : $whole.'.5';
        if (in_array(app()->getLocale(), ['nl', 'fr', 'de', 'es', 'it'], true)) {
            $text = str_replace('.', ',', $text);
        }

        return $negative ? '−'.$text : $text;
    }
}
