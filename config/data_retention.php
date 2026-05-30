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
];
