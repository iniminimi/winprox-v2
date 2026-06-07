<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Idempotency Configuration
    |--------------------------------------------------------------------------
    |
    | Configureer idempotency settings voor de API. Idempotency zorgt ervoor
    | dat clients dezelfde request veilig kunnen herhalen zonder dubbele
    | mutaties (bijv. bij netwerk timeouts of retries).
    |
    */

    'idempotency_ttl' => env('API_IDEMPOTENCY_TTL', 86400), // 24 uur in seconden

    'idempotency_enabled' => env('API_IDEMPOTENCY_ENABLED', true),

    'idempotency_max_response_size' => 1024 * 1024, // 1MB in bytes

    'idempotency_max_key_length' => 255, // karakters
];
