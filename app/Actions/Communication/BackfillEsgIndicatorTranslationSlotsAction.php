<?php

namespace App\Actions\Communication;

use App\Models\EsgIndicator;
use App\Models\EsgIndicatorTranslation;

class BackfillEsgIndicatorTranslationSlotsAction
{
    public function __construct(private EnsureEsgIndicatorTranslationSlotsAction $ensureSlots) {}

    /**
     * @return array{indicators: int, slots_created: int}
     */
    public function handle(?int $tenantId = null): array
    {
        $indicatorsProcessed = 0;
        $slotsCreated = 0;

        EsgIndicator::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
            ->orderBy('id')
            ->chunkById(100, function ($indicators) use (&$indicatorsProcessed, &$slotsCreated): void {
                foreach ($indicators as $indicator) {
                    $before = EsgIndicatorTranslation::query()
                        ->where('esg_indicator_id', $indicator->id)
                        ->count();

                    $this->ensureSlots->handle($indicator);

                    $after = EsgIndicatorTranslation::query()
                        ->where('esg_indicator_id', $indicator->id)
                        ->count();

                    $indicatorsProcessed++;
                    $slotsCreated += max(0, $after - $before);
                }
            });

        return [
            'indicators' => $indicatorsProcessed,
            'slots_created' => $slotsCreated,
        ];
    }
}
