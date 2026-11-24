<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Models\Screen;
use App\Models\SensePoint;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Checkmate\CheckmateMode;
use App\Support\Sense\SenseSeason;
use Illuminate\Validation\ValidationException;

class EnableSenseOnScreenAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @return array{point: SensePoint, plain_token: ?string}
     */
    public function handle(Screen $screen, int $tenantId, ?int $actorUserId = null): array
    {
        if ((int) $screen->tenant_id !== $tenantId) {
            throw ValidationException::withMessages([
                'screen' => [__('screen.errors.unit_invalid')],
            ]);
        }

        $tenant = Tenant::query()->find($tenantId);
        if ($tenant === null || CheckmateMode::isActive($tenant)) {
            throw ValidationException::withMessages([
                'screen' => [__('sense.errors.unavailable')],
            ]);
        }

        $existing = SensePoint::query()->where('screen_id', $screen->id)->first();
        $screen->update(['has_sense' => true]);

        if ($existing !== null) {
            return ['point' => $existing, 'plain_token' => null];
        }

        $token = SensePoint::issueToken();
        $season = SenseSeason::current();
        $preset = config('sense.presets.'.$season);
        $defaults = config('sense.defaults');

        $point = SensePoint::query()->create([
            'tenant_id' => $tenantId,
            'screen_id' => $screen->id,
            'unit_id' => $screen->unit_id,
            'token_hash' => $token['hash'],
            'token_prefix' => $token['prefix'],
            'temperature_low' => $preset['temperature']['low'],
            'temperature_target' => $preset['temperature']['target'],
            'temperature_high' => $preset['temperature']['high'],
            'humidity_low' => $preset['humidity']['low'],
            'humidity_target' => $preset['humidity']['target'],
            'humidity_high' => $preset['humidity']['high'],
            'co2_high' => $defaults['co2_high'],
            'voc_high' => $defaults['voc_high'],
            'season_applied' => $season,
        ]);

        $this->audit->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'sense_point.created',
            modelType: SensePoint::class,
            modelId: (int) $point->id,
            payload: [
                'screen_id' => $screen->id,
                'unit_id' => $screen->unit_id,
                'clock_point_id' => $screen->clock_point_id,
                'token_prefix' => $point->token_prefix,
            ],
        );

        return [
            'point' => $point,
            'plain_token' => $token['plain'],
        ];
    }
}
