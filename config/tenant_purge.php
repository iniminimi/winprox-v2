<?php

return [
    /*
    | Cool-down voor betalende tenants na e-mailbevestiging (dagen).
    */
    'paid_cooldown_days' => (int) env('TENANT_PURGE_PAID_COOLDOWN_DAYS', 7),

    /*
    | Dagen na zelfregistratie zonder e-mailverificatie: account volledig wissen.
    */
    'unverified_registration_days' => (int) env('TENANT_PURGE_UNVERIFIED_REGISTRATION_DAYS', 7),

    /*
    | Dagen na einde proefperiode: waarschuwingsmail + planning auto-purge.
    */
    'expired_trial_warning_days' => (int) env('TENANT_PURGE_EXPIRED_TRIAL_WARNING_DAYS', 7),

    /*
    | Dagen na einde proefperiode: automatische purge (zonder abonnement).
    */
    'expired_trial_purge_days' => (int) env('TENANT_PURGE_EXPIRED_TRIAL_PURGE_DAYS', 14),

    /*
    | Reminder naar admins zoveel dagen vóór geplande purge.
    */
    'reminder_days_before' => (int) env('TENANT_PURGE_REMINDER_DAYS_BEFORE', 2),

    /*
    | Bewaartermijn SQL-snapshot zonder media na uitvoering (dagen).
    */
    'backup_retention_days' => (int) env('TENANT_PURGE_BACKUP_RETENTION_DAYS', 30),

    /*
    | Geldigheid van de e-mailbevestigingslink (uren).
    */
    'confirm_token_hours' => (int) env('TENANT_PURGE_CONFIRM_TOKEN_HOURS', 48),

    /*
    | Relatief pad onder storage/app voor snapshots (buiten webroot).
    */
    'backup_directory' => 'tenant-purge-backups',

    /*
    | Max. foute wachtwoordpogingen bij purge; daarna uitloggen.
    */
    'password_max_attempts' => (int) env('TENANT_PURGE_PASSWORD_MAX_ATTEMPTS', 3),

    /*
    | Interne melding wanneer een account-purge gepland wordt.
    */
    'ops_notification_email' => env(
        'TENANT_PURGE_OPS_NOTIFICATION_EMAIL',
        env('WINPROX_NEW_TENANT_NOTIFICATION_EMAIL', 'info@winprox.app'),
    ),
];
