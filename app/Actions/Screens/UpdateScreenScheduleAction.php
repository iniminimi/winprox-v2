<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateScreenScheduleAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Screen $screen, int $tenantId, ?int $actorUserId, ?string $from, ?string $until): Screen
    {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (($from === null) !== ($until === null)) {
            throw new InvalidArgumentException('schedule_incomplete');
        }

        return DB::transaction(function () use ($screen, $tenantId, $actorUserId, $from, $until) {
            $locked = Screen::query()->whereKey($screen->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('screen_not_found');
            }

            $locked->update([
                'display_on_from' => $from,
                'display_on_until' => $until,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.schedule_updated',
                modelType: Screen::class,
                modelId: $locked->id,
                payload: [
                    'screen_id' => $locked->id,
                    'unit_id' => $locked->unit_id,
                    'display_on_from' => $from,
                    'display_on_until' => $until,
                ],
            );

            return $locked;
        });
    }
}
