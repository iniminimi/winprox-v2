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
        $lines = [];

        $points = SensePoint::query()
            ->where('tenant_id', $tenantId)
            ->with(['unit.location'])
            ->get()
            ->sortBy(fn (SensePoint $point): string => mb_strtolower(
                $point->unit !== null ? SenseBands::roomLabel($point->unit) : ''
            ));

        foreach ($points as $point) {
            $room = $point->unit !== null ? SenseBands::roomLabel($point->unit) : '';
            foreach (SenseBands::attentions($point) as $row) {
                $lines[] = new SenseAttentionLine(
                    sensePointId: (int) $point->id,
                    sentence: SenseBands::sentence(
                        $room,
                        $row['metric'],
                        $row['direction'],
                        $row['value'],
                        $row['limit'],
                        $row['recorded_at'],
                    ),
                );
            }
        }

        return $lines;
    }
}
