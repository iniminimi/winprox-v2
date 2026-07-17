<?php

return [
    'helpdesk_email' => env('WINPROX_HELPDESK_EMAIL', 'helpdesk@winprox.app'),
    'new_tenant_notification_email' => env('WINPROX_NEW_TENANT_NOTIFICATION_EMAIL', 'info@winprox.app'),
    'municipal_promo_email_from' => [
        'address' => env('WINPROX_MUNICIPAL_PROMO_EMAIL_FROM', 'dominique.schaepdrijver@winprox.app'),
        'name' => env('WINPROX_MUNICIPAL_PROMO_EMAIL_FROM_NAME', 'Dominique Schaepdrijver'),
    ],
    // Cloud86: max ~250 outgoing mails/hour per hosting plan. 20s ≈ 180/hour (headroom for other mail).
    'promo_campaign_email_min_interval_seconds' => (int) env('WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS', 20),
];
