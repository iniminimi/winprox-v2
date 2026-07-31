<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureCategoryTranslationSlotsAction;
use App\Actions\Communication\InvalidateCategoryTranslationsOnSourceChangeAction;
use App\Models\Category;
use App\Support\Audit\AuditRecorder;

class UpdateCategoryAction
{
    public function __construct(
        private AuditRecorder $audit,
        private InvalidateCategoryTranslationsOnSourceChangeAction $invalidateTranslations,
        private EnsureCategoryTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array{name: string, allow_gps_location?: bool, is_reservable?: bool, require_reporter_contact?: bool}  $data
     */
    public function handle(Category $category, array $data, ?int $actorUserId = null): Category
    {
        $previousName = (string) $category->name;

        $category->update([
            'name' => trim($data['name']),
            'allow_gps_location' => (bool) ($data['allow_gps_location'] ?? false),
            'is_reservable' => (bool) ($data['is_reservable'] ?? false),
            'require_reporter_contact' => (bool) ($data['require_reporter_contact'] ?? false),
        ]);

        $fresh = $category->fresh();

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
                'require_reporter_contact' => $fresh->require_reporter_contact,
            ],
        );

        return $fresh;
    }
}
