<?php

return [
    'host' => env('IMAP_HOST'),
    'port' => env('IMAP_PORT', 993),
    'encryption' => env('IMAP_ENCRYPTION', 'ssl'),
    'username' => env('IMAP_USERNAME'),
    'password' => env('IMAP_PASSWORD'),
    'protocol' => env('IMAP_PROTOCOL', 'imap'),
    'authentication' => env('IMAP_AUTHENTICATION'),
];
