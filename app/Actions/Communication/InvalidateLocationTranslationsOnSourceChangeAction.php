<?php

namespace App\Actions\Communication;

use App\Enums\LocationTranslationStatus;
use App\Models\Location;
use App\Models\LocationTranslation;
use App\Support\Audit\AuditRecorder;

class InvalidateLocationTranslationsOnSourceChangeAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Location $location, string $previousName, ?int $actorUserId = null): void
    {
        if (trim($previousName) === trim((string) $location->name)) {
            return;
        }

        if (! $location->is_active || trim((string) $location->name) === '') {
            return;
        }

        $source = $location->normalizedOriginalLanguage();

        $invalidated = LocationTranslation::query()
            ->where('location_id', $location->id)
            ->where('locale', '!=', $source)
            ->where(function ($query) {
                $query->where('status', '!=', LocationTranslationStatus::Pending->value)
                    ->orWhereNotNull('name');
            })
            ->update([
                'name' => null,
                'status' => LocationTranslationStatus::Pending->value,
            ]);

        if ($invalidated === 0) {
            return;
        }

        $this->audit->record(
            $actorUserId,
            (int) $location->tenant_id,
            'location.translations_invalidated',
            Location::class,
            (int) $location->id,
            [
                'location_id' => $location->id,
                'rows' => $invalidated,
                'source_locale' => $source,
            ],
        );
    }
}
