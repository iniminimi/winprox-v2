<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),
    'trial_plan_facility' => 'trial',
    'paid_expiry_grace_days' => (int) env('BILLING_PAID_GRACE_DAYS', 7),
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'api_rate_limits' => [
        'trial' => ['max_attempts' => 30, 'decay_seconds' => 60],
        'micro' => ['max_attempts' => 60, 'decay_seconds' => 60],
        'starter' => ['max_attempts' => 100, 'decay_seconds' => 60],
        'pro' => ['max_attempts' => 200, 'decay_seconds' => 60],
        'business' => ['max_attempts' => 500, 'decay_seconds' => 60],
        'enterprise' => ['max_attempts' => 10000, 'decay_seconds' => 60],
    ],

    'trial' => [
        'units_limit' => 25,
        'users_limit' => 2,
        'announcements_per_unit' => 4,
        'documents_per_unit' => 4,
        'documents_org_limit' => 10,
    ],

    'plans' => [
        'micro' => [
            'label_key' => 'subscription.plans.micro.name',
            'units_limit' => 10,
            'users_limit' => 1,
            'announcements_per_unit' => 2,
            'documents_per_unit' => 2,
            'documents_org_limit' => 10,
            'subscription_period_days' => 365,
            'self_activate' => true,
        ],
        'starter' => [
            'label_key' => 'subscription.plans.starter.name',
            'units_limit' => 25,
            'users_limit' => 2,
            'announcements_per_unit' => 4,
            'documents_per_unit' => 4,
            'documents_org_limit' => 20,
            'subscription_period_days' => 30,
            'self_activate' => true,
        ],
        'pro' => [
            'label_key' => 'subscription.plans.pro.name',
            'units_limit' => 100,
            'users_limit' => 5,
            'announcements_per_unit' => 10,
            'documents_per_unit' => 6,
            'documents_org_limit' => 40,
            'subscription_period_days' => 30,
            'self_activate' => true,
        ],
        'business' => [
            'label_key' => 'subscription.plans.business.name',
            'units_limit' => 300,
            'users_limit' => 15,
            'announcements_per_unit' => 20,
            'documents_per_unit' => 8,
            'documents_org_limit' => 80,
            'subscription_period_days' => 30,
            'self_activate' => true,
        ],
        'enterprise' => [
            'label_key' => 'subscription.plans.enterprise.name',
            'units_limit' => null,
            'users_limit' => null,
            'announcements_per_unit' => null,
            'documents_per_unit' => null,
            'documents_org_limit' => null,
            'self_activate' => false,
        ],
    ],

    'modules' => [
        'esg' => [
            'label_key' => 'subscription.modules.esg.name',
            'minimum_plan' => 'pro',
        ],
        'time' => [
            'label_key' => 'subscription.modules.time.name',
            'minimum_plan' => 'starter',
        ],
    ],

    'bundles' => [
        'time' => [
            'label_key' => 'subscription.products.time.name',
            'base_plan' => 'micro',
        ],
    ],
];
