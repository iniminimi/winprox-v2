<?php

$teams = [
    'Onderhoudsteam', 'Schoonmaakteam', 'Elektriciteit', 'HVAC & Klimaat', 'Beveiliging',
    'Tuinonderhoud', 'Catering', 'IT Support', 'Logistiek', 'Sanitair',
    'Schilderwerk', 'Timmerwerk', 'Glas en ramen', 'Liften en hijs', 'Brandveiligheid',
    'Parkeerbeheer', 'Receptie', 'Technische dienst', 'Groenvoorziening', 'Facility Support',
];

$firstNames = [
    'Jan', 'Piet', 'Marie', 'Sophie', 'Luc', 'Emma', 'Thomas', 'Laura', 'Koen', 'Nathalie',
    'Bart', 'Sarah', 'Joris', 'Els', 'Wouter', 'An', 'Stijn', 'Lien', 'Dries', 'Katrien',
    'Filip', 'Hanne', 'Niels', 'Julie', 'Maarten', 'Inge', 'Bram', 'Valerie', 'Jens', 'Lotte',
    'Robbe', 'Amber', 'Gert', 'Ellen', 'Stef', 'Kim', 'Vincent', 'Mieke', 'Johan', 'Sara',
];

$lastNames = [
    'Janssen', 'Peeters', 'Maes', 'Claes', 'Wouters', 'De Smet', 'Vermeersch', 'Goossens',
    'Jacobs', 'Mertens', 'Willems', 'De Vries', 'Van den Berg', 'Hermans', 'Michiels',
    'Stevens', 'Hendrickx', 'Martens', 'Claessens', 'Dubois', 'Lambert', 'Dupont', 'Martin',
    'Bernard', 'Leroy', 'Moreau', 'Simon', 'Laurent', 'Lefebvre', 'Van Damme', 'Vandenberghe',
    'Vandamme', 'Vermeulen', 'Verstraeten', 'Verbeke', 'Verhoeven', 'Verschueren', 'Verlinden',
    'Vervoort', 'Vlaeminck', 'Wauters', 'Willekens', 'Aerts', 'Baert', 'Cools', 'Declercq',
    'Engelen', 'Geerts', 'Hendrix', 'Janssens', 'Kerkhof', 'Lemmens', 'Moens', 'Nijs',
    'Pauwels', 'Quinten', 'Raes', 'Segers', 'Thijs', 'Van Acker', 'Van Cauwenberghe',
    'Van de Velde', 'Van Dyck', 'Van Gompel', 'Van Hecke', 'Van Hoof', 'Van Looy', 'Van Moer',
    'Vandekerckhove', 'Vandenbussche', 'Vandewalle', 'Vercammen', 'Vercruysse', 'Verdonck',
    'Verhaegen', 'Verhelst', 'Verheyen', 'Verhulst', 'Vermeire', 'Verstappen', 'Vleugels',
    'Vranckx', 'Wyckmans', 'Zwaenepoel',
];

$outputPath = __DIR__ . '/workers_import_test_400.csv';

$out = fopen($outputPath, 'w');
if ($out === false) {
    fwrite(STDERR, "Could not open output file.\n");
    exit(1);
}

fputcsv($out, ['team_name', 'first_name', 'last_name', 'email', 'phone', 'external_id']);

$emp = 1;

foreach ($teams as $teamIndex => $team) {
    for ($workerIndex = 1; $workerIndex <= 20; $workerIndex++) {
        $firstName = $firstNames[($teamIndex * 7 + $workerIndex * 3) % count($firstNames)];
        $lastName = $lastNames[($teamIndex * 11 + $workerIndex * 5) % count($lastNames)];

        $slugFirst = preg_replace('/[^a-z]/', '', strtolower($firstName));
        $slugLast = preg_replace('/[^a-z]/', '', strtolower(str_replace(' ', '', $lastName)));
        $email = $slugFirst . '.' . $slugLast . '.' . sprintf('%03d', $emp) . '@bedrijf.be';

        $prefix = 470 + ($emp % 30);
        $part2 = str_pad((string) (10 + ($emp * 7) % 90), 2, '0', STR_PAD_LEFT);
        $part3 = str_pad((string) (10 + ($emp * 13) % 90), 2, '0', STR_PAD_LEFT);
        $part4 = str_pad((string) (10 + ($emp * 17) % 90), 2, '0', STR_PAD_LEFT);
        $phone = sprintf('+32 %d %s %s %s', $prefix, $part2, $part3, $part4);

        $externalId = sprintf('EMP-%03d', $emp);

        fputcsv($out, [$team, $firstName, $lastName, $email, $phone, $externalId]);
        $emp++;
    }
}

fclose($out);

echo 'Generated ' . ($emp - 1) . ' workers in ' . count($teams) . ' teams at ' . $outputPath . PHP_EOL;
