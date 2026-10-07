<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\ListClockDisplayWorkersAction;
use App\Actions\Time\PinClockFromClockDisplayAction;
use App\Actions\Time\RecordClockDisplayPingAction;
use App\Actions\Time\SetupClockDisplayPinAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Enums\ClockDisplayClaimStatus;
use App\Enums\ClockDisplayPinStatus;
use App\Http\Requests\Api\V1\PinClockDisplayRequest;
use App\Http\Requests\Api\V1\SetupPinClockDisplayRequest;
use App\Http\Requests\Api\V1\SubmitClockDisplayClaimRequest;
use App\Models\ClockDisplayClaim;
use App\Models\ClockDisplayImage;
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
        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
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
     * No-phone fallback: minimale workerlijst voor de lettertrie op het
     * scherm — actief + PIN + locatiescope van dit Clock Point.
     */
    public function workers(
        Request $request,
        ListClockDisplayWorkersAction $listWorkers,
    ): JsonResponse {
        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
        }

        return response()->json([
            'workers' => $listWorkers->handle($clockPoint),
        ]);
    }

    /**
     * PIN-klok vanaf het scherm: worker_id + 4-cijferige PIN → in/uit.
     * Server is autoriteit voor PIN-check, worker-lockout en audit.
     */
    public function pinClock(
        PinClockDisplayRequest $request,
        PinClockFromClockDisplayAction $pinClock,
    ): JsonResponse {
        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
        }

        $validated = $request->validated();

        try {
            $result = $pinClock->handle(
                $clockPoint,
                (int) $validated['worker_id'],
                (string) $validated['pin'],
            );
        } catch (InvalidArgumentException $e) {
            // Race: shift-status of locatie/activiteit veranderde tussen
            // trie-selectie en submit — device toont de fouttekst.
            return response()->json(['error' => $e->getMessage()], 409);
        }

        return match ($result->status) {
            ClockDisplayPinStatus::ClockedIn => response()->json([
                'result' => 'in',
                'worker' => $result->workerName,
                'at' => $result->clockedAt,
            ]),
            ClockDisplayPinStatus::ClockedOut => response()->json([
                'result' => 'out',
                'worker' => $result->workerName,
                'at' => $result->clockedAt,
            ]),
            ClockDisplayPinStatus::InvalidPin => response()->json(['error' => 'invalid_pin'], 401),
            ClockDisplayPinStatus::WorkerLocked => response()->json([
                'error' => 'worker_locked',
                'retry_after' => $result->retryAfterSeconds,
            ], 429),
            ClockDisplayPinStatus::WorkerNotFound => response()->json(['error' => 'worker_not_found'], 404),
        };
    }

    /**
     * Worker zonder PIN zet zijn code op het scherm zelf (2× ingeven) en
     * wordt meteen in/uitgeklokt — zelfde resultaat-shape als pinClock.
     */
    public function pinSetup(
        SetupPinClockDisplayRequest $request,
        SetupClockDisplayPinAction $setup,
    ): JsonResponse {
        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
        }

        $validated = $request->validated();

        try {
            $result = $setup->handle(
                $clockPoint,
                (int) $validated['worker_id'],
                (string) $validated['pin'],
            );
        } catch (InvalidArgumentException $e) {
            return match ($e->getMessage()) {
                'pin_already_set' => response()->json(['error' => 'pin_already_set'], 409),
                default => response()->json(['error' => 'worker_not_found'], 404),
            };
        }

        return match ($result->status) {
            ClockDisplayPinStatus::ClockedIn => response()->json([
                'result' => 'in',
                'worker' => $result->workerName,
                'at' => $result->clockedAt,
            ]),
            ClockDisplayPinStatus::ClockedOut => response()->json([
                'result' => 'out',
                'worker' => $result->workerName,
                'at' => $result->clockedAt,
            ]),
            ClockDisplayPinStatus::WorkerLocked => response()->json([
                'error' => 'worker_locked',
                'retry_after' => $result->retryAfterSeconds,
            ], 429),
            default => response()->json(['error' => 'worker_not_found'], 404),
        };
    }

    /**
     * Gekoppeld Clock Point uit de device-middleware of 401 — het scherm
     * wist dan zijn credentials en valt terug naar pairing-modus.
     */
    private function linkedPoint(Request $request): ClockPoint|JsonResponse
    {
        /** @var ClockPoint|null $clockPoint */
        $clockPoint = $request->attributes->get('clock_display_point');

        if ($clockPoint === null || ! $clockPoint->hasLinkedDisplay()) {
            return response()->json(['error' => 'unlinked'], 401);
        }

        return $clockPoint;
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
            'album' => $this->albumManifest($clockPoint),
            'rotation_seconds' => (int) config('time.display_window_seconds', 30),
            'offline_warn_hours' => (int) config('time.display_offline_warn_hours', 24),
            'offline_block_hours' => (int) config('time.display_offline_block_hours', 168),
            // Scherm vult hier {display_id}{dyn} in; display_id volgt mee in
            // de claim-status-response (of staat in NVS na provisioning).
            'portal_url_template' => route('public.time-portal', '__TOKEN__'),
        ];
    }

    /**
     * Foto-album-manifest: vensters + beeldlijst voor de slideshow.
     * `version` wijzigt bij elke wijziging → scherm synchroniseert dan.
     *
     * @return array<string, mixed>
     */
    private function albumManifest(?ClockPoint $clockPoint): array
    {
        if ($clockPoint === null) {
            return ['version' => null, 'windows' => [], 'images' => []];
        }

        $windows = [];
        foreach ([
            [$clockPoint->album1_from, $clockPoint->album1_until],
            [$clockPoint->album2_from, $clockPoint->album2_until],
        ] as [$from, $until]) {
            if ($from !== null && $until !== null) {
                $windows[] = [substr((string) $from, 0, 5), substr((string) $until, 0, 5)];
            }
        }

        $images = ClockDisplayImage::queryForPoint($clockPoint)
            ->limit(ClockDisplayImage::MAX_PER_SCOPE)
            ->get();

        $albumDays = (int) ($clockPoint->album_days ?? 31);

        $version = substr(md5(
            $images->map(fn (ClockDisplayImage $i) => $i->id.'@'.$i->updated_at?->getTimestamp())->implode(',')
            .'|'.json_encode($windows).'|'.$albumDays
        ), 0, 12);

        return [
            'version' => $version,
            // Rust-scherm tijdens de vensters: photos | clock | none.
            'mode' => $clockPoint->album_mode ?? 'photos',
            'windows' => $windows,
            // Bitmask weekdagen (bit 0 = ma … bit 6 = zo); geldt voor de
            // dag waarop een venster begint (relevant over middernacht).
            'days' => $albumDays,
            'images' => $images
                ->map(fn (ClockDisplayImage $i) => ['id' => $i->id, 'url' => $i->publicUrl()])
                ->values()
                ->all(),
        ];
    }
}
