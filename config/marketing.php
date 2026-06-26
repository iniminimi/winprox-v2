<?php

return [
    /*
    | Promo-video keys (basename in public/video/{locale}/). Must match promo.json items.
    */
    'promo_video_keys' => [
        'issue',
        'task',
        'users_edit_qr',
        'issue_approve_briefing',
        'unit_categorie_gps_allow_issue_print_qr',
    ],

    /*
    | Tijdelijk verborgen promo-video's per locale (basename). Leegmaken na vervanging.
    */
    'promo_hidden_videos' => [
        'nl' => ['issue', 'task'],
    ],
];
