<?php

namespace App\Support\Auth;

/**
 * Korte toestelnaam uit de User-Agent, voor de QR-bevestiging en het auditlog.
 */
final class ComputerLabel
{
    public static function fromUserAgent(?string $userAgent): string
    {
        $agent = trim((string) $userAgent);
        if ($agent === '') {
            return __('auth.phone.unknown_device');
        }

        $device = __('auth.phone.device_computer');
        if (str_contains($agent, 'iPhone')) {
            $device = 'iPhone';
        } elseif (str_contains($agent, 'iPad')) {
            $device = 'iPad';
        } elseif (str_contains($agent, 'Android')) {
            $device = 'Android';
        } elseif (str_contains($agent, 'Windows')) {
            $device = 'Windows';
        } elseif (str_contains($agent, 'Mac OS')) {
            $device = 'Mac';
        }

        $browser = __('auth.phone.device_browser');
        if (str_contains($agent, 'Edg/')) {
            $browser = 'Edge';
        } elseif (str_contains($agent, 'Chrome/')) {
            $browser = 'Chrome';
        } elseif (str_contains($agent, 'Firefox/')) {
            $browser = 'Firefox';
        } elseif (str_contains($agent, 'Safari/')) {
            $browser = 'Safari';
        }

        return __('auth.phone.device_label', ['browser' => $browser, 'device' => $device]);
    }
}
