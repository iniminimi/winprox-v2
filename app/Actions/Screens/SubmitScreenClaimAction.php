<?php

declare(strict_types=1);

namespace App\Actions\Screens;

use App\Enums\ScreenClaimStatus;
use App\Models\Screen;
use App\Models\ScreenClaim;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SubmitScreenClaimAction
{
    public function handle(string $code, string $deviceHint, ?string $ip): ScreenClaim
    {
        $normalized = strtoupper(str_replace('-', '', trim($code)));

        $screen = Screen::query()
            ->withoutGlobalScopes()
            ->where('pairing_code', $normalized)
            ->where('pairing_expires_at', '>', now())
            ->first();

        if ($screen === null) {
            throw ValidationException::withMessages([
                'code' => [__('screen.errors.code_invalid')],
            ]);
        }

        $sameDevice = ScreenClaim::query()
            ->withoutGlobalScopes()
            ->where('screen_id', $screen->id)
            ->where('device_hint', $deviceHint)
            ->activePending()
            ->first();

        if ($sameDevice !== null) {
            return $sameDevice;
        }

        ScreenClaim::query()
            ->withoutGlobalScopes()
            ->where('screen_id', $screen->id)
            ->where('status', ScreenClaimStatus::Pending->value)
            ->get()
            ->each(function (ScreenClaim $claim): void {
                $claim->update([
                    'status' => ScreenClaimStatus::Denied,
                    'denied_reason' => 'replaced',
                ]);
            });

        $ttlMinutes = max(1, (int) config('time.display_pairing_ttl_minutes', 10));

        return ScreenClaim::query()->create([
            'tenant_id' => $screen->tenant_id,
            'screen_id' => $screen->id,
            'claim_token' => Str::lower(Str::random(40)),
            'pairing_code' => $normalized,
            'device_hint' => $deviceHint,
            'ip' => $ip,
            'status' => ScreenClaimStatus::Pending,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
