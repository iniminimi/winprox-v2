<?php

namespace App\Actions\Esg;

use App\Actions\Communication\EnsureEsgIndicatorTranslationSlotsAction;
use App\Enums\EsgIndicatorCategory;
use App\Enums\EsgIndicatorType;
use App\Models\EsgIndicator;
use App\Support\Audit\AuditRecorder;
use App\Support\Translation\LocaleSupport;

class CreateEsgIndicatorAction
{
    public function __construct(
        private AuditRecorder $audit,
        private EnsureEsgIndicatorTranslationSlotsAction $ensureTranslationSlots,
    ) {}

    /**
     * @param  array{name: string, type: string|EsgIndicatorType, category?: ?EsgIndicatorCategory, unit_of_measure?: ?string, thresholds?: ?array, options?: ?list<string>, original_language?: ?string}  $data
     */
    public function handle(int $tenantId, array $data, ?int $actorUserId = null): EsgIndicator
    {
        $type = $data['type'] instanceof EsgIndicatorType
            ? $data['type']
            : EsgIndicatorType::from((string) $data['type']);

        $indicator = EsgIndicator::query()->create([
            'tenant_id' => $tenantId,
            'name' => trim($data['name']),
            'original_language' => LocaleSupport::normalize($data['original_language'] ?? null),
            'type' => $type,
            'category' => $data['category'] ?? null,
            'unit_of_measure' => $data['unit_of_measure'] ?? null,
            'is_active' => true,
            'thresholds' => $data['thresholds'] ?? null,
            'options' => $data['options'] ?? null,
        ]);

        $this->ensureTranslationSlots->handle($indicator);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'esg_indicator.created',
            modelType: EsgIndicator::class,
            modelId: (int) $indicator->id,
            payload: [
                'id' => $indicator->id,
                'name' => $indicator->name,
                'type' => $indicator->type->value,
            ],
        );

        return $indicator;
    }
}
