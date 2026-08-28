<?php

return [
    /*
    | FAQ substring matcher for the in-app assistant (not an LLM).
    | Order matters: first matching pattern wins. Prefer specific phrases over
    | single ambiguous words (e.g. avoid bare "status", "?", "app", "klok").
    */
    'entries' => [
        [
            'patterns' => [
                'e-mailbevestiging', 'emailbevestiging', 'bevestigingsmail', 'bevestigingslink',
                'email confirmation', 'require email confirmation', 'confirmation e-mail', 'confirmation email',
                'e-mail-bestätigung', 'email-bestätigung', 'bestätigungsmail',
                'confirmation par e-mail', 'confirmation par email',
                'confirmación de correo', 'conferma e-mail', 'conferma email',
            ],
            'body_key' => 'faq.items.reporter_portal.summary',
        ],
        [
            'patterns' => ['qr', 'scan', 'melden', 'melder', 'code qr', 'clock point'],
            'body_key' => 'faq.items.qr_code.summary',
        ],
        [
            // Stem "reserv" covers NL reserveren/reservering/reservatie and EN reservation.
            'patterns' => [
                'reserv', 'reserveren', 'reservering', 'reserveer', 'reserveerbaar', 'reservatie',
                'reservation', 'reserve', 'reservieren', 'reservierung',
                'réserver', 'réservation',
                'reservar', 'reserva', 'prenotare', 'prenotazione',
                'boeking', 'boeken', 'booking', 'vergaderruimte', 'meeting room', 'meetingroom',
            ],
            'body_key' => 'faq.items.reservations.summary',
        ],
        [
            'patterns' => [
                'unit check', 'unit-check', 'unit checks', 'unitcheck',
                'ok/niet ok', 'ok/nicht ok', 'ok/not ok', 'ok/non ok', 'ok/no ok',
                'checklist', 'checklists', 'controle unit', 'unit-Check', 'Unit check',
            ],
            'body_key' => 'faq.items.unit_checks.summary',
        ],
        [
            'patterns' => [
                'unitmeting', 'unitmetingen', 'unit measurement', 'unit measurements',
                'meetveld', 'meetvelden', 'kilometerstand', 'odometer', 'kilométrage', 'kilometrage',
                'kamertemperatuur', 'room temperature', 'voertuigstatus', 'vehicle status',
                'unit-messung', 'unit-messungen', 'messfeld',
                'medición de unidad', 'mediciones de unidad',
                'misurazione unità', 'misurazioni unità',
                'mesures d’unité', "mesures d'unité", 'mesure d’unité',
            ],
            'body_key' => 'faq.items.unit_measurements.summary',
        ],
        [
            'patterns' => ['melding', 'issue', 'report', 'signalement', 'inciden'],
            'body_key' => 'faq.items.how_it_works.summary',
        ],
        [
            'patterns' => ['blur', 'goedkeur', 'moderatie', 'wacht op controle', 'approve', 'freigabe'],
            'body_key' => 'faq.items.moderation.summary',
        ],
        [
            'patterns' => ['foto', 'photo', 'beeld', 'image', 'upload'],
            'body_key' => 'faq.items.photos.summary',
        ],
        [
            'patterns' => ['time', 'klokken', 'inchecken', 'uitchecken', 'clock', 'pauze', 'shift', 'stempeln', 'fichaje', 'pointage'],
            'body_key' => 'faq.items.time_clock.summary',
        ],
        [
            'patterns' => ['esg', 'compliance', 'indicator', 'duurzaam', 'nachhalt', 'esg-meting', 'esg measurement'],
            'body_key' => 'faq.items.esg.summary',
        ],
        [
            'patterns' => ['iot', 'sensor', 'gateway', 'lora', 'mqtt', 'waterlek', 'lekdetectie'],
            'body_key' => 'faq.items.iot.summary',
        ],
        [
            'patterns' => ['taak', 'task', 'tache', 'aufgabe', 'briefing'],
            'body_key' => 'faq.items.team_follow_up.summary',
        ],
        [
            'patterns' => ['team', 'intern', 'équipe', 'equipe', 'uitvoerder', 'worker'],
            'body_key' => 'faq.items.internal_teams.summary',
        ],
        [
            'patterns' => ['locatie', 'location', 'unit', 'standort', 'site'],
            'body_key' => 'faq.items.multiple_locations.summary',
        ],
        [
            'patterns' => ['kalender', 'calendar', 'planning', 'terugkerend', 'recurring'],
            'body_key' => 'faq.items.team_follow_up.summary',
        ],
        [
            'patterns' => ['portaal', 'portal', 'portail'],
            'body_key' => 'faq.items.reporter_portal.summary',
        ],
        [
            'patterns' => [
                'pagina-hulp', 'page help', 'page-help', 'hulpknop', 'help button',
                'hilfe-button', 'bouton aide', 'seitenhilfe', 'pagina hulp',
            ],
            'body_key' => 'faq.items.page_help.summary',
        ],
        [
            'patterns' => [
                'voor wie', 'for who', 'for whom', 'doelgroep', 'hospitality', 'aannemer',
                'contractor', 'pour qui', 'für wen', 'para quién', 'para quien', 'per chi',
            ],
            'body_key' => 'faq.items.for_who.summary',
        ],
        [
            'patterns' => ['abonnement', 'subscription', 'trial', 'proef', 'prijs', 'kost', 'plan', 'formule', 'corporate', 'facility'],
            'body_key' => 'faq.items.pricing.summary',
        ],
        [
            'patterns' => [
                'inloggen met microsoft', 'sign in with microsoft', 'mit microsoft anmelden',
                'se connecter avec microsoft', 'iniciar sesión con microsoft', 'accedi con microsoft',
                'microsoft login', 'microsoft-login', 'microsoft entra', 'entra id', 'sso',
                'aanmelden met microsoft', 'inloggen microsoft',
            ],
            'body_key' => 'faq.items.microsoft_login.summary',
        ],
        [
            'patterns' => ['rol', 'role', 'beheerder', 'admin', 'gebruiker', 'medewerker'],
            'body_key' => 'faq.items.user_roles.summary',
        ],
        [
            'patterns' => [
                'verwijder account', 'verwijder gegevens', 'account verwijderen', 'organisatie verwijderen',
                'delete account', 'delete data', 'delete organisation', 'delete organization',
                'konto löschen', 'daten löschen', 'supprimer compte', 'supprimer données',
                'eliminar cuenta', 'eliminar datos', 'elimina account', 'elimina dati',
                'wissen', 'purge', 'gdpr delete', 'avg wissen',
            ],
            'body_key' => 'faq.items.delete_account.summary',
        ],
        [
            'patterns' => ['export', 'gegevens', 'data', 'gdpr', 'avg'],
            'body_key' => 'faq.items.data_export.summary',
        ],
        [
            'patterns' => ['gsm', 'smartphone', 'mobile', 'tablet'],
            'body_key' => 'faq.items.mobile.summary',
        ],
        [
            'patterns' => ['install', 'installeren', 'app store', 'software', 'applicatie'],
            'body_key' => 'faq.items.install.summary',
        ],
        [
            'patterns' => ['sticker', 'avery', 'herma', 'print'],
            'body_key' => 'faq.items.stickers.summary',
        ],
        [
            'patterns' => ['dynamisch', 'dynamic', 'herkoppel'],
            'body_key' => 'faq.items.dynamic_qr_codes.summary',
        ],
        [
            'patterns' => [
                'werkmenu', 'work menu', 'workmenu',
                'arbeitsmenü', 'arbeitsmenu',
                'menu travail',
                'menú trabajo', 'menu trabajo',
                'menu lavoro',
            ],
            'body_key' => 'faq.items.work_menu.summary',
        ],
        [
            'patterns' => ['thema', 'stijl', 'donker', 'dark', 'portaal-stijl'],
            'body_key' => 'faq.items.style.summary',
        ],
        [
            'patterns' => ['api', 'webhook', 'integratie', 'token'],
            'body_key' => 'faq.items.api_webhooks.summary',
        ],
    ],
];
