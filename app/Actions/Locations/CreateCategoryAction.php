<?php

namespace App\Actions\Locations;

use App\Actions\Communication\EnsureCategoryTranslationSlotsAction;
use App\Models\Category;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;

class CreateCategoryAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureCategoryTranslationSlotsAction $ensureSlots,
    ) {}

    /**
     * @param  array{name: string, allow_gps_location?: bool, is_reservable?: bool, allow_unit_checks?: bool, require_reporter_contact?: bool, require_reporter_email_verification?: bool, original_language?: string|null}  $data
     */
    public function handle(int $tenantId, array $data, ?int $actorUserId = null): Category
    {
        $category = Category::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'allow_gps_location' => (bool) ($data['allow_gps_location'] ?? false),
            'is_reservable' => (bool) ($data['is_reservable'] ?? false),
            'allow_unit_checks' => (bool) ($data['allow_unit_checks'] ?? false),
            'require_reporter_contact' => (bool) ($data['require_reporter_contact'] ?? false),
            'require_reporter_email_verification' => (bool) ($data['require_reporter_email_verification'] ?? false),
        ]);

        $this->ensureSlots->handle($category);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'category.created',
            modelType: Category::class,
            modelId: (int) $category->id,
            payload: [
                'id' => $category->id,
                'name' => $category->name,
                'allow_gps_location' => $category->allow_gps_location,
                'is_reservable' => $category->is_reservable,
                'allow_unit_checks' => $category->allow_unit_checks,
                'require_reporter_contact' => $category->require_reporter_contact,
                'require_reporter_email_verification' => $category->require_reporter_email_verification,
            ],
        );

        return $category;
    }
}
