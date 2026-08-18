<?php

/**
 * Kleine starttemplates voor nieuwe organisaties (geen sectorsysteem).
 * Namen staan in lang/{locale}/starter_pack.json; alle talen gaan mee in de DB.
 */
return [
    'hotel' => [
        'teams' => [
            'cleaning' => ['categories' => ['rooms', 'sanitary', 'common']],
            'reception' => ['categories' => ['rooms']],
        ],
        'categories' => ['rooms', 'sanitary', 'common'],
        'units' => [
            ['key' => 'room_001', 'category' => 'rooms'],
            ['key' => 'room_002', 'category' => 'rooms'],
            ['key' => 'room_003', 'category' => 'rooms'],
        ],
    ],
    'hospital' => [
        'teams' => [
            'cleaning' => ['categories' => ['patient_rooms', 'treatment', 'installations']],
            'technical' => ['categories' => ['installations', 'treatment']],
        ],
        'categories' => ['patient_rooms', 'treatment', 'installations'],
        'units' => [
            ['key' => 'room_101', 'category' => 'patient_rooms'],
            ['key' => 'room_102', 'category' => 'patient_rooms'],
            ['key' => 'room_103', 'category' => 'patient_rooms'],
        ],
    ],
    'industry' => [
        'teams' => [
            'technical' => ['categories' => ['machines', 'warehouse']],
            'cleaning' => ['categories' => ['office', 'warehouse']],
        ],
        'categories' => ['machines', 'warehouse', 'office'],
        'units' => [
            ['key' => 'machine_001', 'category' => 'machines'],
            ['key' => 'machine_002', 'category' => 'machines'],
            ['key' => 'warehouse_a', 'category' => 'warehouse'],
        ],
    ],
    'municipality' => [
        'teams' => [
            'facility' => ['categories' => ['buildings', 'sports']],
            'green' => ['categories' => ['public_space']],
        ],
        'categories' => ['buildings', 'public_space', 'sports'],
        'units' => [
            ['key' => 'hall_001', 'category' => 'buildings'],
            ['key' => 'hall_002', 'category' => 'buildings'],
            ['key' => 'hall_003', 'category' => 'sports'],
        ],
    ],
];
