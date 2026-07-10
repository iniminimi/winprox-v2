<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;
use App\Models\ClockPointQrToken;
use App\Support\Time\ClockPointPortalTokenResolution;

class ResolveClockPointPortalTokenAction
{
    public function handle(string $token): ClockPointPortalTokenResolution
    {
        $token = trim($token);
        if ($token === '') {
            return ClockPointPortalTokenResolution::notFound();
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
}
