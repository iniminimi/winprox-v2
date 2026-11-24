<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Actions\Time\UpdateClockDisplayAlbumWindowsAction;
use App\Models\Screen;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateScreenAlbumAction
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        Screen $screen,
        int $tenantId,
        ?int $actorUserId,
        ?string $from1,
        ?string $until1,
        ?string $from2,
        ?string $until2,
        string $albumMode,
        int $albumDays,
    ): Screen {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw new InvalidArgumentException('tenant_mismatch');
        }

        if (! in_array($albumMode, UpdateClockDisplayAlbumWindowsAction::MODES, true)) {
            throw new InvalidArgumentException('album_mode_invalid');
        }

        if ($albumDays < 0 || $albumDays > 0x7F) {
            throw new InvalidArgumentException('album_days_invalid');
        }

        if (($from1 === null) !== ($until1 === null) || ($from2 === null) !== ($until2 === null)) {
            throw new InvalidArgumentException('album_windows_incomplete');
        }

        $onFrom = $this->hm($screen->display_on_from);
        $onUntil = $this->hm($screen->display_on_until);
        foreach ([[$from1, $until1], [$from2, $until2]] as [$from, $until]) {
            if ($from !== null && ! $this->withinDisplayHours($onFrom, $onUntil, $from, $until)) {
                throw new InvalidArgumentException('album_outside_schedule');
            }
        }

        return DB::transaction(function () use ($screen, $tenantId, $actorUserId, $from1, $until1, $from2, $until2, $albumMode, $albumDays) {
            $locked = Screen::query()->whereKey($screen->id)->lockForUpdate()->first();
            if ($locked === null) {
                throw new InvalidArgumentException('screen_not_found');
            }

            $locked->update([
                'album1_from' => $from1,
                'album1_until' => $until1,
                'album2_from' => $from2,
                'album2_until' => $until2,
                'album_mode' => $albumMode,
                'album_days' => $albumDays,
            ]);

            $this->audit->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'screen.album_updated',
                modelType: Screen::class,
                modelId: $locked->id,
                payload: [
                    'screen_id' => $locked->id,
                    'album_mode' => $albumMode,
                    'album_days' => $albumDays,
                ],
            );

            return $locked;
        });
    }

    private function hm(mixed $time): ?string
    {
        return $time !== null ? substr((string) $time, 0, 5) : null;
    }

    private function withinDisplayHours(?string $onFrom, ?string $onUntil, string $from, string $until): bool
    {
        if ($onFrom === null || $onUntil === null) {
            return true;
        }

        $overnight = $onFrom > $onUntil;
        $inWindow = fn (string $t) => $overnight
            ? ($t >= $onFrom || $t <= $onUntil)
            : ($t >= $onFrom && $t <= $onUntil);

        if (! $inWindow($from) || ! $inWindow($until)) {
            return false;
        }

        if ($from > $until) {
            return $overnight && $from >= $onFrom && $until <= $onUntil;
        }

        return ! $overnight || $from >= $onFrom || $until <= $onUntil;
    }
}
