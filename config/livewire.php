<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary File Uploads
    |--------------------------------------------------------------------------
    |
    | Livewire only generates signed preview URLs for extensions listed here.
    | AVIF is allowed by Laravel's `image` validation rule but omitted from
    | Livewire's defaults — include it so Settings branding uploads can preview.
    |
    */

    'temporary_file_upload' => [
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
            'avif',
        ],
    ],

];
