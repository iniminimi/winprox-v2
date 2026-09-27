<?php

namespace App\Livewire\Concerns;

use App\Support\Locations\GoogleMapsAddressLine;

/**
 * Paste a Google Maps "Straat 1, 1234 Plaats" line into address forms:
 * splits into the locationForm* fields.
 */
trait AppliesPastedAddress
{
    public function applyLocationAddressPaste(string $text): bool
    {
        $parsed = GoogleMapsAddressLine::tryParse($text);
        if ($parsed === null) {
            return false;
        }

        $this->locationFormStreet = $parsed['street'];
        $this->locationFormHouseNumber = $parsed['house_number'];
        $this->locationFormPostalCode = $parsed['postal_code'];
        $this->locationFormCity = $parsed['city'];

        return true;
    }
}
