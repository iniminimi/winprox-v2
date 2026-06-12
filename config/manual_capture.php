<?php

return [
    'node_bin' => env('MANUAL_CAPTURE_NODE_BIN', 'node'),
    'playwright_browsers_path' => env(
        'MANUAL_CAPTURE_PLAYWRIGHT_BROWSERS_PATH',
        storage_path('playwright-browsers'),
    ),
    'base_url' => rtrim((string) env('MANUAL_CAPTURE_BASE_URL', 'http://127.0.0.1'), '/'),
    'host_header' => env('MANUAL_CAPTURE_HOST'),
    'email' => env('MANUAL_CAPTURE_EMAIL'),
    'password' => env('MANUAL_CAPTURE_PASSWORD'),
    'locales' => ['nl', 'en', 'fr', 'de'],
    'output_dir' => public_path('images/manual'),
    'config_path' => base_path('scripts/manual-capture.config.json'),
    'script_path' => base_path('scripts/capture-manual-screenshots.mjs'),
    'status_path' => 'manual-capture/status.json',
    'timeout_seconds' => (int) env('MANUAL_CAPTURE_TIMEOUT', 600),

    'location_id' => env('MANUAL_CAPTURE_LOCATION_ID'),
    'issue_id' => env('MANUAL_CAPTURE_ISSUE_ID'),
    'task_id' => env('MANUAL_CAPTURE_TASK_ID'),
    'unit_qr_token' => env('MANUAL_CAPTURE_UNIT_QR_TOKEN'),
    'team_qr_token' => env('MANUAL_CAPTURE_TEAM_QR_TOKEN'),
    'worker_first_name' => env('MANUAL_CAPTURE_WORKER_FIRST_NAME'),
    'worker_last_name' => env('MANUAL_CAPTURE_WORKER_LAST_NAME'),
    'worker_icon' => env('MANUAL_CAPTURE_WORKER_ICON'),
];
