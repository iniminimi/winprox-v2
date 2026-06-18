<?php

return [
    'enabled' => filter_var(env('TRANSLATION_SYNC_ENABLED', false), FILTER_VALIDATE_BOOL),

    'ssh_host' => (string) env('TRANSLATION_SYNC_SSH_HOST', ''),
    'ssh_user' => (string) env('TRANSLATION_SYNC_SSH_USER', ''),
    'ssh_port' => (int) env('TRANSLATION_SYNC_SSH_PORT', 22),
    'ssh_key' => env('TRANSLATION_SYNC_SSH_KEY'),

    'remote_path' => rtrim((string) env('TRANSLATION_SYNC_REMOTE_PATH', ''), '/'),

    'work_dir' => storage_path('app/translation-sync'),
    'export_filename' => 'translations.json',
    'import_filename' => 'translated.json',
    'status_path' => 'translation-sync/status.json',

    'timeout_seconds' => (int) env('TRANSLATION_SYNC_TIMEOUT', 7200),
];
