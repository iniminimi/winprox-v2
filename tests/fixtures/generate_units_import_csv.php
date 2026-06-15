<?php

/**
 * Genereert een units CSV-import voor huurland / industriële verhuur.
 * 20 categorieën · 40 locaties · 30 units per locatie = 1200 rijen.
 */

$categories = [
    'Grondverzet' => [
        'prefixes' => ['Graafmachine', 'Minigraver', 'Shovel', 'Wiellader', 'Rupsgraafmachine'],
        'models' => ['Takeuchi TB210R', 'Kubota KX057', 'Caterpillar 320', 'Volvo EC220E', 'JCB 3CX', 'Hitachi ZX85'],
        'descriptions' => [
            'Urenstand controleren bij uitgifte',
            'Hydraulieklek controleren voor vertrek',
            'Graafbek en snelwissel visueel nakijken',
            'Brandstofpeil minimaal 50% bij retour',
        ],
    ],
    'Heftrucks en reachtrucks' => [
        'prefixes' => ['Heftruck', 'Reachtruck', 'Elektrische heftruck', 'Lpg-heftruck'],
        'models' => ['Toyota 8FG25', 'Linde H25T', 'Still RX20', 'Jungheinrich EFG 320', 'Hyster H2.5FT', 'Crown SC 5245'],
        'descriptions' => [
            'Vorken en hefcilinder controleren',
            'Accu laden minimaal 80% bij uitgifte',
            'Veiligheidsgordel en lichten testen',
            'Laadvermogen sticker zichtbaar houden',
        ],
    ],
    'Graafmachines' => [
        'prefixes' => ['Graafmachine', 'Midi-graver', 'Rupsgraafmachine'],
        'models' => ['Komatsu PC138', 'Doosan DX140', 'Case CX130', 'Kobelco SK140', 'Hyundai HX140'],
        'descriptions' => [
            'Rotator en hydrauliek warm laten draaien',
            'Rijwerk en onderstel schoon houden',
            'Cabinefilter vervangen na 500 uur',
        ],
    ],
    'Hoogwerkers' => [
        'prefixes' => ['Schaarhoogwerker', 'Telescoophoogwerker', 'Knikarmhoogwerker'],
        'models' => ['Genie GS-1932', 'JLG 450AJ', 'Haulotte Compact 10', 'Skyjack SJ3219', 'Manitou 120 AETJ'],
        'descriptions' => [
            'Nooddaal controle uitvoeren voor gebruik',
            'Stabilisatoren volledig uitgeschoven',
            'Windkracht max. 6 Beaufort respecteren',
        ],
    ],
    'Compressoren en luchtgereedschap' => [
        'prefixes' => ['Compressor', 'Dieselcompressor', 'Elektrische compressor'],
        'models' => ['Atlas Copco XAS 88', 'Kaeser M57', 'Ingersoll Rand P185', 'CompAir C14', 'Sullair 185'],
        'descriptions' => [
            'Oliedruk en temperatuur monitoren',
            'Luchtslangen visueel controleren',
            'Condensafvoer dagelijks legen',
        ],
    ],
    'Generatoren en aggregaten' => [
        'prefixes' => ['Generator', 'Aggregaat', 'Stroomgroep'],
        'models' => ['Atlas Copco QAS 60', 'Cummins C60D5', 'Himoinsa HFW 80', 'Pramac GSW 65', 'FG Wilson P65-5'],
        'descriptions' => [
            'Aarding controleren voor aansluiting',
            'Brandstofverbruik noteren bij retour',
            'Koelvloeistofpeil wekelijks checken',
        ],
    ],
    'Steigers en bekisting' => [
        'prefixes' => ['Rolsteiger', 'Gevelsteiger set', 'Bekistingselement', 'Tribord'],
        'models' => ['Layher Allround 4m', 'PERI UP Flex', 'Altrad Bosta 6m', 'Hünnebeck Manto', 'Doka Framax set'],
        'descriptions' => [
            'Alle verankeringen volgens plan plaatsen',
            'Wielen vergrendelen op vlakke ondergrond',
            'Ontbrekende spindels direct melden',
        ],
    ],
    'Sanitair en welfare units' => [
        'prefixes' => ['Sanitairunit', 'Welfare unit', 'Douchecontainer', 'Toiletunit'],
        'models' => ['Algeco WC 4 cabines', 'Ela Container D6', 'Containex Sanitair 20ft', 'Bodard Welfare 12 pers', 'Portakabin Shower 2 cabines'],
        'descriptions' => [
            'Watertank vullen voor levering',
            'Afvoer aansluiten volgens siteplan',
            'Schoonmaak bij retour verplicht',
        ],
    ],
    'Kantoorunits en containers' => [
        'prefixes' => ['Kantoorcontainer', 'Site office', 'Opslagcontainer', 'Kantineunit'],
        'models' => ['Algeco Burelen 20ft', 'Ela Premium Office', 'Containex Lager 40ft', 'Portakabin Solus 2', 'Bodard Kantoor 3 ruimtes'],
        'descriptions' => [
            'Sleutelset en alarmcode meegeven',
            'Verwarming aanzetten bij vorst',
            'Rookmelder testen bij plaatsing',
        ],
    ],
    'Pompen en waterbehandeling' => [
        'prefixes' => ['Dompelpomp', 'Bovengrondse pomp', 'Rioolpomp', 'Waterbehandelingsunit'],
        'models' => ['Flygt Bibo 2640', 'Tsurumi LH422', 'Atlas Copco PAS 150', 'Grundfos DW 10', 'Wacker PT 3A'],
        'descriptions' => [
            'Slangen en koppelingen lekdicht houden',
            'Debiet instellen volgens werfplan',
            'Pomp nooit droog laten draaien',
        ],
    ],
    'Lasapparatuur' => [
        'prefixes' => ['Lasapparaat MIG', 'Lasapparaat TIG', 'Lasapparaat MMA', 'Plasmasnijder'],
        'models' => ['Fronius TransPocket 180', 'Lincoln Electric Idealarc', 'Kemppi MinarcTig', 'ESAB Rebel EMP 215ic', 'Hypertherm Powermax 45'],
        'descriptions' => [
            'Gasfles en reduceerventiel controleren',
            'Aardkabel stevig aansluiten',
            'Lashelm en handschoenen verplicht',
        ],
    ],
    'Meet- en testapparatuur' => [
        'prefixes' => ['Laserlevel', 'Theodoliet', 'Volumescanner', 'Testunit elektriciteit'],
        'models' => ['Leica Rugby 640', 'Topcon RL-H5A', 'Fluke 1664 FC', 'Hilti PM 30-MG', 'Spectra Precision LL300N'],
        'descriptions' => [
            'Kalibratiecertificaat bij uitgifte meegeven',
            'Statief en prisma in koffer bewaren',
            'Batterijen opladen voor vertrek',
        ],
    ],
    'Verlichting en lichtgoten' => [
        'prefixes' => ['Lichtgoot LED', 'Mobiele lichtmast', 'Bouwlamp set', 'Noodverlichtingsunit'],
        'models' => ['Atlas Copco V5+', 'Generac ML15', 'X-Glo LED 50m', 'Trime X-Eco LED', 'Wacker Neuson LTV6'],
        'descriptions' => [
            'Kabels beschermen tegen beschadiging',
            'Lichtsterkte afstemmen op werfzone',
            'Mast verankeren bij wind',
        ],
    ],
    'Kettingzagen en bosbouw' => [
        'prefixes' => ['Kettingzaag', 'Bosmaaier', 'Houtversnipperaar', 'Stobbenfrees'],
        'models' => ['Husqvarna 572 XP', 'Stihl MS 500i', 'Vermeer BC1000XL', 'Bandit 1590', 'Bobcat SG60'],
        'descriptions' => [
            'Zaagketting smeren voor gebruik',
            'Persoonlijke bescherming verplicht',
            'Brandblusser aanwezig op locatie',
        ],
    ],
    'Betonmolens en verwerking' => [
        'prefixes' => ['Betonmolen', 'Trilnaald', 'Betonpomp unit', 'Mortelmixer'],
        'models' => ['Altrad BC180', 'Wacker Neuson HM1000', 'Putzmeister M740', 'Cifa Magnum', 'Imer Minuteman'],
        'descriptions' => [
            'Drum reinigen na elke werkdag',
            'Trilfrequentie instellen volgens spec',
            'Onderhoudslogboek bijhouden',
        ],
    ],
    'HVAC units' => [
        'prefixes' => ['HVAC unit', 'Klimaatcontainer', 'Ventilatieunit', 'Verwarmingstoestel'],
        'models' => ['Carrier 40FC', 'Daikin FVQ125', 'Munters ML270', 'Bosch Condens 7000', 'Trane Voyager 3'],
        'descriptions' => [
            'Filters maandelijks vervangen',
            'Condensafvoer vrijhouden',
            'Setpoint niet zonder overleg wijzigen',
        ],
    ],
    'Elektrische installaties' => [
        'prefixes' => ['Verdeelkast', 'Kabelhaspel 63A', 'Tijdelijke aansluitkast', 'Transformator unit'],
        'models' => ['ABB 250A verdeelkast', 'PCE 63A haspel', 'Legrand XL3 400A', 'Schneider Prisma', 'Hensel 32A kast'],
        'descriptions' => [
            'KEURING verplicht voor eerste gebruik',
            'Differentieel 30mA testen',
            'Kabels niet over rijpaden leggen',
        ],
    ],
    'Veiligheidsuitrusting' => [
        'prefixes' => ['Valbeveiliging set', 'Adembescherming set', 'Gasdetectieunit', 'Brandslanghaspel'],
        'models' => ['Petzl I’D S', 'Dräger X-am 8000', 'MSA Altair 4XR', '3M DBI-SALA Nano-Lok', 'Ansul brandslang 30m'],
        'descriptions' => [
            'Keuringsdatum controleren voor uitgifte',
            'Volledige set in koffer retourneren',
            'Kalibratie gasdetectie jaarlijks',
        ],
    ],
    'Transport en aanhangers' => [
        'prefixes' => ['Machinetransporter', 'Kipperaanhanger', 'Dieplader', 'Bagagewagen industrieel'],
        'models' => ['Eduard 3 assen', 'Humbaur HT 356218', 'Ifor Williams GH146', 'Brian James A4', 'Brenderup 6215'],
        'descriptions' => [
            'Remlichten en koppeling controleren',
            'Lading verzekeren volgens reglement',
            'Bandenspanning voor vertrek checken',
        ],
    ],
    'Reinigingsmachines' => [
        'prefixes' => ['Veegmachine', 'Hogedrukreiniger', 'Schrobmachine', 'Stofzuigunit industrieel'],
        'models' => ['Tennant T600', 'Kärcher HD 10/25', 'Nilfisk SC500', 'Dulevo 6000', 'Comac C130'],
        'descriptions' => [
            'Waterfilter reinigen na gebruik',
            'Borstels slijtag controleren',
            'Reinigingsmiddel doseren volgens instructie',
        ],
    ],
];

