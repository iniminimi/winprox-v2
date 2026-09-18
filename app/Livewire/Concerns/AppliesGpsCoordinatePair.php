<?php

namespace App\Livewire\Concerns;

use App\Support\Geo\GpsCoordinatePair;

trait AppliesGpsCoordinatePair
{
    public function applyLocationGpsPair(string $text): bool
    {
        return $this->fillGpsPair($text, 'locationFormLatitude', 'locationFormLongitude');
    }

    protected function fillGpsPair(string $text, string $latProp, string $lngProp): bool
    {
        $text = trim($text);
        if ($text === '') {
            $this->{$latProp} = '';
            $this->{$lngProp} = '';

            return true;
        }

        $parsed = GpsCoordinatePair::tryParse($text);
        if ($parsed === null) {
            return false;
        }

        $this->{$latProp} = $parsed[0];
        $this->{$lngProp} = $parsed[1];

        return true;
    }
}
