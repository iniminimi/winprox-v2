<?php

declare(strict_types=1);

namespace App\Actions\Sense;

use App\Models\SensePoint;
use App\Models\Unit;
use App\Support\Audit\AuditRecorder;
use App\Support\Checkmate\CheckmateMode;
use App\Models\Tenant;
use Illuminate\Validation\ValidationException;

class CreateSensePointAction
{
    public function __construct(private AuditRecorder $audit) {}

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

        $token = SensePoint::issueToken();
        $defaults = config('sense.defaults');

        $point = SensePoint::query()->create([
            'tenant_id' => $tenantId,
            'unit_id' => $unit->id,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'temperature_low' => $defaults['temperature']['low'],
            'temperature_target' => $defaults['temperature']['target'],
            'temperature_high' => $defaults['temperature']['high'],
            'humidity_low' => $defaults['humidity']['low'],
            'humidity_target' => $defaults['humidity']['target'],
            'humidity_high' => $defaults['humidity']['high'],
            'co2_high' => $defaults['co2_high'],
            'voc_high' => $defaults['voc_high'],
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.created',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: [
                'unit_id' => $unit->id,
                'token_prefix' => $point->token_prefix,
            ],
        );

        return [
            'point' => $point,
            'plain_token' => $token['plain'],
        ];
    }
}
