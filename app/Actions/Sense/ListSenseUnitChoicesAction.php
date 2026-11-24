<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\Unit;
use App\Support\Sense\SenseBands;

class ListSenseUnitChoicesAction
{
    /**
     * @return list<array{id: int, label: string}>
     */
    public function handle(int $tenantId): array
    {
        return Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereDoesntHave('sensePoint')
            ->with('location')
            ->orderBy('name')
            ->get()
            ->map(fn (Unit $unit): array => [
                'id' => (int) $unit->id,
                'label' => SenseBands::choiceLabel($unit),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }
}
