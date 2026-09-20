<?php

declare(strict_types=1);

return [
    /** Simulatie-endpoint (tests / onboarding). */
    'simulation_base_url' => env(
        'RSZ_PRESENCE_SIMULATION_BASE_URL',
        'https://services-sim.socialsecurity.be/REST/presenceRegistration/v1',
    ),

    /** Productie-endpoint. */
    'production_base_url' => env(
        'RSZ_PRESENCE_PRODUCTION_BASE_URL',
        'https://services.socialsecurity.be/REST/presenceRegistration/v1',
    ),

    'oauth_token_url' => env(
        'RSZ_OAUTH_TOKEN_URL',
        'https://services.socialsecurity.be/REST/oauth/v5/token',
    ),

    /**
     * RSZ: OAuth-token ook voor simulatie via productie-token-URL.
     * @see https://www.socialsecurity.be (presenceRegistration handleiding)
     */
    'oauth_token_url_simulation' => env(
        'RSZ_OAUTH_TOKEN_URL_SIMULATION',
        'https://services.socialsecurity.be/REST/oauth/v5/token',
    ),

    /** true = simulation presence API base URL. */
    'use_simulation' => filter_var(env('RSZ_USE_SIMULATION', true), FILTER_VALIDATE_BOOL),

    /**
     * Optioneel vast Bearer-token (alleen local/testing). Overslaat JWT-signing.
     * Productie: leeg laten.
     */
    'static_access_token' => env('RSZ_STATIC_ACCESS_TOKEN'),

    /**
     * WinProx-platform Chaman REST-credentials (softwareleverancier).
     * Tenants vullen alleen BCE; OAuth loopt via dit account.
     * Private key: RSZ_PLATFORM_PRIVATE_KEY (PEM, \n toegestaan) of pad via RSZ_PLATFORM_PRIVATE_KEY_PATH.
     */
    'platform_client_id' => env('RSZ_PLATFORM_CLIENT_ID'),
    'platform_private_key' => env('RSZ_PLATFORM_PRIVATE_KEY'),
    'platform_private_key_path' => env('RSZ_PLATFORM_PRIVATE_KEY_PATH'),

    'timeout_seconds' => (int) env('RSZ_HTTP_TIMEOUT', 15),

    /** Golf 2: bouw-scope pas na RSZ-specs. */
    'construction_scope_enabled' => filter_var(env('RSZ_CONSTRUCTION_SCOPE_ENABLED', false), FILTER_VALIDATE_BOOL),

    /** Max. seconden vertraging t.o.v. registration_at vóór skip (RSZ real-time). */
    'max_submit_delay_seconds' => (int) env('RSZ_MAX_SUBMIT_DELAY_SECONDS', 600),
];
