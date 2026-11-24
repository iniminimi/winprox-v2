<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Support\Audit\AuditRecorder;
use Illuminate\Validation\ValidationException;

class DisableSenseOnScreenAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Screen $screen, int $tenantId, ?int $actorUserId = null): void
    {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'screen' => [__('screen.errors.unit_invalid')],
            ]);
        }

        $screen->update(['has_sense' => false]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'screen.sense_disabled',
            modelType: Screen::class,
            modelId: $screen->id,
            payload: [
                'screen_id' => $screen->id,
                'unit_id' => $screen->unit_id,
                'clock_point_id' => $screen->clock_point_id,
            ],
        );
    }
}
