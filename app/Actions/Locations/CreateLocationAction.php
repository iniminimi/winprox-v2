<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureLocationTranslationSlotsAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Schema;

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
        Tenant::query()->findOrFail($tenantId)->assertCanAddLocations(1);

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            $name = trim((string) ($data['street'] ?? '')) ?: __('locations.default_name');
        }

        $payload = [
            'tenant_id' => $tenantId,
            'name' => $name,
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'street' => $this->nullableString($data['street'] ?? null),
            'house_number' => $this->nullableString($data['house_number'] ?? null),
            'postal_code' => $this->nullableString($data['postal_code'] ?? null),
            'city' => $this->nullableString($data['city'] ?? null),
            'country_code' => strtoupper((string) ($data['country_code'] ?? 'BE')),
            'notes' => $this->nullableString($data['notes'] ?? null),
            'contractual_relationship_reference' => $this->nullableString($data['contractual_relationship_reference'] ?? null),
            'latitude' => self::nullableFloat($data['latitude'] ?? null),
            'longitude' => self::nullableFloat($data['longitude'] ?? null),
            'address' => null,
            'is_active' => true,
        ];

        if (Schema::hasColumn('locations', 'import_batch_id') && array_key_exists('import_batch_id', $data)) {
            $payload['import_batch_id'] = $data['import_batch_id'];
        }

        $location = Location::create($payload);

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

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
