<?php

return [
    /*
    | Promo-video keys (basename in public/video/{locale}/). Landing slugs + legacy keys.
    */
    'promo_video_keys' => [
        'issue',
        'task',
        'users_edit_qr',
        'issue_approve_briefing',
        'unit_categorie_gps_allow_issue_print_qr',
        'hospitality',
        'industry',
        'healthcare',
        'government',
        'realestate',
        'work-on-location',
        'checkmate',
        'prikklok',
    ],

    /*
    | Commerciële huurprijs van het klokscherm (marketing; geen Stripe-plan).
    | Zelfde bedrag op landing, welcome en pricing via :price-placeholder.
    */
    'clock_rent_monthly_eur' => 10,

    /*
    | Tijdelijk verborgen promo-video's per locale (basename). Leegmaken na vervanging.
    */
    'promo_hidden_videos' => [],

    /*
    | Marktdomeinen naast winprox.app. Hostnamen zijn array-sleutels; niet via
    | config('…winprox.be') lezen (de punt splitst de sleutel).
    | live=false: apex én www 302 naar het app-domein, geen hreflang.
    | live=true: marketing blijft op het domein; app-paden 302.
    | permanent_redirects: die redirects en www → apex worden 301.
    | Standaard uit, zodat een deploy vóór DNS niets openzet.
    */
    'domains' => [
        'app_host' => env('MARKETING_APP_HOST', 'winprox.app'),
        'markets' => [
            env('MARKETING_BE_HOST', 'winprox.be') => [
                'live' => filter_var(env('MARKETING_BE_LIVE', false), FILTER_VALIDATE_BOOLEAN),
                'permanent_redirects' => filter_var(env('MARKETING_BE_PERMANENT', false), FILTER_VALIDATE_BOOLEAN),
                'locales' => ['nl', 'fr'],
                'default_locale' => 'nl',
                'regions' => ['nl' => 'BE', 'fr' => 'BE'],
            ],
            env('MARKETING_NL_HOST', 'winprox.nl') => [
                'live' => filter_var(env('MARKETING_NL_LIVE', false), FILTER_VALIDATE_BOOLEAN),
                'permanent_redirects' => filter_var(env('MARKETING_NL_PERMANENT', false), FILTER_VALIDATE_BOOLEAN),
                'locales' => ['nl'],
                'default_locale' => 'nl',
                'regions' => ['nl' => 'NL'],
            ],
        ],
    ],
];
