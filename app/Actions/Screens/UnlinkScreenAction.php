<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Models\SensePoint;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnlinkScreenAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Screen $screen, int $tenantId, ?int $actorUserId): void
    {
        if ((int) $screen->tenant_id !== $tenantId || ! $screen->isLinked()) {
            throw ValidationException::withMessages([
                'screen' => [__('screen.errors.not_pending')],
            ]);
        }

        DB::transaction(function () use ($screen, $tenantId, $actorUserId): void {
            $hint = $screen->device_hint;

            $screen->update([
                'device_hint' => null,
                'display_secret' => null,
                'paired_at' => null,
                'last_seen_at' => null,
                'pairing_code' => null,
                'pairing_expires_at' => null,
            ]);

            if ($screen->unit_id !== null) {
                $point = SensePoint::query()->where('unit_id', $screen->unit_id)->first();
                if ($point !== null) {
                    $token = SensePoint::issueToken();
                    $point->update([
                        'token_hash' => $token['hash'],
                        'token_prefix' => $token['prefix'],
                    ]);
                }
            }

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.unlinked',
                modelType: Screen::class,
                modelId: $screen->id,
                payload: [
                    'unit_id' => $screen->unit_id,
                    'device_hint' => $hint,
                ],
            );
        });
    }
}
