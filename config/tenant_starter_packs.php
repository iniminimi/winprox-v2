<?php

/**
 * Kleine starttemplates voor nieuwe organisaties (geen sectorsysteem).
 * Namen staan in lang/{locale}/starter_pack.json; alle talen gaan mee in de DB.
 */
return [
    'all_work_menu_on' => [
        'calendar' => true,
        'reservations' => true,
        'inspection_rounds' => true,
        'unit_measurements' => true,
    ],
    'hotel' => [
        'work_menu' => 'all_work_menu_on',
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
        'work_menu' => 'all_work_menu_on',
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
        'work_menu' => 'all_work_menu_on',
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
        'work_menu' => 'all_work_menu_on',
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
    'realestate' => [
        'work_menu' => [
            'calendar' => true,
            'reservations' => false,
            'inspection_rounds' => true,
            'unit_measurements' => false,
        ],
        'teams' => [
            'technical' => ['categories' => ['apartments', 'technical']],
            'cleaning' => ['categories' => ['apartments', 'common']],
        ],
        'categories' => ['apartments', 'common', 'technical'],
        'units' => [
            ['key' => 'apt_101', 'category' => 'apartments'],
            ['key' => 'apt_102', 'category' => 'apartments'],
            ['key' => 'boiler_room', 'category' => 'technical'],
        ],
    ],
    'fleet' => [
        'work_menu' => 'all_work_menu_on',
        'teams' => [
            'workshop' => ['categories' => ['vehicles', 'workshop']],
            'planning' => ['categories' => ['vehicles', 'depot']],
        ],
        'categories' => ['vehicles', 'workshop', 'depot'],
        'units' => [
            ['key' => 'vehicle_001', 'category' => 'vehicles'],
            ['key' => 'vehicle_002', 'category' => 'vehicles'],
            ['key' => 'bay_01', 'category' => 'workshop'],
        ],
    ],
];
