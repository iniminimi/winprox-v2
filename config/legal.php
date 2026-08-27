<?php

/**
 * Juridische pagina's (privacy, voorwaarden, DPA).
 * Pas aan via .env zonder Blade-bestanden te wijzigen.
 */
return [

    'documents_last_updated' => env('LEGAL_DOCUMENTS_LAST_UPDATED', '2026-08-27'),

    'operator' => [
        'name' => env('LEGAL_OPERATOR_NAME', 'Dominique Schaepdrijver'),
        'address_lines' => array_values(array_filter(array_map(
            'trim',
            explode("\n", (string) env('LEGAL_OPERATOR_ADDRESS', "Pannenstraat 223\n8301 Knokke-Heist\nBelgië"))
        ))),
        'vat_label' => env('LEGAL_OPERATOR_VAT_LABEL', 'BE 0743 862 316'),
        'enterprise_number' => env('LEGAL_OPERATOR_ENTERPRISE_NUMBER'),
        'email' => env('LEGAL_OPERATOR_EMAIL', 'info@winprox.app'),
    ],

    'jurisdiction' => env('LEGAL_JURISDICTION', 'België'),

    'documents' => [
        'privacy' => [
            'route' => 'legal.privacy',
            'label_key' => 'legal.documents.privacy',
        ],
        'terms' => [
            'route' => 'legal.terms',
            'label_key' => 'legal.documents.terms',
        ],
        'cookies' => [
            'route' => 'legal.cookies',
            'label_key' => 'legal.documents.cookies',
        ],
        'dpa' => [
            'route' => 'legal.dpa',
            'label_key' => 'legal.documents.dpa',
        ],
        'subprocessors' => [
            'route' => 'legal.subprocessors',
            'label_key' => 'legal.documents.subprocessors',
        ],
    ],
];
