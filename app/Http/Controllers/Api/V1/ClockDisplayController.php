<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\RecordClockDisplayPingAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Enums\ClockDisplayClaimStatus;
use App\Http\Requests\Api\V1\SubmitClockDisplayClaimRequest;
use App\Models\ClockDisplayClaim;
use App\Models\ClockPoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Device-API voor klokschermen (ESP32): claim → claim-status (poll) → ping.
 * `claim`/`claim-status` zijn publiek — de pairing-code + admin-confirm zijn
 * de toegangspoort; `ping` vereist de wpclk_-token (clock.display-middleware).
 */
class ClockDisplayController extends Controller
{
    public function claim(
        SubmitClockDisplayClaimRequest $request,
        SubmitClockDisplayClaimAction $submit,
    ): JsonResponse {
        $validated = $request->validated();

        try {
            $claim = $submit->handle(
                (string) $validated['code'],
                (string) $validated['device_hint'],
                $request->ip(),
            );
        } catch (InvalidArgumentException $e) {
            return match ($e->getMessage()) {
                'claim_pending' => response()->json(['error' => 'claim_pending'], 409),
                'claim_rate_limited' => response()->json(['error' => 'rate_limited'], 429),
                default => response()->json(['error' => 'pairing_code_invalid'], 404),
            };
        }

        return response()->json([
            'claim_token' => $claim->claim_token,
            'status' => $claim->status->value,
            'expires_at' => $claim->expires_at?->toIso8601String(),
        ], 201);
    }

    public function claimStatus(string $claimToken): JsonResponse
    {
        $claim = ClockDisplayClaim::query()
            ->where('claim_token', $claimToken)
            ->with('clockPoint')
            ->first();

        if ($claim === null) {
            return response()->json(['error' => 'claim_not_found'], 404);
        }

        // Verlopen pending die de scheduler nog niet op 'expired' zette —
        // zelfde semantiek als ClockDisplayClaim::isPending().
        if ($claim->status === ClockDisplayClaimStatus::Pending && ! $claim->isPending()) {
            return response()->json(['status' => ClockDisplayClaimStatus::Expired->value]);
        }

        if ($claim->status !== ClockDisplayClaimStatus::Confirmed) {
            return response()->json([
                'status' => $claim->status->value,
                'reason' => $claim->denied_reason,
            ]);
        }

        $clockPoint = $claim->clockPoint;

        return response()->json([
            'status' => ClockDisplayClaimStatus::Confirmed->value,
            'device_token' => $claim->issued_token,
            'display_id' => $clockPoint?->display_id,
            'display_secret' => $clockPoint?->display_secret,
            'clock_point_name' => $clockPoint?->name,
            ...$this->displayConfig($clockPoint),
        ]);
    }

    public function ping(Request $request, RecordClockDisplayPingAction $ping): JsonResponse
    {
        /** @var ClockPoint|null $clockPoint */
        $clockPoint = $request->attributes->get('clock_display_point');

        if ($clockPoint === null || ! $clockPoint->hasLinkedDisplay()) {
            return response()->json(['error' => 'unlinked'], 401);
        }

        $clockPoint = $ping->handle($clockPoint);

        return response()->json([
            'state' => $clockPoint->is_active ? 'active' : 'inactive',
            'clock_point_name' => $clockPoint->name,
            'location_name' => $clockPoint->location?->localizedName(),
            ...$this->displayConfig($clockPoint),
        ]);
    }

    /**
     * Config die het scherm nodig heeft voor de QR-tokens + offline-grenzen;
     * server-side instelbaar zodat drempels zonder firmware-update wijzigen.
     *
     * @return array<string, mixed>
     */
    private function displayConfig(?ClockPoint $clockPoint): array
    {
        return [
            'server_time' => now()->toIso8601String(),
            // Aan-uren van het scherm ("06:00"/null = altijd aan). Firmware
            // dimt de backlight buiten dit venster; over-middernacht werkt.
            'display_on_from' => $clockPoint?->display_on_from !== null
                ? substr((string) $clockPoint->display_on_from, 0, 5) : null,
            'display_on_until' => $clockPoint?->display_on_until !== null
                ? substr((string) $clockPoint->display_on_until, 0, 5) : null,
            'rotation_seconds' => (int) config('time.display_window_seconds', 30),
            'offline_warn_hours' => (int) config('time.display_offline_warn_hours', 24),
            'offline_block_hours' => (int) config('time.display_offline_block_hours', 168),
            // Scherm vult hier {display_id}{dyn} in; display_id volgt mee in
            // de claim-status-response (of staat in NVS na provisioning).
            'portal_url_template' => route('public.time-portal', '__TOKEN__'),
        ];
    }
}
