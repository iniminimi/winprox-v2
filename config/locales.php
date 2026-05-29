<?php

/*
 | Data-gedreven talenlijst (WINPROX_RULES.md §5). Uitbreidbaar: voeg een locale
 | toe aan 'supported' + 'labels' en lever lang/[locale]/*.json met pariteit.
 */
return [
    'default' => 'nl',

    'supported' => ['nl', 'en', 'fr', 'de'],

    'labels' => [
        'nl' => 'NL',
        'en' => 'EN',
        'fr' => 'FR',
        'de' => 'DE',
    ],
];
