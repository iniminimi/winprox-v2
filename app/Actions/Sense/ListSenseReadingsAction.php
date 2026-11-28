<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SenseReading;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListSenseReadingsAction
{
    public function handle(int $tenantId, int $sensePointId, CarbonImmutable $from, CarbonImmutable $until, int $page): LengthAwarePaginator
    {
        return SenseReading::query()
            ->where('tenant_id', $tenantId)
            ->where('sense_point_id', $sensePointId)
            ->where('recorded_at', '>=', $from)
            ->where('recorded_at', '<=', $until)
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->paginate(perPage: 50, page: max(1, $page));
    }
}
