<?php

return [
    /*
    | Fotoalbum per unit, aan of uit voor de hele organisatie
    | (docs/FEATURES.md §5h). Foto's staan op de privé-disk en worden na
    | `data_retention.unit_album_photos_days` gewist.
    */
    'max_photos_per_day' => (int) env('UNIT_PHOTO_ALBUM_MAX_PER_DAY', 24),

    'max_photos_per_mail' => (int) env('UNIT_PHOTO_ALBUM_MAX_PER_MAIL', 8),

    /*
    | Cloud86 laat 250 mails per uur toe per hostingpakket (alle tenants +
    | systeemmails samen). Het album blijft daar ruim onder.
    */
    'mails_per_hour_per_tenant' => (int) env('UNIT_PHOTO_ALBUM_MAILS_PER_HOUR_TENANT', 25),

    'mails_per_hour_global' => (int) env('UNIT_PHOTO_ALBUM_MAILS_PER_HOUR_GLOBAL', 150),
];
