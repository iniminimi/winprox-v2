<?php

return [
    'operator' => env('LEGAL_OPERATOR', 'WinProx B.V.'),
    'jurisdiction' => env('LEGAL_JURISDICTION', 'Netherlands'),

    'documents' => [
        'privacy' => [
            'route' => 'legal.privacy',
            'label_key' => 'legal.documents.privacy',
            'updated' => '2026-05-01',
        ],
        'terms' => [
            'route' => 'legal.terms',
            'label_key' => 'legal.documents.terms',
            'updated' => '2026-05-01',
        ],
        'cookies' => [
            'route' => 'legal.cookies',
            'label_key' => 'legal.documents.cookies',
            'updated' => '2026-05-01',
        ],
        'dpa' => [
            'route' => 'legal.dpa',
            'label_key' => 'legal.documents.dpa',
            'updated' => '2026-05-01',
        ],
        'subprocessors' => [
            'route' => 'legal.subprocessors',
            'label_key' => 'legal.documents.subprocessors',
            'updated' => '2026-05-01',
        ],
    ],
];
