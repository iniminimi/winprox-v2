<?php

return [
    'host' => env('IMAP_HOST'),
    'port' => env('IMAP_PORT', 993),
    'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
    'username' => env('IMAP_USERNAME'),
    'password' => env('IMAP_PASSWORD'),
    'protocol' => env('IMAP_PROTOCOL', 'imap'),
    'authentication' => env('IMAP_AUTHENTICATION'),

    // Promo bounce mailbox (Dominique) — separate from contact-reply IMAP.
    'promo' => [
        'host' => env('WINPROX_PROMO_IMAP_HOST', env('IMAP_HOST')),
        'port' => env('WINPROX_PROMO_IMAP_PORT', env('IMAP_PORT', 993)),
        'encryption' => env('WINPROX_PROMO_IMAP_ENCRYPTION', env('IMAP_ENCRYPTION', 'ssl')),
        'username' => env(
            'WINPROX_PROMO_IMAP_USERNAME',
            env('WINPROX_MUNICIPAL_PROMO_EMAIL_FROM', 'dominique.schaepdrijver@winprox.app'),
        ),
        'password' => env('WINPROX_PROMO_IMAP_PASSWORD', env('IMAP_PASSWORD', env('MAIL_PASSWORD'))),
        'protocol' => env('WINPROX_PROMO_IMAP_PROTOCOL', env('IMAP_PROTOCOL', 'imap')),
        'authentication' => env('WINPROX_PROMO_IMAP_AUTHENTICATION', env('IMAP_AUTHENTICATION')),
    ],
];
