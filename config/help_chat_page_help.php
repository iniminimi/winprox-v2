<?php

/**
 * Help-chat → page-help (handleiding) matches.
 * Order matters: first matching pattern wins. Prefer specific phrases over short words.
 * Answers are built from lang/{locale}/page-help.json via PageHelp (same source as the printed manual).
 */
return [
    'max_actions' => 3,
    'max_chars' => 1100,

    'entries' => [
        [
            'patterns' => [
                'taak toe', 'taken toe', 'team toewijs', 'toewijs', 'toewijzen',
                'assign team', 'assign task', 'équipe', 'zuweisen', 'asignar', 'assegn',
            ],
            'page' => 'tasks.show',
            'prefer' => ['team toewijs', 'toewijs', 'assign', 'équipe', 'zuweisen', 'asignar', 'assegn'],
        ],
        [
            'patterns' => ['taakdetail', 'taak open', 'task detail', 'status wijzigen', 'prio'],
            'page' => 'tasks.show',
        ],
        [
            'patterns' => ['takenlijst', 'open taken', 'filter op team', 'tasks list'],
            'page' => 'tasks.list',
        ],
        [
            'patterns' => ['taak', 'taken', 'task', 'aufgabe', 'tâche', 'tache'],
            'page' => 'tasks.show',
            'prefer' => ['team', 'status', 'toewijs'],
        ],
        [
            'patterns' => [
                'reserver', 'reservatie', 'boeking', 'boeken', 'booking', 'reservation',
                'vergaderruimte', 'meeting room', 'reserveerbaar',
            ],
            'page' => 'reservations',
        ],
        [
            'patterns' => ['meldingdetail', 'issue detail', 'goedkeur melding', 'sluit melding'],
            'page' => 'issues.show',
        ],
        [
            'patterns' => ['terugkerend', 'recurring', 'cyclus', 'periodiek'],
            'page' => 'issues.create',
        ],
        [
            'patterns' => ['nieuwe melding', 'melding maken', 'create issue', 'signalement'],
            'page' => 'issues.list',
            'prefer' => ['nieuwe', 'maken', 'create', 'qr'],
        ],
        [
            'patterns' => ['melding', 'meldingen', 'issue', 'report', 'inciden'],
            'page' => 'issues.list',
        ],
        [
            'patterns' => ['kalender', 'calendar', 'briefing', 'planning'],
            'page' => 'calendar',
        ],
        [
            'patterns' => ['dashboard', 'overzicht dashboard'],
            'page' => 'dashboard',
        ],
        [
            'patterns' => ['categorie', 'category', 'unit aanmak', 'bulk', 'csv', 'import unit'],
            'page' => 'locations.list',
        ],
        [
            'patterns' => ['locatie', 'location', 'unit', 'standort', 'sticker', 'qr-pack'],
            'page' => 'locations.show',
            'prefer' => ['qr', 'sticker', 'unit'],
        ],
        [
            'patterns' => [
                'collega', 'colleague', 'backoffice', 'gebruiker toe', 'gebruikers beheer',
                'utilisateur collèg', 'kollegen', 'usuario colega', 'utente colleg',
            ],
            'page' => 'team.backoffice',
        ],
        [
            'patterns' => [
                'uitvoerder', 'worker', 'teamleider', 'teamleader', 'operationeel team',
                'checklist', 'clock point',
            ],
            'page' => 'team.teams',
        ],
        [
            'patterns' => ['team', 'équipes', 'equipe'],
            'page' => 'team.teams',
        ],
        [
            'patterns' => ['clock point', 'klokken', 'inchecken', 'uitchecken', 'aanwezigheid', 'presence'],
            'page' => 'time.clock_points',
        ],
        [
            'patterns' => ['uren', 'shift', 'time module', 'pauze'],
            'page' => 'time.shifts',
        ],
        [
            'patterns' => ['alarm', 'alarms'],
            'page' => 'time.alarms',
        ],
        [
            'patterns' => ['esg', 'indicator', 'meting', 'compliance', 'duurzaam'],
            'page' => 'esg.indicators',
        ],
        [
            'patterns' => ['iot', 'sensor', 'gateway', 'lora', 'mqtt', 'waterlek'],
            'page' => 'iot.index',
        ],
        [
            'patterns' => ['api', 'webhook', 'token', 'integratie'],
            'page' => 'settings.api',
        ],
        [
            'patterns' => ['gegevens wissen', 'data wissen', 'abonnement opzeg', 'cancel subscription'],
            'page' => 'subscription',
        ],
        [
            'patterns' => ['instelling', 'settings', 'thema', 'stijl', 'privacy', 'export'],
            'page' => 'settings',
        ],
        [
            'patterns' => ['clock point portaal', 'aanmelden uitvoerder', 'icoon'],
            'page' => 'portal.time',
        ],
        [
            'patterns' => ['publiek portaal', 'unit-qr', 'unit qr', 'melder'],
            'page' => 'portal.unit',
        ],
        [
            'patterns' => ['uitvoerders portaal', 'uitvoerder portaal'],
            'page' => 'portal.team',
        ],
        [
            'patterns' => ['foto', 'photo', 'beeld'],
            'page' => 'portal.worker.photos',
        ],
        [
            'patterns' => ['qr', 'scan', 'sticker'],
            'page' => 'portal.worker.qr',
        ],
        [
            'type' => 'manual_getting_started',
            'patterns' => [
                'aan de slag', 'getting started', 'inrichten', 'eerste stappen',
                'up-and-running', 'up and running', '5 stappen', 'vijf stappen',
            ],
        ],
    ],
];
