<?php

/*
 | Data-gedreven talenlijst (WINPROX_RULES.md §5).
 | supported + labels = backoffice, website en Ollama-inhoudsvertaling.
 | portal = talen op het uitvoerdersportaal. Extra talen (nu ro) staan daar,
 | niet in supported, en krijgen geen Ollama-slots.
 */
return [
    'default' => 'nl',

    'supported' => ['nl', 'en', 'fr', 'de', 'es', 'it'],

    'portal' => ['nl', 'en', 'fr', 'de', 'es', 'it', 'ro'],

    'labels' => [
        'nl' => 'NL',
        'en' => 'EN',
        'fr' => 'FR',
        'de' => 'DE',
        'es' => 'ES',
        'it' => 'IT',
    ],

    'portal_labels' => [
        'ro' => 'RO',
    ],
];
