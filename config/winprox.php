<?php

return [
    'helpdesk_email' => env('WINPROX_HELPDESK_EMAIL', 'helpdesk@winprox.app'),
    'new_tenant_notification_email' => env('WINPROX_NEW_TENANT_NOTIFICATION_EMAIL', 'info@winprox.app'),
    'municipal_promo_email_from' => [
        'address' => env('WINPROX_MUNICIPAL_PROMO_EMAIL_FROM', 'dominique.schaepdrijver@winprox.app'),
        'name' => env('WINPROX_MUNICIPAL_PROMO_EMAIL_FROM_NAME', 'Dominique Schaepdrijver'),
    ],
    // Promo campaigns send via Amazon SES by default (not Cloud86 SMTP).
    'promo_mailer' => env('WINPROX_PROMO_MAILER', 'ses'),
    // Token for SES SNS bounce/complaint webhook (?token=). Empty = webhook disabled.
    'ses_sns_token' => env('WINPROX_SES_SNS_TOKEN', ''),
    // SES: 1s is enough. Use 20 only when WINPROX_PROMO_MAILER=municipal_promo (Cloud86 ~250/hour).
    'promo_campaign_email_min_interval_seconds' => (int) env('WINPROX_PROMO_EMAIL_MIN_INTERVAL_SECONDS', 1),
    // Emergency kill switch for bulk promo sending (campaign UI pause + queued jobs).
    'promo_campaign_emails_enabled' => filter_var(env('WINPROX_PROMO_EMAILS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
