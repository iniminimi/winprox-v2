<?php

namespace App\Http\Resources;

use App\Models\Worker;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Worker */
class WorkerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internal_team_id' => $this->internal_team_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'is_active' => (bool) $this->is_active,
            'is_teamleader' => (bool) $this->is_teamleader,
        ];
    }
}
