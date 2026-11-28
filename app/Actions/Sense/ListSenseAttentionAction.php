<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\SenseAttentionBoard;
use App\Data\Sense\SenseAttentionLine;
use App\Enums\SenseAttentionDirection;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;

class ListSenseAttentionAction
{
    public function handle(int $tenantId): SenseAttentionBoard
    {
        app(SyncSenseSeasonAction::class)->handleTenant($tenantId);

        $lines = [];

        $points = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('screen', fn ($query) => $query->where('has_sense', true))
            ->with(['unit.location', 'screen.clockPoint', 'screen.unit'])
            ->get()
            ->sortBy(fn (SensePoint $point): string => mb_strtolower($this->placeName($point)));

        foreach ($points as $point) {
            $room = $this->placeName($point);
            foreach (SenseBands::attentions($point) as $row) {
                $sentence = SenseBands::sentence(
                    $room,
                    $row['metric'],
                    $row['direction'],
                    $row['value'],
                    $row['limit'],
                    $row['recorded_at'],
                );
                $prefix = $room.' · ';
                $detail = str_starts_with($sentence, $prefix)
                    ? mb_substr($sentence, mb_strlen($prefix))
                    : $sentence;

                $band = in_array($row['direction'], [SenseAttentionDirection::Below, SenseAttentionDirection::Above], true);

                $lines[] = new SenseAttentionLine(
                    sensePointId: (int) $point->id,
                    room: $room,
                    detail: $detail,
                    url: $this->readingsUrl($point, selectScreen: true, alarms: $band) ?? route('dashboard'),
                );
            }
        }

        $readingsUrl = null;
        $first = $points->first();
        if ($lines === [] && $first instanceof SensePoint) {
            $readingsUrl = $this->readingsUrl($first, selectScreen: false, alarms: false);
        }

        return new SenseAttentionBoard($lines, $readingsUrl);
    }

    private function placeName(SensePoint $point): string
    {
        if ($point->unit !== null) {
            return SenseBands::roomLabel($point->unit);
        }

        return (string) ($point->screen?->clockPoint?->name ?? '');
    }

    private function readingsUrl(SensePoint $point, bool $selectScreen, bool $alarms): ?string
    {
        $params = [];
        if ($selectScreen) {
            $params['screen'] = $point->id;
        }
        if ($alarms) {
            $params['alarms'] = 1;
        }

        $screen = $point->screen;
        if ($screen?->clock_point_id !== null) {
            return route('time.clock-displays.sense', ['clockPoint' => $screen->clock_point_id] + $params);
        }

        $unitId = $screen?->unit_id ?? $point->unit_id;
        if ($unitId !== null) {
            return route('screens.sense', ['unit' => $unitId] + $params);
        }

        return null;
    }
}
