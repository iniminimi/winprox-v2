<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Data\Sense\SenseAttentionLine;
use App\Models\SensePoint;
use App\Support\Sense\SenseBands;

class ListSenseAttentionAction
{
    /**
     * @return list<SenseAttentionLine>
     */
    public function handle(int $tenantId): array
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

                $lines[] = new SenseAttentionLine(
                    sensePointId: (int) $point->id,
                    room: $room,
                    detail: $detail,
                    url: $this->settingsUrl($point),
                );
            }
        }

        return $lines;
    }

    private function placeName(SensePoint $point): string
    {
        if ($point->unit !== null) {
            return SenseBands::roomLabel($point->unit);
        }

        return (string) ($point->screen?->clockPoint?->name ?? '');
    }

    private function settingsUrl(SensePoint $point): string
    {
        $screen = $point->screen;
        if ($screen?->clock_point_id !== null) {
            return route('time.clock-displays.pair', $screen->clock_point_id);
        }

        if ($screen?->unit_id !== null) {
            return route('screens.pair', $screen->unit_id);
        }

        return route('dashboard');
    }
}
