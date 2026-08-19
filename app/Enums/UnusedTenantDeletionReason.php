<?php

namespace App\Enums;

/** Waarom een nooit-gebruikt account gewist wordt (audit-payload). */
enum UnusedTenantDeletionReason: string
{
    /** Zelfregistratie waarvan het e-mailadres nooit bevestigd is (onderhoudstaak). */
    case UnverifiedRegistration = 'unverified_registration';

    /** Superuser markeert de aanmelding als vals/spam. */
    case SuperuserSpam = 'superuser_spam';
}
