<?php

/**
 * Billing — WinProx (jaar, units) + optionele Time-prikklok + Corporate.
 *
 * Publieke catalogus: winprox_10 / winprox_50 / winprox_100 + corporate.
 * Time (prikklok) is een self-activate plan-variant (*_time): dezelfde units,
 * time_module aan, zelfde jaarperiode. Maandprijs × 12 op dezelfde factuur.
 *
 * Legacy facility_* blijft in config voor bestaande abonnees (geen catalogus,
 * geen self-activate). Limits/entitlements blijven werken.
 *
 *  - Units bepalen de schaal; documenten = units (1 per unit).
 *  - Seats = actieve login-users (admin + medewerker) + actieve workers (1:1 met units per tier).
 *  - Locaties: altijd onbeperkt.
 *  - Foto's: altijd onbeperkt.
 *  - Time: optionele prikklok (niet inbegrepen in WinProx; wél in de proefperiode).
 *  - IoT + ESG + API: uitsluitend Corporate.
 *  - Trial: tot 50 units en 50 plaatsen, Time inbegrepen, geen IoT/ESG/API.
 *  - Corporate: afgesproken units via `tenants.billing_units_cap` (superuser).
 */

$winproxShared = static function (int $units): array {
    return [
        'units_limit'            => $units,
        'locations_limit'        => null,
        'users_limit'            => null,
        'seats_limit'            => $units,
        'documents_org_limit'    => $units,
        'photos_org_limit'       => null,
        'documents_per_unit'     => null,
        'announcements_per_unit' => null,
        'includes_facility'      => true,
        'esg_module'             => false,
        'iot_module'             => false,
        'api_access'             => false,
        'csv_workers_import'     => true,
        'csv_units_import'       => true,
        'subscription_period_days' => 365,
        'self_activate'          => true,
    ];
};

$winproxPair = static function (int $units, int $timeMonthlyEur) use ($winproxShared): array {
    $baseKey = 'winprox_'.$units;
    $timeKey = 'winprox_'.$units.'_time';
    $shared = $winproxShared($units);

    return [
        $baseKey => array_merge($shared, [
            'label_key'         => 'subscription.plans.'.$baseKey.'.name',
            'time_module'       => false,
            'public_catalog'    => true,
            'time_variant'      => $timeKey,
            'time_monthly_eur'  => $timeMonthlyEur,
        ]),
        $timeKey => array_merge($shared, [
            'label_key'         => 'subscription.plans.'.$timeKey.'.name',
            'time_module'       => true,
            'public_catalog'    => false,
            'time_variant'      => null,
            'time_monthly_eur'  => $timeMonthlyEur,
        ]),
    ];
};

$legacyFacility = static function (int $units, bool $iotEsg): array {
    return [
        'label_key'              => 'subscription.plans.facility_'.$units.'.name',
        'units_limit'            => $units,
        'locations_limit'        => null,
        'users_limit'            => null,
        'seats_limit'            => null,
        'documents_org_limit'    => $units,
        'photos_org_limit'       => null,
        'documents_per_unit'     => null,
        'announcements_per_unit' => null,
        'includes_facility'      => true,
        'time_module'            => true,
        'esg_module'             => $iotEsg,
        'iot_module'             => $iotEsg,
        'api_access'             => false,
        'csv_workers_import'     => true,
        'csv_units_import'       => true,
        'subscription_period_days' => 30,
        'self_activate'          => false,
        'public_catalog'         => false,
    ];
};

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),
    'trial_plan_facility' => 'trial',
    'paid_expiry_grace_days' => (int) env('BILLING_PAID_GRACE_DAYS', 7),
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'api_rate_limits' => [
        'trial'            => ['max_attempts' => 30,    'decay_seconds' => 60],
        'winprox_10'       => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_10_time'  => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_50'       => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_50_time'  => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_100'      => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_100_time' => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_10'      => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_25'      => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_50'      => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_100'     => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_250'     => ['max_attempts' => 200,   'decay_seconds' => 60],
        'facility_500'     => ['max_attempts' => 200,   'decay_seconds' => 60],
        'facility_1000'    => ['max_attempts' => 200,   'decay_seconds' => 60],
        'corporate'        => ['max_attempts' => 10000, 'decay_seconds' => 60],
    ],

    // Trial: tot 50 units (= WinProx 50), Time inbegrepen, geen IoT/ESG/API.
    'trial' => [
        'units_limit'            => 50,
        'locations_limit'        => null,
        'users_limit'            => null,
        'seats_limit'            => 50,
        'documents_org_limit'    => 50,
        'photos_org_limit'       => null,
        'documents_per_unit'     => null,
        'announcements_per_unit' => null,
        'includes_facility'      => true,
        'time_module'            => true,
        'esg_module'             => false,
        'iot_module'             => false,
        'api_access'             => false,
        'csv_workers_import'     => true,
        'csv_units_import'       => true,
    ],

    'plans' => array_merge(
        $winproxPair(10, 29),
        $winproxPair(50, 39),
        $winproxPair(100, 49),
        [
            // Legacy maandtiers — grandfather, niet in de catalogus.
            'facility_10' => $legacyFacility(10, false),
            'facility_25' => $legacyFacility(25, false),
            'facility_50' => $legacyFacility(50, false),
            'facility_100' => $legacyFacility(100, false),
            'facility_250' => $legacyFacility(250, true),
            'facility_500' => $legacyFacility(500, true),
            'facility_1000' => $legacyFacility(1000, true),

            'corporate' => [
                'label_key'              => 'subscription.plans.corporate.name',
                'units_limit'            => null,
                'locations_limit'        => null,
                'users_limit'            => null,
                'seats_limit'            => null,
                'documents_org_limit'    => null,
                'photos_org_limit'       => null,
                'documents_per_unit'     => null,
                'announcements_per_unit' => null,
                'includes_facility'      => true,
                'time_module'            => true,
                'esg_module'             => true,
                'iot_module'             => true,
                'api_access'             => true,
                'csv_workers_import'     => true,
                'csv_units_import'       => true,
                'subscription_period_days' => 30,
                'self_activate'          => false,
                'public_catalog'         => true,
            ],
        ],
    ),
];
