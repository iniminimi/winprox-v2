<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Http\Requests\Sense\UpdateSenseDisplayLocaleRequest;
use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;

class UpdateSenseDisplayLocaleAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(SensePoint $point, string $locale, int $tenantId, ?int $actorUserId = null): SensePoint
    {
        $validated = UpdateSenseDisplayLocaleRequest::validatePayload(['locale' => $locale]);
        $locale = $validated['locale'];

        if ($point->display_locale === $locale) {
            return $point;
        }

        $point->display_locale = $locale;
        $point->save();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.locale_updated',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: ['display_locale' => $locale],
        );

        return $point->fresh() ?? $point;
    }
}
