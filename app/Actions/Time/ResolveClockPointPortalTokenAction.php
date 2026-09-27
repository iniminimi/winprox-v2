<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\ClockPointQrToken;
use App\Support\Time\ClockDisplayQr;
use App\Support\Time\ClockPointPortalTokenResolution;

class ResolveClockPointPortalTokenAction
{
    public function handle(string $token): ClockPointPortalTokenResolution
    {
        $token = trim($token);
        if ($token === '') {
            return ClockPointPortalTokenResolution::notFound();
        }

        // Dynamische scherm-token {display_id:24}{dyn:40} — enige 64-teken
        // variant; statische/history QR's zijn altijd 40 tekens.
        if (strlen($token) === ClockDisplayQr::TOKEN_LENGTH) {
            return $this->resolveDisplayToken($token);
        }

        $clockPoint = ClockPoint::withoutGlobalScope('tenant')
            ->where('qr_token', $token)
            ->first();

        if ($clockPoint !== null) {
            return ClockPointPortalTokenResolution::current($clockPoint);
        }

        $historyToken = ClockPointQrToken::withoutGlobalScope('tenant')
            ->where('qr_token', $token)
            ->with('clockPoint')
            ->first();

        if ($historyToken === null || $historyToken->clockPoint === null) {
            return ClockPointPortalTokenResolution::notFound();
        }

        if ($historyToken->isInGrace()) {
            return ClockPointPortalTokenResolution::grace($historyToken->clockPoint, $historyToken);
        }

        return ClockPointPortalTokenResolution::blocked($historyToken->clockPoint, $historyToken);
    }

    private function resolveDisplayToken(string $token): ClockPointPortalTokenResolution
    {
        $clockPoint = ClockPoint::withoutGlobalScope('tenant')
            ->where('display_id', substr($token, 0, ClockDisplayQr::DISPLAY_ID_LENGTH))
            ->first();

        if ($clockPoint === null) {
            return ClockPointPortalTokenResolution::notFound();
        }

        if (! ClockDisplayQr::matches($clockPoint, substr($token, ClockDisplayQr::DISPLAY_ID_LENGTH))) {
            // Verlopen dynamische token (oude foto/screenshot) of vervalste
            // dyn: blocked-semantiek → gelogd als qr_blocked door de portal.
            return ClockPointPortalTokenResolution::blockedDisplay($clockPoint);
        }

        return ClockPointPortalTokenResolution::current($clockPoint);
    }
}
