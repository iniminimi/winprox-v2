<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan_facility' => 'starter',
    'paid_expiry_grace_days' => (int) env('BILLING_PAID_GRACE_DAYS', 7),
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 365),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'plans' => [
        'starter' => [
            'label_key' => 'subscription.plans.starter.name',
            'units_limit' => 25,
            'users_limit' => 5,
            'self_activate' => true,
        ],
        'pro' => [
            'label_key' => 'subscription.plans.pro.name',
            'units_limit' => 75,
            'users_limit' => 15,
            'self_activate' => true,
        ],
        'business' => [
            'label_key' => 'subscription.plans.business.name',
            'units_limit' => 200,
            'users_limit' => 40,
            'self_activate' => true,
        ],
        'enterprise' => [
            'label_key' => 'subscription.plans.enterprise.name',
            'units_limit' => null,
            'users_limit' => null,
            'self_activate' => false,
        ],
    ],
];
