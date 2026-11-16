<?php

/*
 | Data-gedreven talenlijst (WINPROX_RULES.md §5).
 | supported + labels = backoffice, website en Ollama-inhoudsvertaling.
 | portal = talen op het uitvoerdersportaal. Extra talen (nu pt, ro) staan daar,
 | niet in supported, en krijgen geen Ollama-slots.
 | portal_labels-volgorde = volgorde in de portaal-keuzelijst (PO boven RO).
 */
return [
    'default' => 'nl',

    'supported' => ['nl', 'en', 'fr', 'de', 'es', 'it'],

    'portal' => ['nl', 'en', 'fr', 'de', 'es', 'it', 'pt', 'ro'],

    'labels' => [
        'nl' => 'NL',
        'en' => 'EN',
        'fr' => 'FR',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
    ],

    'portal_labels' => [
        'pt' => 'PO',
        'ro' => 'RO',
    ],
];