$categoryNames = array_keys($categories);

// Per locatie: 6 relevante categorieën (industriële mix, roterend per locatie-index)
function categoriesForLocation(int $locationIndex, array $categoryNames): array
{
    $count = count($categoryNames);
    $selected = [];
    for ($i = 0; $i < 6; $i++) {
        $selected[] = $categoryNames[($locationIndex * 3 + $i * 2) % $count];
    }

    return array_values(array_unique($selected));
}

$locations = [
    ['name' => 'Huurdepot Deinze-West', 'street' => 'Industrielaan', 'house_number' => '12', 'postal_code' => '9800', 'city' => 'Deinze', 'country_code' => 'BE', 'notes' => 'Ingang via poort B'],
    ['name' => 'Huurpark Gent-Zuid', 'street' => 'Wiedauwkaai', 'house_number' => '78', 'postal_code' => '9000', 'city' => 'Gent', 'country_code' => 'BE', 'notes' => 'Kantoor aan zone A'],
    ['name' => 'Depot Kortrijk-Noord', 'street' => 'Moeskroensesteenweg', 'house_number' => '154', 'postal_code' => '8500', 'city' => 'Kortrijk', 'country_code' => 'BE', 'notes' => 'Levering enkel 7u-16u'],
    ['name' => 'Site Antwerpen-Haven', 'street' => 'Scheldelaan', 'house_number' => '455', 'postal_code' => '2030', 'city' => 'Antwerpen', 'country_code' => 'BE', 'notes' => 'Poort 7 - badge verplicht'],
    ['name' => 'Industriepark Aalter', 'street' => 'Stationsstraat', 'house_number' => '220', 'postal_code' => '9880', 'city' => 'Aalter', 'country_code' => 'BE', 'notes' => 'Parkeerplaats P3'],
    ['name' => 'Werfzone Brugge-Oost', 'street' => 'Legeweg', 'house_number' => '89', 'postal_code' => '8200', 'city' => 'Brugge', 'country_code' => 'BE', 'notes' => 'Tijdelijke site tot Q4'],
    ['name' => 'Depot Roeselare', 'street' => 'Kortrijksestraat', 'house_number' => '301', 'postal_code' => '8800', 'city' => 'Roeselare', 'country_code' => 'BE', 'notes' => 'Laadperron nr. 2'],
    ['name' => 'Huurcentrum Waregem', 'street' => 'Zuiderlaan', 'house_number' => '44', 'postal_code' => '8790', 'city' => 'Waregem', 'country_code' => 'BE', 'notes' => 'Showroom op afspraak'],
    ['name' => 'Industriezone Oudenaarde', 'street' => 'Neder Zwalm', 'house_number' => '17', 'postal_code' => '9700', 'city' => 'Oudenaarde', 'country_code' => 'BE', 'notes' => 'Graafwerkzaamheden zone C'],
    ['name' => 'Depot Wetteren', 'street' => 'Hondstraat', 'house_number' => '66', 'postal_code' => '9230', 'city' => 'Wetteren', 'country_code' => 'BE', 'notes' => 'Nachtlevering op aanvraag'],
    ['name' => 'Site Ieper-Industrie', 'street' => 'Jagersstraat', 'house_number' => '8', 'postal_code' => '8900', 'city' => 'Ieper', 'country_code' => 'BE', 'notes' => 'Munitiegrond - geen graafwerk'],
    ['name' => 'Huurdepot Mechelen', 'street' => 'Battelsesteenweg', 'house_number' => '455', 'postal_code' => '2800', 'city' => 'Mechelen', 'country_code' => 'BE', 'notes' => 'Ingang zuidzijde'],
    ['name' => 'Logistiek Hasselt', 'street' => 'Steenovenweg', 'house_number' => '3', 'postal_code' => '3500', 'city' => 'Hasselt', 'country_code' => 'BE', 'notes' => 'Cross-dock zone 4'],
    ['name' => 'Depot Genk-Zuid', 'street' => 'Europalaan', 'house_number' => '101', 'postal_code' => '3600', 'city' => 'Genk', 'country_code' => 'BE', 'notes' => 'Chemiezone - PBM verplicht'],
    ['name' => 'Werfzone Turnhout', 'street' => 'Steenweg op Gierle', 'house_number' => '210', 'postal_code' => '2300', 'city' => 'Turnhout', 'country_code' => 'BE', 'notes' => 'Wegwerkzaamheden R13'],
    ['name' => 'Industriepark Geel', 'street' => 'Kempische Steenweg', 'house_number' => '312', 'postal_code' => '2440', 'city' => 'Geel', 'country_code' => 'BE', 'notes' => 'Nucleaire zone - badge A2'],
    ['name' => 'Depot Sint-Niklaas', 'street' => 'Casinostraat', 'house_number' => '55', 'postal_code' => '9100', 'city' => 'Sint-Niklaas', 'country_code' => 'BE', 'notes' => 'Poort automatisch na 6u'],
    ['name' => 'Site Lokeren-Oost', 'street' => 'Europark-Oost', 'house_number' => '1', 'postal_code' => '9160', 'city' => 'Lokeren', 'country_code' => 'BE', 'notes' => 'Containerpark achteraan'],
    ['name' => 'Huurdepot Dendermonde', 'street' => 'N41', 'house_number' => 'km 12', 'postal_code' => '9200', 'city' => 'Dendermonde', 'country_code' => 'BE', 'notes' => 'Toegang via N41'],
    ['name' => 'Depot Tienen', 'street' => 'Industrieweg', 'house_number' => '27', 'postal_code' => '3300', 'city' => 'Tienen', 'country_code' => 'BE', 'notes' => 'Suikerfabriek-site'],
    ['name' => 'Huurpark Rotterdam-Moerdijk', 'street' => 'Moerdijkweg', 'house_number' => '2', 'postal_code' => '4762', 'city' => 'Moerdijk', 'country_code' => 'NL', 'notes' => 'Havennummer 8801'],
    ['name' => 'Depot Dordrecht-Industrie', 'street' => 'Laan der Verenigde Naties', 'house_number' => '60', 'postal_code' => '3316', 'city' => 'Dordrecht', 'country_code' => 'NL', 'notes' => 'ADN-zone - geen ontstekingsbronnen'],
    ['name' => 'Site Bergen op Zoom', 'street' => 'De Zeeweg', 'house_number' => '15', 'postal_code' => '4612', 'city' => 'Bergen op Zoom', 'country_code' => 'NL', 'notes' => 'Werfzone Schelde-Rhein'],
    ['name' => 'Industrieterrein Breda-Noord', 'street' => 'Teteringsedijk', 'house_number' => '210', 'postal_code' => '4817', 'city' => 'Breda', 'country_code' => 'NL', 'notes' => 'Levering via achteringang'],
    ['name' => 'Huurdepot Tilburg', 'street' => 'Kanaalweg', 'house_number' => '33', 'postal_code' => '5041', 'city' => 'Tilburg', 'country_code' => 'NL', 'notes' => 'Spoorwegovergang let op'],
    ['name' => 'Depot Eindhoven-Zuid', 'street' => 'Ekkersrijt', 'house_number' => '4201', 'postal_code' => '5692', 'city' => 'Son en Breugel', 'country_code' => 'NL', 'notes' => 'High Tech Campus nabij'],
    ['name' => 'Werfzone Venlo', 'street' => 'Blerickseweg', 'house_number' => '88', 'postal_code' => '5912', 'city' => 'Venlo', 'country_code' => 'NL', 'notes' => 'Grenslogistiek zone'],
    ['name' => 'Industriepark Roosendaal', 'street' => 'Laan van Brabant', 'house_number' => '12', 'postal_code' => '4703', 'city' => 'Roosendaal', 'country_code' => 'NL', 'notes' => 'Poort 3 voor zwaar materieel'],
    ['name' => 'Depot Goes', 'street' => 'Industrieweg', 'house_number' => '7', 'postal_code' => '4462', 'city' => 'Goes', 'country_code' => 'NL', 'notes' => 'Zeeland depot'],
    ['name' => 'Huurcentrum Zwolle', 'street' => 'Hanzelaan', 'house_number' => '201', 'postal_code' => '8017', 'city' => 'Zwolle', 'country_code' => 'NL', 'notes' => 'IJsselhallen-site'],
    ['name' => 'Site Arnhem-Industrie', 'street' => 'Westervoortsedijk', 'house_number' => '73', 'postal_code' => '6827', 'city' => 'Arnhem', 'country_code' => 'NL', 'notes' => 'Rijnwaalhaven'],
    ['name' => 'Depot Nijmegen-West', 'street' => 'Wijchenseweg', 'house_number' => '122', 'postal_code' => '6537', 'city' => 'Nijmegen', 'country_code' => 'NL', 'notes' => 'Bouwweg tijdelijk'],
    ['name' => 'Industriepark Ghent-Kluizen', 'street' => 'Kluizendok', 'house_number' => '1', 'postal_code' => '9042', 'city' => 'Gent', 'country_code' => 'BE', 'notes' => 'Dok 6 kade'],
    ['name' => 'Depot Luik-Grâce-Hollogne', 'street' => 'Rue des Jardiniers', 'house_number' => '45', 'postal_code' => '4460', 'city' => 'Grâce-Hollogne', 'country_code' => 'BE', 'notes' => 'Aéroport zone cargo'],
    ['name' => 'Werfzone Charleroi', 'street' => 'Rue du Grand Puits', 'house_number' => '18', 'postal_code' => '6000', 'city' => 'Charleroi', 'country_code' => 'BE', 'notes' => 'Oude staalfabriek-site'],
    ['name' => 'Huurdepot Namen', 'street' => 'Rue de l\'Épée', 'house_number' => '92', 'postal_code' => '5000', 'city' => 'Namen', 'country_code' => 'BE', 'notes' => 'Meuse-werfzone'],
    ['name' => 'Depot Bergen', 'street' => 'Rue de Grand-Leez', 'house_number' => '201', 'postal_code' => '5030', 'city' => 'Bergen', 'country_code' => 'BE', 'notes' => 'Champ de courses'],
    ['name' => 'Site Leuven-Heverlee', 'street' => 'Geldenaaksebaan', 'house_number' => '330', 'postal_code' => '3001', 'city' => 'Heverlee', 'country_code' => 'BE', 'notes' => 'Campus industriezone'],
    ['name' => 'Depot Vilvoorde', 'street' => 'Houtemsesteenweg', 'house_number' => '56', 'postal_code' => '1800', 'city' => 'Vilvoorde', 'country_code' => 'BE', 'notes' => 'Brucargo nabij'],
    ['name' => 'Industriepark Lille', 'street' => 'Rue de Tournai', 'house_number' => '310', 'postal_code' => '5900', 'city' => 'Lille', 'country_code' => 'BE', 'notes' => 'Grensdepot BE-FR'],
];

