<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Time\ListClockDisplayWorkersAction;
use App\Actions\Time\PinClockFromClockDisplayAction;
use App\Actions\Time\RecordClockDisplayPingAction;
use App\Actions\Screens\RecordUnitScreenPingAction;
use App\Actions\Screens\SubmitScreenClaimAction;
use App\Actions\Sense\RecordUnitScreenSenseReadingAction;
use App\Actions\Time\RecordClockSenseReadingAction;
use App\Data\Sense\RecordSenseReadingData;
use App\Http\Requests\Sense\RecordSenseReadingRequest;
use App\Models\SensePoint;
use App\Support\Sense\SenseScreenWords;
use App\Actions\Time\SetupClockDisplayPinAction;
use App\Actions\Time\SubmitClockDisplayClaimAction;
use App\Enums\ClockDisplayClaimStatus;
use App\Enums\ScreenClaimStatus;
use App\Enums\ClockDisplayPinAction;
use App\Enums\ClockDisplayPinStatus;
use App\Actions\Time\ChangeClockDisplayPinAction;
use App\Http\Requests\Api\V1\ChangePinClockDisplayRequest;
use App\Http\Requests\Api\V1\PinClockDisplayRequest;
use App\Http\Requests\Api\V1\SetupPinClockDisplayRequest;
use App\Http\Requests\Api\V1\SubmitClockDisplayClaimRequest;
use App\Models\ClockDisplayClaim;
use App\Models\ClockDisplayImage;
use App\Models\ClockPoint;
use App\Models\Screen;
use App\Models\ScreenClaim;
use App\Support\Screens\ScreenQr;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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
        SubmitScreenClaimAction $submitScreen,
    ): JsonResponse {
        $validated = $request->validated();
        $code = (string) $validated['code'];
        $hint = (string) $validated['device_hint'];

        try {
            $claim = $submit->handle($code, $hint, $request->ip());
        } catch (InvalidArgumentException $e) {
            if ($e->getMessage() !== 'pairing_code_invalid') {
                return match ($e->getMessage()) {
                    'claim_pending' => response()->json(['error' => 'claim_pending'], 409),
                    'claim_rate_limited' => response()->json(['error' => 'rate_limited'], 429),
                    default => response()->json(['error' => 'pairing_code_invalid'], 404),
                };
            }

            try {
                $claim = $submitScreen->handle($code, $hint, $request->ip());
            } catch (ValidationException) {
                return response()->json(['error' => 'pairing_code_invalid'], 404);
            }
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
            $screenClaim = ScreenClaim::query()
                ->withoutGlobalScopes()
                ->where('claim_token', $claimToken)
                ->first();

            return $screenClaim instanceof ScreenClaim
                ? $this->screenClaimStatus($screenClaim)
                : response()->json(['error' => 'claim_not_found'], 404);
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

        // Device-credentials zijn een eenmalige handoff: na een korte window
        // vervalt de claim (het device heeft ze dan al opgeslagen; recovery =
        // opnieuw pairen). Zo blijft claim_token geen eeuwige cred-fetcher.
        $confirmedAt = $claim->confirmed_at;
        if ($confirmedAt === null || $confirmedAt->lt(now()->subMinutes(10))) {
            $claim->delete();

            return response()->json(['error' => 'claim_not_found'], 404);
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

    public function ping(
        Request $request,
        RecordClockDisplayPingAction $ping,
        RecordUnitScreenPingAction $pingScreen,
    ): JsonResponse {
        $screen = $request->attributes->get('clock_display_screen');
        if ($screen instanceof Screen) {
            $screen = $pingScreen->handle($screen);

            return response()->json($this->screenPingBody($screen));
        }

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

    public function sense(
        RecordSenseReadingRequest $request,
        RecordClockSenseReadingAction $record,
        RecordUnitScreenSenseReadingAction $recordScreen,
    ): JsonResponse {
        $screen = $request->attributes->get('clock_display_screen');
        if ($screen instanceof Screen) {
            try {
                $result = $recordScreen->handle(
                    $screen,
                    RecordSenseReadingData::fromValidated($request->validated()),
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 404);
            }

            return response()->json([
                'id' => $result['reading']->id,
                'created' => $result['created'],
            ], $result['created'] ? 201 : 200);
        }

        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
        }

        try {
            $result = $record->handle(
                $clockPoint,
                RecordSenseReadingData::fromValidated($request->validated()),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }

        return response()->json([
            'id' => $result['reading']->id,
            'created' => $result['created'],
        ], $result['created'] ? 201 : 200);
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
     * PIN-klok vanaf het scherm: worker_id + 4-cijferige PIN → in/uit/pauze.
     * Optionele `action` (in|out|break_start|break_end); zonder action = toggle.
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
        $action = isset($validated['action']) && is_string($validated['action']) && $validated['action'] !== ''
            ? ClockDisplayPinAction::from($validated['action'])
            : null;

        try {
            $result = $pinClock->handle(
                $clockPoint,
                (int) $validated['worker_id'],
                (string) $validated['pin'],
                $action,
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
            ClockDisplayPinStatus::BreakStarted => response()->json([
                'result' => 'break_start',
                'worker' => $result->workerName,
                'at' => $result->clockedAt,
            ]),
            ClockDisplayPinStatus::BreakEnded => response()->json([
                'result' => 'break_end',
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
     * Bestaande code wijzigen: huidige code + nieuwe code. Klokt niet.
     */
    public function pinChange(
        ChangePinClockDisplayRequest $request,
        ChangeClockDisplayPinAction $change,
    ): JsonResponse {
        $clockPoint = $this->linkedPoint($request);
        if (! $clockPoint instanceof ClockPoint) {
            return $clockPoint;
        }

        $validated = $request->validated();
        $result = $change->handle(
            $clockPoint,
            (int) $validated['worker_id'],
            (string) $validated['current_pin'],
            (string) $validated['new_pin'],
        );

        return match ($result->status) {
            ClockDisplayPinStatus::PinChanged => response()->json([
                'result' => 'changed',
                'worker' => $result->workerName,
            ]),
            ClockDisplayPinStatus::InvalidPin => response()->json(['error' => 'invalid_pin'], 401),
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
            // Unix UTC — scherm zet hiermee de QR-klok zonder ISO-offset-ambiguïteit.
            'server_unix' => now()->getTimestamp(),
            // Aan-uren van het scherm ("06:00"/null = altijd aan). Firmware
            // dimt de backlight buiten dit venster; over-middernacht werkt.
            'display_on_from' => $clockPoint?->display_on_from !== null
                ? substr((string) $clockPoint->display_on_from, 0, 5) : null,
            'display_on_until' => $clockPoint?->display_on_until !== null
                ? substr((string) $clockPoint->display_on_until, 0, 5) : null,
            // Piep bij toets en prikking; uit = stil scherm.
            'sound_enabled' => (bool) ($clockPoint?->display_sound_enabled ?? true),
            'album' => $this->albumManifest($clockPoint),
            'rotation_seconds' => $this->rotationSeconds($clockPoint),
            'offline_warn_hours' => (int) config('time.display_offline_warn_hours', 24),
            'offline_block_hours' => (int) config('time.display_offline_block_hours', 168),
            // Scherm vult hier {display_id}{dyn} in; display_id volgt mee in
            // de claim-status-response (of staat in NVS na provisioning).
            'portal_url_template' => route('public.time-portal', '__TOKEN__'),
            'sense' => $this->senseManifest($clockPoint),
        ];
    }

    /**
     * Grenzen voor de pills op het D1S-scherm. De sleutel zelf gaat niet mee:
     * het klokscherm post met zijn eigen token.
     *
     * @return array<string, mixed>
     */
    private function senseManifest(?ClockPoint $clockPoint): array
    {
        if ($clockPoint === null) {
            return ['enabled' => false];
        }

        $screen = Screen::query()
            ->where('clock_point_id', $clockPoint->id)
            ->where('has_sense', true)
            ->first();
        $point = $screen instanceof Screen
            ? SensePoint::query()->where('screen_id', $screen->id)->first()
            : null;

        if (! $point instanceof SensePoint) {
            return ['enabled' => false];
        }

        $locale = in_array($point->display_locale, config('locales.supported'), true)
            ? (string) $point->display_locale
            : (string) config('locales.default', 'nl');

        return [
            'enabled' => true,
            'locale' => $locale,
            'words' => SenseScreenWords::forLocale($locale),
            'temperature_low' => $point->temperature_low,
            'temperature_high' => $point->temperature_high,
            'humidity_low' => $point->humidity_low,
            'humidity_high' => $point->humidity_high,
            'co2_high' => $point->co2_high,
            'voc_high' => $point->voc_high,
        ];
    }

    private function rotationSeconds(?ClockPoint $clockPoint): int
    {
        if ($clockPoint === null) {
            return (int) config('time.display_window_seconds', 30);
        }

        $window = Screen::query()->where('clock_point_id', $clockPoint->id)->value('qr_window_seconds');

        return $window !== null ? (int) $window : (int) config('time.display_window_seconds', 30);
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
            // Rust-scherm tijdens de vensters: photos | clock | flipclock | sense | black | none.
            'mode' => $clockPoint->album_mode ?? 'none',
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

    private function screenClaimStatus(ScreenClaim $claim): JsonResponse
    {
        if ($claim->status === ScreenClaimStatus::Pending && $claim->expires_at?->isPast()) {
            return response()->json(['status' => 'expired']);
        }

        if ($claim->status !== ScreenClaimStatus::Confirmed) {
            return response()->json([
                'status' => $claim->status->value,
                'reason' => $claim->denied_reason,
            ]);
        }

        $confirmedAt = $claim->confirmed_at;
        $screen = $claim->screen;
        if ($confirmedAt === null
            || $confirmedAt->lt(now()->subMinutes(10))
            || $claim->device_token === null
            || ! $screen instanceof Screen
            || $screen->unit_id === null) {
            return response()->json(['error' => 'claim_not_found'], 404);
        }

        $screen->loadMissing(['unit.location', 'sensePoint']);

        return response()->json([
            'status' => ScreenClaimStatus::Confirmed->value,
            'device_token' => $claim->device_token,
            'display_id' => $screen->display_id,
            'display_secret' => $screen->display_secret,
            'clock_point_name' => $screen->unit?->localizedName(),
            'location_name' => $screen->unit?->location?->localizedName(),
            ...$this->screenPingBody($screen),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function screenPingBody(Screen $screen): array
    {
        $screen->loadMissing(['unit.location', 'sensePoint']);

        return [
            'state' => 'active',
            'clock_point_name' => $screen->unit?->localizedName(),
            'location_name' => $screen->unit?->location?->localizedName(),
            'server_time' => now()->toIso8601String(),
            'server_unix' => now()->getTimestamp(),
            'display_on_from' => $screen->display_on_from !== null
                ? substr((string) $screen->display_on_from, 0, 5) : null,
            'display_on_until' => $screen->display_on_until !== null
                ? substr((string) $screen->display_on_until, 0, 5) : null,
            'sound_enabled' => (bool) ($screen->display_sound_enabled ?? true),
            'album' => $this->screenAlbum($screen),
            'rotation_seconds' => ScreenQr::windowSeconds($screen),
            'offline_warn_hours' => (int) config('time.display_offline_warn_hours', 24),
            'offline_block_hours' => (int) config('time.display_offline_block_hours', 168),
            'portal_url_template' => route('public.unit-portal', '__TOKEN__'),
            'sense' => $this->screenSense($screen),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function screenSense(Screen $screen): array
    {
        $point = $screen->sensePoint;
        if (! $screen->has_sense || $point === null) {
            return ['enabled' => false];
        }

        $locale = in_array($point->display_locale, config('locales.supported'), true)
            ? (string) $point->display_locale
            : (string) config('locales.default', 'nl');

        return [
            'enabled' => true,
            'locale' => $locale,
            'words' => SenseScreenWords::forLocale($locale),
            'temperature_low' => $point->temperature_low,
            'temperature_high' => $point->temperature_high,
            'humidity_low' => $point->humidity_low,
            'humidity_high' => $point->humidity_high,
            'co2_high' => $point->co2_high,
            'voc_high' => $point->voc_high,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function screenAlbum(Screen $screen): array
    {
        $windows = [];
        foreach ([
            [$screen->album1_from, $screen->album1_until],
            [$screen->album2_from, $screen->album2_until],
        ] as [$from, $until]) {
            if ($from !== null && $until !== null) {
                $windows[] = [substr((string) $from, 0, 5), substr((string) $until, 0, 5)];
            }
        }

        $images = ClockDisplayImage::query()
            ->where('tenant_id', $screen->tenant_id)
            ->where('screen_id', $screen->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(ClockDisplayImage::MAX_PER_SCOPE)
            ->get();

        $albumDays = (int) ($screen->album_days ?? 31);
        $version = substr(md5(
            $images->map(fn (ClockDisplayImage $i) => $i->id.'@'.$i->updated_at?->getTimestamp())->implode(',')
            .'|'.json_encode($windows).'|'.$albumDays
        ), 0, 12);

        return [
            'version' => $version,
            'mode' => $screen->album_mode ?? 'none',
            'windows' => $windows,
            'days' => $albumDays,
            'images' => $images
                ->map(fn (ClockDisplayImage $i) => ['id' => $i->id, 'url' => $i->publicUrl()])
                ->values()
                ->all(),
        ];
    }
}
