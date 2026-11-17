<?php

return [
    /** mailto: of https:// — verplicht voor de browserdiensten, geen geheim. */
    'subject' => env('PUSH_VAPID_SUBJECT', 'mailto:info@winprox.app'),

    'public_key' => env('PUSH_VAPID_PUBLIC_KEY', ''),

    'private_key' => env('PUSH_VAPID_PRIVATE_KEY', ''),
];
