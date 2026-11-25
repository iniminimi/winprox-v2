<?php

return [
    /*
    | Foto's van gesloten meldingen ouder dan X dagen worden verwijderd
    | (melding + taken blijven staan).
    */
    'closed_issue_media_days' => (int) env('RETENTION_CLOSED_ISSUE_MEDIA_DAYS', 365),

    /*
    | Facility-data (meldingen incl. media) van tenants die lang inactief zijn
    | en waarvan trial of betaalperiode voorbij is.
    */
    'inactive_tenant_days' => (int) env('RETENTION_INACTIVE_TENANT_DAYS', 730),

    /*
    | Geslaagde CIAO/RSZ-inzendingen (presence_submissions status=submitted)
    | ouder dan X maanden. Mislukt / wachtrij / overgeslagen blijven staan.
    | Officiële registratie blijft bij de RSZ; WinProx is doorstuurlog.
    */
    'presence_submissions_months' => (int) env('RETENTION_PRESENCE_SUBMISSIONS_MONTHS', 12),

    /*
    | Uurrooster-herstelpunten (vorige diensten van een roosterwijziging).
    */
    'roster_restore_points_days' => (int) env('RETENTION_ROSTER_RESTORE_POINTS_DAYS', 30),

    /*
    | Foto's van het fotoalbum per unit (bestand + rij). De verzendhistoriek blijft.
    */
    'unit_album_photos_days' => (int) env('RETENTION_UNIT_ALBUM_PHOTOS_DAYS', 7),

    /*
    | Foto's bij een afwezigheidsaanvraag. Na absence_photos_months gaan ze in een
    | zip van die maand (nog één maand te downloaden via Abonnement) en verdwijnt
    | het origineel. De aanvraag blijft. Vanaf warn_months waarschuwt het beginscherm.
    */
    'absence_photos_months' => (int) env('RETENTION_ABSENCE_PHOTOS_MONTHS', 6),
    'absence_photos_warn_months' => (int) env('RETENTION_ABSENCE_PHOTOS_WARN_MONTHS', 5),
];
