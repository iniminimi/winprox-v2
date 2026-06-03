<?php

return [
    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 30),
    'trial_plan_facility' => 'pro',
    'paid_expiry_grace_days' => (int) env('BILLING_PAID_GRACE_DAYS', 7),
    'allow_tenant_self_activation' => (bool) env('BILLING_ALLOW_SELF_ACTIVATION', true),
    'subscription_period_days' => (int) env('BILLING_SUBSCRIPTION_PERIOD_DAYS', 30),
    'contact_email' => env('BILLING_CONTACT_EMAIL', 'info@winprox.app'),

    'plans' => [
        'micro' => [
            'label_key' => 'subscription.plans.micro.name',
            'units_limit' => 10,
            'users_limit' => 1,
            'subscription_period_days' => 365,
            'self_activate' => true,
        ],
        'starter' => [
            'label_key' => 'subscription.plans.starter.name',
            'units_limit' => 25,
            'users_limit' => 2,
            'subscription_period_days' => 30,
            'self_activate' => true,
        ],
        'pro' => [
            'label_key' => 'subscription.plans.pro.name',
            'units_limit' => 100,
            'users_limit' => 5,
            'subscription_period_days' => 30,
            'self_activate' => true,
        ],
        'business' => [
            'label_key' => 'subscription.plans.business.name',
            'units_limit' => 300,
            'users_limit' => 15,
            'subscription_period_days' => 30,
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
