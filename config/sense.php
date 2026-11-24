<?php

/**
 * WinProx Sense — klimaat per lokaal (D1S).
 * Hexwaarden zijn de lcd-kleuren; dezelfde constanten staan in App\Support\Sense\SensePalette.
 */
return [

    'stale_after_minutes' => 15,

    'defaults' => [
        'temperature' => ['low' => 20, 'target' => 21, 'high' => 24],
        'humidity' => ['low' => 40, 'target' => 50, 'high' => 60],
        'co2_high' => 900,
        'voc_high' => 150,
    ],

    // Leefruimte. Winter is de start. Zomer vervangt alleen temperatuur en vocht.
    'presets' => [
        'winter' => [
            'temperature' => ['low' => 20, 'target' => 21, 'high' => 24],
            'humidity' => ['low' => 40, 'target' => 50, 'high' => 60],
        ],
        'summer' => [
            'temperature' => ['low' => 22, 'target' => 24, 'high' => 26],
            'humidity' => ['low' => 30, 'target' => 50, 'high' => 70],
        ],
    ],

    // SGP40-index, geen µg/m³. 100 is het gemiddelde van de sensor over 24 uur, geen gezondheidsgrens.
    // Groen tot 100, geel tot 150, oranje tot 250, daarna rood. Aandacht pas boven 150 én een half uur volgehouden.
    'voc' => [
        'attention_minutes' => 30,
        'zones' => [100, 150, 250],
    ],

    'ranges' => [
        'temperature' => ['min' => 10, 'max' => 35, 'step' => 0.5],
        'humidity' => ['min' => 0, 'max' => 100, 'step' => 1],
        'co2' => ['min' => 400, 'max' => 2000, 'step' => 50],
        'voc' => ['min' => 1, 'max' => 500, 'step' => 1],
    ],

    'reading' => [
        'temperature' => ['min' => -10, 'max' => 60],
        'humidity' => ['min' => 0, 'max' => 100],
        'co2' => ['min' => 0, 'max' => 40000],
        'voc' => ['min' => 1, 'max' => 500],
    ],

];
