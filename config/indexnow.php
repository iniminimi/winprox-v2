<?php

declare(strict_types=1);

return [
    /*
    | IndexNow (Bing e.a.): notify search engines when marketing URLs change.
    | Key file must live at public/{key}.txt (site root).
    */
    'enabled' => (bool) env('INDEXNOW_ENABLED', true),

    'key' => (string) env('INDEXNOW_KEY', '2c081d71a6a943a19a81fbb727f93cf4'),

    /*
    | Host without scheme (e.g. winprox.app). Empty = host from APP_URL.
    */
    'host' => env('INDEXNOW_HOST'),

    'endpoint' => (string) env('INDEXNOW_ENDPOINT', 'https://api.indexnow.org/indexnow'),
];
