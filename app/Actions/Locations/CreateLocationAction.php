<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureLocationTranslationSlotsAction;
use App\Models\Location;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Str;

class CreateLocationAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureLocationTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data, int $tenantId, ?int $actorUserId = null): Location
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($data['street'] ?? '')) ?: __('locations.default_name');
        }

        $location = Location::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'street' => $this->nullableString($data['street'] ?? null),
            'house_number' => $this->nullableString($data['house_number'] ?? null),
            'postal_code' => $this->nullableString($data['postal_code'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'country_code' => strtoupper((string) ($data['country_code'] ?? 'BE')),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'address' => null,
            'location_qr_token' => Str::lower(Str::random(40)),
            'is_active' => true,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'location.created',
            modelType: Location::class,
            modelId: (int) $location->id,
            payload: ['id' => $location->id, 'name' => $location->name],
        );

        $location = $location->fresh();
        $this->ensureTranslationSlots->handle($location);

        return $location;
    }

    private function nullableString(mixed $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }
}