if (count($locations) !== 40) {
    fwrite(STDERR, 'Expected 40 locations, got ' . count($locations) . PHP_EOL);
    exit(1);
}

if (count($categoryNames) !== 20) {
    fwrite(STDERR, 'Expected 20 categories, got ' . count($categoryNames) . PHP_EOL);
    exit(1);
}

$outputPath = __DIR__ . '/units_import_huurland_1200.csv';
$out = fopen($outputPath, 'w');
if ($out === false) {
    fwrite(STDERR, "Could not open output file.\n");
    exit(1);
}

fputcsv($out, [
    'location_name',
    'street',
    'house_number',
    'postal_code',
    'city',
    'country_code',
    'notes',
    'unit_name',
    'description',
    'category_name',
]);

$unitCounter = 1;

foreach ($locations as $locationIndex => $location) {
    $locationCategories = categoriesForLocation($locationIndex, $categoryNames);

    for ($unitIndex = 1; $unitIndex <= 30; $unitIndex++) {
        $categoryName = $locationCategories[($unitIndex - 1) % count($locationCategories)];
        $category = $categories[$categoryName];

        $prefix = $category['prefixes'][($unitIndex + $locationIndex) % count($category['prefixes'])];
        $model = $category['models'][($unitCounter + $locationIndex) % count($category['models'])];
        $description = $category['descriptions'][($unitIndex + $locationIndex) % count($category['descriptions'])];

        $unitName = sprintf('%s %s #%03d', $prefix, $model, $unitCounter);

        fputcsv($out, [
            $location['name'],
            $location['street'],
            $location['house_number'],
            $location['postal_code'],
            $location['city'],
            $location['country_code'],
            $location['notes'],
            $unitName,
            $description,
            $categoryName,
        ]);

        $unitCounter++;
    }
}

fclose($out);

echo sprintf(
    "Generated %d units across %d locations and %d categories at %s\n",
    $unitCounter - 1,
    count($locations),
    count($categoryNames),
    $outputPath,
);
