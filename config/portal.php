<?php

/*
 | Publiek QR-portaal (unit-meldingen). Zie WINPROX_RULES.md §7.1.
 */
return [
    'public_report_rate_limit' => [
        'enabled' => (bool) env('PORTAL_REPORT_RATE_LIMIT_ENABLED', true),

        // Korte cooldown tussen twee meldingen (per unit + IP): max 1 melding per interval.
        // 0 = uit. Stopt direct twee meldingen vlak na elkaar.
        'cooldown' => [
            'decay_seconds' => (int) env('PORTAL_REPORT_RATE_LIMIT_COOLDOWN', 180),
        ],

        // Venster per unit + IP (burger via dezelfde QR).
        'per_unit' => [
            'max_attempts' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_UNIT', 5),
            'decay_seconds' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_UNIT_DECAY', 1800),
        ],

        // Per tenant + IP (zelfde IP over meerdere units).
        'per_tenant' => [
            'max_attempts' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_TENANT', 20),
            'decay_seconds' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_TENANT_DECAY', 3600),
        ],
    ],

    'qr_report_email_verification' => [
        'expire_minutes' => (int) env('PORTAL_QR_REPORT_EMAIL_VERIFY_EXPIRE', 60),
        'confirmed_retain_days' => (int) env('PORTAL_QR_REPORT_EMAIL_HOLD_RETAIN_DAYS', 7),
    ],
];
