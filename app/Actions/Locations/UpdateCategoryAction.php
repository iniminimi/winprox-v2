<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureCategoryTranslationSlotsAction;
use App\Actions\Communication\InvalidateCategoryTranslationsOnSourceChangeAction;
use App\Actions\Issues\RemoveUnitsFromInspectionRoundsAction;
use App\Models\Category;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;

class UpdateCategoryAction
{
    public function __construct(
        private AuditRecorder $audit,
        private InvalidateCategoryTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureCategoryTranslationSlotsAction $ensureSlots,
        private RemoveUnitsFromInspectionRoundsAction $removeFromRounds,
    ) {}

    /**
     * @param  array{name: string, allow_gps_location?: bool, is_reservable?: bool, allow_unit_checks?: bool, require_reporter_contact?: bool}  $data
     */
    public function handle(Category $category, array $data, ?int $actorUserId = null): Category
    {
        $previousName = (string) $category->name;
        $unitChecksWereAllowed = (bool) $category->allow_unit_checks;

        $category->update([
            'name' => trim($data['name']),
            'allow_gps_location' => (bool) ($data['allow_gps_location'] ?? false),
            'is_reservable' => (bool) ($data['is_reservable'] ?? false),
            'allow_unit_checks' => (bool) ($data['allow_unit_checks'] ?? false),
            'require_reporter_contact' => (bool) ($data['require_reporter_contact'] ?? false),
        ]);

        $fresh = $category->fresh();

        if ($unitChecksWereAllowed && ! (bool) $fresh->allow_unit_checks) {
            $unitIds = Unit::query()
                ->where('tenant_id', $fresh->tenant_id)
                ->where('category_id', $fresh->id)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $this->removeFromRounds->handle($unitIds, (int) $fresh->tenant_id);
        }

        $this->invalidateTranslations->handle($fresh, $previousName, $actorUserId);
        $this->ensureSlots->handle($fresh);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->tenant_id,
            action: 'category.updated',
            modelType: Category::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'name' => $fresh->name,
                'allow_gps_location' => $fresh->allow_gps_location,
                'is_reservable' => $fresh->is_reservable,
                'allow_unit_checks' => $fresh->allow_unit_checks,
                'require_reporter_contact' => $fresh->require_reporter_contact,
            ],
        );

        return $fresh;
    }
}
