<?php

/*
 | Publiek QR-portaal (unit-meldingen). Zie WINPROX_RULES.md §7.1.
 */
return [
    'public_report_rate_limit' => [
        'enabled' => (bool) env('PORTAL_REPORT_RATE_LIMIT_ENABLED', true),

        // Per unit + IP (burger via dezelfde QR).
        'per_unit' => [
            'max_attempts' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_UNIT', 5),
            'decay_seconds' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_UNIT_DECAY', 900),
        ],

        // Per tenant + IP (zelfde IP over meerdere units).
        'per_tenant' => [
            'max_attempts' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_TENANT', 20),
            'decay_seconds' => (int) env('PORTAL_REPORT_RATE_LIMIT_PER_TENANT_DECAY', 3600),
        ],
    ],
];
