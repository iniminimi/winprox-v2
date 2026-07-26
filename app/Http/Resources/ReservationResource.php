<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Reservation */
class ReservationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'unit_id' => $this->unit_id,
            'worker_id' => $this->worker_id,
            'guest_first_name' => $this->guest_first_name,
            'guest_last_name' => $this->guest_last_name,
            'guest_email' => $this->guest_email,
            'start_at' => optional($this->start_at)->toIso8601String(),
            'end_at' => optional($this->end_at)->toIso8601String(),
            'confirmed_at' => optional($this->confirmed_at)->toIso8601String(),
            'cancelled_at' => optional($this->cancelled_at)->toIso8601String(),
            'expires_at' => optional($this->expires_at)->toIso8601String(),
            'lifecycle' => $this->lifecycle()->value,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
