<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'enabled' => filled(env('STRIPE_SECRET')),
    'success_path' => env('STRIPE_SUCCESS_PATH', '/subscription'),
    'cancel_path' => env('STRIPE_CANCEL_PATH', '/subscription'),
    'price_ids' => [
        'starter' => env('STRIPE_PRICE_STARTER'),
        'pro' => env('STRIPE_PRICE_PRO'),
        'business' => env('STRIPE_PRICE_BUSINESS'),
    ],
];
