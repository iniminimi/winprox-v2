<?php

namespace App\Actions\Locations;

use App\Models\Location;

class UpdateLocationAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data): Location
    {
        $name = trim((string) ($data['name'] ?? $location->name));

        $location->update([
            'name' => $name !== '' ? $name : $location->name,
            'street' => $this->nullableString($data['street'] ?? null),
            'house_number' => $this->nullableString($data['house_number'] ?? null),
            'postal_code' => $this->nullableString($data['postal_code'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'country_code' => strtoupper((string) ($data['country_code'] ?? $location->country_code ?? 'BE')),
            'notes' => $this->nullableString($data['notes'] ?? null),
        ]);

        return $location->fresh();
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
