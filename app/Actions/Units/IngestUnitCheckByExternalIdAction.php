<?php

declare(strict_types=1);

namespace App\Actions\Units;

use App\Data\Units\RecordUnitCheckData;
use App\Enums\UnitCheckSource;
use App\Models\Unit;
use App\Models\UnitCheck;
use Illuminate\Validation\ValidationException;

/**
 * Inbound unit check from external facility software (IWMS / CMMS / ERP),
 * resolved by the unit's external_id mapping.
 */
class IngestUnitCheckByExternalIdAction
{
    public function __construct(
        private RecordUnitCheckAction $recordUnitCheck,
    ) {}

    /**
     * @param  array{
     *     external_unit_id: string,
     *     result: string,
     *     checked_at: string,
     *     latitude?: float|int|string|null,
     *     longitude?: float|int|string|null,
     *     task_id?: int|null,
     *     issue_id?: int|null,
     *     checklist_items?: list<string>|null,
     *     external_id?: string|null
     * }  $validated
     */
    public function handle(array $validated, int $tenantId, ?int $actorUserId = null): UnitCheck
    {
        $externalUnitId = trim((string) $validated['external_unit_id']);

        $unit = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalUnitId)
            ->first();

        if ($unit === null) {
            throw ValidationException::withMessages([
                'external_unit_id' => [__('unit_checks.validation.external_unit_not_found')],
            ]);
        }

        if (! $unit->allowsUnitChecks()) {
            throw ValidationException::withMessages([
                'external_unit_id' => [__('unit_checks.validation.unit_checks_disabled')],
            ]);
        }

        $payload = $validated;
        $payload['source'] = UnitCheckSource::External->value;

        return $this->recordUnitCheck->handle(
            unit: $unit,
            data: RecordUnitCheckData::fromValidated($payload),
            tenantId: $tenantId,
            worker: null,
            actorUserId: $actorUserId,
        );
    }
}
