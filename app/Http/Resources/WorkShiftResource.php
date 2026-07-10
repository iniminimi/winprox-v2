<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\WorkShift */
class WorkShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'worker_id' => $this->worker_id,
            'internal_team_id' => $this->internal_team_id,
            'status' => $this->status->value,
            'clock_in_at' => $this->clock_in_at?->toIso8601String(),
            'clock_out_at' => $this->clock_out_at?->toIso8601String(),
            'total_break_minutes' => $this->total_break_minutes,
            'net_work_minutes' => $this->netWorkMinutes(),
            'clock_in_clock_point_id' => $this->clock_in_clock_point_id,
            'clock_out_clock_point_id' => $this->clock_out_clock_point_id,
        ];
    }
}
