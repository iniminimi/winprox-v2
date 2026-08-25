<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureUnitTranslationSlotsAction;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;
use Illuminate\Support\Facades\Schema;

class CreateUnitAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureUnitTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Location $location, array $data, int $tenantId, ?int $actorUserId = null): Unit
    {
        Tenant::query()->findOrFail($tenantId)->assertCanAddUnits(1);

        $payload = [
            'tenant_id' => $tenantId,
            'location_id' => $location->id,
            'name' => trim((string) $data['name']),
            'description' => $data['description'] ?? null,
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'is_active' => true,
            'public_reports_enabled' => array_key_exists('public_reports_enabled', $data)
                ? (bool) $data['public_reports_enabled']
                : true,
            'allow_reservations' => array_key_exists('allow_reservations', $data)
                ? (bool) $data['allow_reservations']
                : false,
            'allow_unit_checks' => array_key_exists('allow_unit_checks', $data)
                ? (bool) $data['allow_unit_checks']
                : false,
            'require_reporter_contact' => array_key_exists('require_reporter_contact', $data)
                ? (bool) $data['require_reporter_contact']
                : false,
            'require_reporter_email_verification' => array_key_exists('require_reporter_email_verification', $data)
                ? (bool) $data['require_reporter_email_verification']
                : false,
        ];

        if (Schema::hasColumn('units', 'category_id')) {
            $payload['category_id'] = $data['category_id'] ?? null;
        }

        if (Schema::hasColumn('units', 'unit_check_list_id')) {
            $payload['unit_check_list_id'] = $data['unit_check_list_id'] ?? null;
        }

        if (Schema::hasColumn('units', 'external_id') && array_key_exists('external_id', $data)) {
            $externalId = trim((string) ($data['external_id'] ?? ''));
            $payload['external_id'] = $externalId !== '' ? $externalId : null;
        }

        $unit = Unit::create($payload);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'unit.created',
            modelType: Unit::class,
            modelId: (int) $unit->id,
            payload: ['id' => $unit->id, 'name' => $unit->name, 'location_id' => $unit->location_id],
        );

        $unit = $unit->fresh();
        $this->ensureTranslationSlots->handle($unit);

        return $unit;
    }
}
