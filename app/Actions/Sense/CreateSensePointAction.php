<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Actions\Screens\EnableSenseOnScreenAction;
use App\Actions\Screens\EnsureUnitScreenAction;
use App\Models\SensePoint;
use App\Models\Unit;
use App\Support\Checkmate\CheckmateMode;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class CreateSensePointAction
{
    public function __construct(
        private EnsureUnitScreenAction $ensureScreen,
        private EnableSenseOnScreenAction $enableSense,
    ) {}

    /**
     * @return array{point: SensePoint, plain_token: string}
     */
    public function handle(int $unitId, int $tenantId, ?int $actorUserId = null): array
    {
        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || CheckmateMode::isActive($tenant)) {
            throw ValidationException::withMessages([
                'unit_id' => [__('sense.errors.unavailable')],
            ]);
        }

        $unit = Unit::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($unitId);

        if ($unit === null) {
            throw ValidationException::withMessages([
                'unit_id' => [__('sense.errors.unit_invalid')],
            ]);
        }

        if (SensePoint::query()->where('tenant_id', $tenantId)->where('unit_id', $unit->id)->exists()) {
            throw ValidationException::withMessages([
                'unit_id' => [__('sense.errors.unit_taken')],
            ]);
        }

        $screen = $this->ensureScreen->handle($unit, $tenantId);
        $created = $this->enableSense->handle($screen, $tenantId, $actorUserId);

        return [
            'point' => $created['point'],
            'plain_token' => (string) $created['plain_token'],
        ];
    }
}
