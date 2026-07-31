<?php

/**
 * Publieke productfiches (features-overzicht + technische fiche).
 * Bron van waarheid: lang/{locale}/product_docs.json — zie WINPROX_RULES.md.
 */
return [

    'documents_last_updated' => env('PRODUCT_DOCS_LAST_UPDATED', '2026-07-31'),

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
    ],
];
