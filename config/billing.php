<?php

/**
 * Billing-configuratie — WinProx Facility (7 standaard tiers + Corporate)
 *
 * Tier-logica (definitief):
 *  - Units bepalen de schaal; documenten = units (1 per unit).
 *  - Locaties en gebruikers: altijd onbeperkt.
 *  - Foto's (bij meldingen/taken): altijd onbeperkt — worden client-side verkleind.
 *  - IoT + ESG: ingeschakeld vanaf 250 units (en hoger).
 *  - API & webhooks: uitsluitend Corporate.
 *  - Time: inbegrepen in alle tiers en trial (geen apart product).
 *  - Trial: max. 100 units (= zelfde als 100-tier, gratis/tijdelijk, geen IoT/ESG/API).
 *  - Corporate: geen vaste unit-cap, geen vaste Stripe-prijs, prijs op maat.
 *    Corporate-plan-key = 'corporate' in DB. billingLimitValue() geeft null voor corporate.
 *    Corporate wordt ook voor <1.000 units gebruikt wanneer API/webhooks/integraties/SLA vereist zijn.
 */
return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),
    'trial_plan_facility' => 'trial',
    'paid_expiry_grace_days' => (int) env('BILLING_PAID_GRACE_DAYS', 7),
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'api_rate_limits' => [
        'trial'          => ['max_attempts' => 30,    'decay_seconds' => 60],
        'facility_10'    => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_25'    => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_50'    => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_100'   => ['max_attempts' => 60,    'decay_seconds' => 60],
        'facility_250'   => ['max_attempts' => 200,   'decay_seconds' => 60],
        'facility_500'   => ['max_attempts' => 200,   'decay_seconds' => 60],
        'facility_1000'  => ['max_attempts' => 200,   'decay_seconds' => 60],
        'corporate'      => ['max_attempts' => 10000, 'decay_seconds' => 60],
    ],

    // Trial: tot 100 units, geen IoT/ESG/API, Time inbegrepen.
    'trial' => [
        'units_limit'            => 100,
        'locations_limit'        => null,
        'users_limit'            => null,
        'documents_org_limit'    => 100,  // = units_limit
        'photos_org_limit'       => null, // onbeperkt
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

    'plans' => [

        // --- Standaard tiers (vaste Stripe-prijs, self-activate) ---

        'facility_10' => [
            'label_key'              => 'subscription.plans.facility_10.name',
            'units_limit'            => 10,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 10,   // = units_limit
            'photos_org_limit'       => null, // onbeperkt
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
        ],

        'facility_25' => [
            'label_key'              => 'subscription.plans.facility_25.name',
            'units_limit'            => 25,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 25,
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
        ],

        'facility_50' => [
            'label_key'              => 'subscription.plans.facility_50.name',
            'units_limit'            => 50,
            'locations_limit'        => null,
            'users_limit'            => null,
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
            'subscription_period_days' => 30,
            'self_activate'          => true,
        ],

        'facility_100' => [
            'label_key'              => 'subscription.plans.facility_100.name',
            'units_limit'            => 100,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 100,
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
        ],

        // IoT + ESG ingeschakeld vanaf 250 units.
        'facility_250' => [
            'label_key'              => 'subscription.plans.facility_250.name',
            'units_limit'            => 250,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 250,
            'photos_org_limit'       => null,
            'documents_per_unit'     => null,
            'announcements_per_unit' => null,
            'includes_facility'      => true,
            'time_module'            => true,
            'esg_module'             => true,
            'iot_module'             => true,
            'api_access'             => false,
            'csv_workers_import'     => true,
            'csv_units_import'       => true,
            'subscription_period_days' => 30,
            'self_activate'          => true,
        ],

        'facility_500' => [
            'label_key'              => 'subscription.plans.facility_500.name',
            'units_limit'            => 500,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 500,
            'photos_org_limit'       => null,
            'documents_per_unit'     => null,
            'announcements_per_unit' => null,
            'includes_facility'      => true,
            'time_module'            => true,
            'esg_module'             => true,
            'iot_module'             => true,
            'api_access'             => false,
            'csv_workers_import'     => true,
            'csv_units_import'       => true,
            'subscription_period_days' => 30,
            'self_activate'          => true,
        ],

        'facility_1000' => [
            'label_key'              => 'subscription.plans.facility_1000.name',
            'units_limit'            => 1000,
            'locations_limit'        => null,
            'users_limit'            => null,
            'documents_org_limit'    => 1000,
            'photos_org_limit'       => null,
            'documents_per_unit'     => null,
            'announcements_per_unit' => null,
            'includes_facility'      => true,
            'time_module'            => true,
            'esg_module'             => true,
            'iot_module'             => true,
            'api_access'             => false,
            'csv_workers_import'     => true,
            'csv_units_import'       => true,
            'subscription_period_days' => 30,
            'self_activate'          => true,
        ],

        // --- Corporate: geen vaste unit-cap, geen vaste Stripe-prijs, prijs op maat ---
        // Alleen API & webhooks op Corporate.
        // documents_org_limit = null (= dynamisch op basis van afgesproken units, niet technisch begrensd).
        'corporate' => [
            'label_key'              => 'subscription.plans.corporate.name',
            'units_limit'            => null,
            'locations_limit'        => null,
            'users_limit'            => null,
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
            'self_activate'          => false, // corporate = manual/custom pricing
        ],
    ],
];
