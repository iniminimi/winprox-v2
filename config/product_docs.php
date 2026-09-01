<?php

/**
 * Publieke productfiches (features, technisch, API & Webhooks).
 * Bron van waarheid: lang/{locale}/product_docs.json — zie WINPROX_RULES.md §10a.
 */
return [

    'documents_last_updated' => env('PRODUCT_DOCS_LAST_UPDATED', '2026-09-01'),

    'documents' => [
        'features' => [
            'route' => 'product.features',
            'label_key' => 'product_docs.documents.features.label',
            'content_key' => 'features',
        ],
        'technical' => [
            'route' => 'product.technical',
            'label_key' => 'product_docs.documents.technical.label',
            'content_key' => 'technical',
        ],
        'api_webhooks' => [
            'route' => 'product.api_webhooks',
            'label_key' => 'product_docs.documents.api_webhooks.label',
            'content_key' => 'api_webhooks',
        ],
    ],
];
