<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureLocationTranslationSlotsAction;
use App\Actions\Communication\InvalidateLocationTranslationsOnSourceChangeAction;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;

class UpdateLocationAction
{
    public function __construct(
        private AuditRecorder $audit,
        private InvalidateLocationTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureLocationTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data, ?int $actorUserId = null): Location
    {
        $previousName = (string) $location->name;

        $street = $this->nullableString($data['street'] ?? null);
        $houseNumber = $this->nullableString($data['house_number'] ?? null);
        $postalCode = $this->nullableString($data['postal_code'] ?? null);
        $city = $this->nullableString($data['city'] ?? null);
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '' && $street !== null && $postalCode !== null && $city !== null) {
            $name = $street;
        }

        if ($name === '') {
            $name = trim((string) $location->name);
        }

        $location->update([
            'name' => $name,
            'street' => $street,
            'house_number' => $houseNumber,
            'postal_code' => $postalCode,
            'city' => $city,
            'country_code' => strtoupper((string) ($data['country_code'] ?? $location->country_code ?? 'BE')),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'address' => $this->legacyAddressLine($street, $houseNumber, $postalCode, $city),
        ]);

        $fresh = $location->fresh();

        $this->invalidateTranslations->handle($fresh, $previousName, $actorUserId);
        $this->ensureTranslationSlots->handle($fresh);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'location.updated',
            modelType: Location::class,
            modelId: (int) $fresh->id,
            payload: ['id' => $fresh->id, 'name' => $fresh->name],
        );

        return $fresh;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function legacyAddressLine(?string $street, ?string $houseNumber, ?string $postalCode, ?string $city): ?string
    {
        $lineOne = trim(trim((string) $street).' '.trim((string) $houseNumber));
        $lineTwo = trim(trim((string) $postalCode).' '.trim((string) $city));
        $legacy = trim(collect([$lineOne, $lineTwo])->filter()->implode(', '));

        return $legacy !== '' ? $legacy : null;
    }
}
