<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'enabled' => filled(env('STRIPE_SECRET')),
    'success_path' => env('STRIPE_SUCCESS_PATH', '/subscription'),
    'cancel_path' => env('STRIPE_CANCEL_PATH', '/subscription'),
    'price_ids' => [
        'facility_10'   => env('STRIPE_PRICE_FACILITY_10'),
        'facility_25'   => env('STRIPE_PRICE_FACILITY_25'),
        'facility_50'   => env('STRIPE_PRICE_FACILITY_50'),
        'facility_100'  => env('STRIPE_PRICE_FACILITY_100'),
        'facility_250'  => env('STRIPE_PRICE_FACILITY_250'),
        'facility_500'  => env('STRIPE_PRICE_FACILITY_500'),
        'facility_1000' => env('STRIPE_PRICE_FACILITY_1000'),
        // Corporate heeft geen vaste Stripe-prijs — custom pricing per klant.
    ],
];
