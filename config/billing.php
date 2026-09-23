<?php

/**
 * Billing — WinProx (maand, licenties) + Corporate.
 *
 * Publieke catalogus: winprox_5 / winprox_10 / winprox_25 / winprox_50 + corporate.
 * Time (prikklok) is inbegrepen. CIAO (RSZ) op aanvraag, zonder extra SKU.
 * IoT + ESG + API: uitsluitend Corporate. 100+ licenties = Corporate.
 *
 *  - Licenties (seats): 5 / 10 / 25 / 50. Units = licenties × 10
 *    (50 / 100 / 250 / 500). Documenten: 10 / 20 / 30 / 40.
 *  - Locaties en foto's: onbeperkt (elke locatie krijgt een "Hele locatie"-unit).
 *  - Collega + prikklok-profiel = 1 licentie.
 *  - Trial = zelfde limieten als winprox_5.
 *  - Corporate: afgesproken units via `tenants.billing_units_cap` (superuser).
 *  - Legacy facility_* en winprox_100 / *_time blijven in config (geen catalogus).
 */

$winproxShared = static function (int $seats, int $units, int $documents): array {
    return [
        'units_limit'            => $units,
        'locations_limit'        => null,
        'users_limit'            => null,
        'seats_limit'            => $seats,
        'documents_org_limit'    => $documents,
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
        'subscription_period_days' => 30,
        'self_activate'          => true,
    ];
};

$winproxPlan = static function (
    int $seats,
    int $units,
    int $documents,
    int $packageMonthlyEur,
    bool $public,
) use ($winproxShared): array {
    $baseKey = 'winprox_'.$seats;
    $timeKey = 'winprox_'.$seats.'_time';
    $shared = $winproxShared($seats, $units, $documents);

    return [
        $baseKey => array_merge($shared, [
            'label_key'            => 'subscription.plans.'.$baseKey.'.name',
            'public_catalog'       => $public,
            'time_variant'         => $timeKey,
            'package_monthly_eur'  => $packageMonthlyEur,
        ]),
        $timeKey => array_merge($shared, [
            'label_key'            => 'subscription.plans.'.$timeKey.'.name',
            'public_catalog'       => false,
            'time_variant'         => null,
            'package_monthly_eur'  => $packageMonthlyEur,
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
    // Tenant kiest formule zelf; Stripe Checkout wanneer geconfigureerd.
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'api_rate_limits' => [
        'trial'            => ['max_attempts' => 30,    'decay_seconds' => 60],
        'winprox_5'        => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_5_time'   => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_10'       => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_10_time'  => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_25'       => ['max_attempts' => 60,    'decay_seconds' => 60],
        'winprox_25_time'  => ['max_attempts' => 60,    'decay_seconds' => 60],
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

    // Trial = winprox_5 limieten: 5 licenties, 50 units, 10 documenten.
    'trial' => [
        'units_limit'            => 50,
        'locations_limit'        => null,
        'users_limit'            => null,
        'seats_limit'            => 5,
        'documents_org_limit'    => 10,
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
        $winproxPlan(5, 50, 10, 59, true),
        $winproxPlan(10, 100, 20, 99, true),
        $winproxPlan(25, 250, 30, 199, true),
        $winproxPlan(50, 500, 40, 349, true),
        $winproxPlan(100, 1000, 100, 0, false),
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
                'subscription_period_days' => 365,
                'self_activate'          => false,
                'public_catalog'         => true,
            ],
        ],
    ),
];
