<?php

/**
 * Help-chat → page-help (handleiding) matches.
 * Order matters: first matching pattern wins. Prefer specific phrases over short words.
 * Answers are built from lang/{locale}/page-help.json via PageHelp (same source as the printed manual).
 * When no pattern matches, the assistant full-text searches the same handleiding content
 * (page-help chapters + Aan de slag; FAQ stays on help_chat_faq.php).
 */
return [
    'max_actions' => 3,
    'max_chars' => 1100,

    'entries' => [
        [
            'patterns' => [
                'e-mailbevestiging', 'emailbevestiging', 'bevestigingsmail', 'bevestigingslink',
                'email confirmation', 'require email confirmation', 'confirmation e-mail', 'confirmation email',
                'e-mail-bestätigung', 'email-bestätigung', 'bestätigungsmail',
                'confirmation par e-mail', 'confirmation par email',
                'confirmación de correo', 'conferma e-mail', 'conferma email',
            ],
            'page' => 'locations.categories',
            'prefer' => [
                'e-mailbevestiging', 'email confirmation', 'e-mail-bestätigung',
                'confirmation e-mail', 'confirmación de correo', 'conferma e-mail',
            ],
        ],
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
            'patterns' => [
                'starttemplate', 'starter template', 'op weg geholpen',
                'modèle de départ', 'modele de depart', 'startvorlage',
                'plantilla inicial', 'modello iniziale', 'starthilfe',
            ],
            'page' => 'dashboard',
            'prefer' => ['starttemplate', 'starter template', 'op weg', 'modèle de départ', 'startvorlage'],
        ],
        [
            'patterns' => ['dashboard', 'overzicht dashboard'],
            'page' => 'dashboard',
        ],
        [
            'patterns' => [
                'unit check', 'unit-check', 'unit checks', 'unitcheck',
                'wat is een unit check', 'what is a unit check',
                'ok/niet ok', 'ok/nicht ok', 'ok/not ok', 'ok/non ok', 'ok/no ok',
                'controle unit', 'contrôles d’unité', 'controles d unite',
                'unit-checks', 'comprobaciones de unit', 'controlli unit',
            ],
            'page' => 'unit-checks',
            'prefer' => ['wat is een unit check', 'what is a unit check', 'inschakelen', 'enable'],
        ],
        [
            'patterns' => [
                'unitmeting', 'unitmetingen', 'unit measurement', 'unit measurements',
                'meetveld', 'meetvelden', 'kilometerstand', 'odometer',
                'unit-messung', 'messfeld', 'medición de unidad', 'misurazione unità',
                'mesures d’unité', "mesures d'unité", 'measure field', 'measure fields',
                'metingenhistoriek', 'measurement history', 'historiek metingen',
                'unit.measurement.recorded',
            ],
            'page' => 'unit-measurements.index',
            'prefer' => ['meetveld', 'unitmeting', 'measure field', 'kilometerstand'],
        ],
        [
            'patterns' => [
                'inspectieronde', 'inspectie ronde', 'inspection round', 'inspection rounds',
                'tournée d’inspection', 'inspektionsrunde',
                'ronda de inspección', 'giro di ispezione',
            ],
            'page' => 'issues.create',
            'prefer' => ['stop', 'ronde', 'unit check'],
        ],
        [
            'patterns' => ['categorie', 'category', 'kategorie', 'categoría', 'catégorie'],
            'page' => 'locations.categories',
        ],
        [
            'patterns' => ['unit aanmak', 'bulk', 'csv', 'import unit'],
            'page' => 'locations.list',
        ],
        [
            'patterns' => ['locaties', 'locations', 'standorte', 'ubicaciones', 'sedi'],
            'page' => 'locations.list',
        ],
        [
            'patterns' => ['units', 'unités', 'unidades', 'unità'],
            'page' => 'units',
            'prefer' => ['overzicht', 'filter', 'unit checks'],
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
                'inloggen met microsoft', 'sign in with microsoft', 'mit microsoft anmelden',
                'se connecter avec microsoft', 'iniciar sesión con microsoft', 'accedi con microsoft',
                'microsoft login', 'microsoft-login', 'microsoft entra',
            ],
            'page' => 'team.backoffice',
        ],
        [
            'patterns' => [
                'nieuw toestel', 'new device', 'neues gerät', 'nouvel appareil',
                'nuevo dispositivo', 'nuovo dispositivo',
                'pincode', 'pin code', 'code pin',
            ],
            'page' => 'team.teams',
        ],
        [
            'patterns' => [
                'evacuatie', 'evacuation', 'évacuation', 'evakuierung', 'evacuación', 'evacuazione',
            ],
            'page' => 'time.presence',
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
            'patterns' => ['esg', 'esg-indicator', 'esg indicator', 'compliance', 'duurzaam', 'esg-meting'],
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
            'patterns' => [
                'werkmenu', 'work menu', 'workmenu',
                'arbeitsmenü', 'arbeitsmenu', 'arbeitsmenü-einstellungen', 'arbeitsmenu-einstellungen',
                'menu travail', 'paramètres du menu travail', 'parametres du menu travail',
                'menú trabajo', 'menu trabajo', 'ajustes del menú trabajo', 'ajustes del menu trabajo',
                'menu lavoro', 'impostazioni menu lavoro',
            ],
            'page' => 'settings',
            'prefer' => [
                'werkmenu', 'work menu', 'arbeitsmenü', 'menu travail', 'menú trabajo', 'menu lavoro',
                'kalender', 'reserveringen', 'inspectierondes', 'unitmetingen',
            ],
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
