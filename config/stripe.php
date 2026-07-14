<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'enabled' => filled(env('STRIPE_SECRET')),
    'success_path' => env('STRIPE_SUCCESS_PATH', '/subscription'),
    'cancel_path' => env('STRIPE_CANCEL_PATH', '/subscription'),
    'price_ids' => [
        'time' => env('STRIPE_PRICE_TIME'),
        'facility' => env('STRIPE_PRICE_FACILITY'),
        'corporate' => env('STRIPE_PRICE_CORPORATE'),
    ],
];
