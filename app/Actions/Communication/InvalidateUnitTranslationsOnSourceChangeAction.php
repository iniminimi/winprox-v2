<?php

namespace App\Actions\Communication;

use App\Enums\UnitTranslationStatus;
use App\Models\Unit;
use App\Models\UnitTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateUnitTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Unit $unit, string $previousName, ?string $previousDescription, ?int $actorUserId = null): void
    {
        $nameChanged = trim($previousName) !== trim((string) $unit->name);
        $descriptionChanged = trim((string) $previousDescription) !== trim((string) ($unit->description ?? ''));

        if (! $nameChanged && ! $descriptionChanged) {
            return;
        }

        if (! $unit->is_active) {
            return;
        }

        $source = $unit->normalizedOriginalLanguage();

        $invalidated = UnitTranslation::query()
            ->where('unit_id', $unit->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', UnitTranslationStatus::Pending->value)
                    ->orWhereNotNull('name')
                    ->orWhereNotNull('description');
            })
            ->update([
                'name' => null,
                'description' => null,
                'status' => UnitTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $unit->tenant_id,
            'unit.translations_invalidated',
            Unit::class,
            (int) $unit->id,
            [
                'unit_id' => $unit->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
