<?php

return [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    'enabled' => filled(env('STRIPE_SECRET')),
    // Checkout zichtbaar wanneer secret + price IDs gezet zijn (zie StripeCheckoutService).
    'offer_checkout' => (bool) env('STRIPE_OFFER_CHECKOUT', true),
    'success_path' => env('STRIPE_SUCCESS_PATH', '/subscription'),
    'cancel_path' => env('STRIPE_CANCEL_PATH', '/subscription'),
    // Vereist Stripe Tax actief in het dashboard (+ BE-registratie,
    // product-tax-code, tax_behavior op Prices) — anders faalt Checkout.
    'automatic_tax' => (bool) env('STRIPE_AUTOMATIC_TAX', false),
    'price_ids' => [
        'winprox_5'        => env('STRIPE_PRICE_WINPROX_5'),
        'winprox_5_time'   => env('STRIPE_PRICE_WINPROX_5_TIME'),
        'winprox_10'       => env('STRIPE_PRICE_WINPROX_10'),
        'winprox_10_time'  => env('STRIPE_PRICE_WINPROX_10_TIME'),
        'winprox_25'       => env('STRIPE_PRICE_WINPROX_25'),
        'winprox_25_time'  => env('STRIPE_PRICE_WINPROX_25_TIME'),
        'winprox_50'       => env('STRIPE_PRICE_WINPROX_50'),
        'winprox_50_time'  => env('STRIPE_PRICE_WINPROX_50_TIME'),
        'winprox_100'      => env('STRIPE_PRICE_WINPROX_100'),
        'winprox_100_time' => env('STRIPE_PRICE_WINPROX_100_TIME'),
        // Checkmate: per-seat prijs (€5/licentie); quantity = aantal seats.
        'checkmate'        => env('STRIPE_PRICE_WINPROX_CHECKMATE'),
        // Legacy maandtiers (bestaande abonnees / oude Price IDs).
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
