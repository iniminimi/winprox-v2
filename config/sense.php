<?php

/**
 * WinProx Sense — klimaat per lokaal (D1S).
 * Hexwaarden zijn de lcd-kleuren; dezelfde constanten staan in App\Support\Sense\SensePalette.
 */
return [

    'stale_after_minutes' => 15,

    'defaults' => [
        'temperature' => ['low' => 18, 'target' => 21, 'high' => 24],
        'humidity' => ['low' => 40, 'target' => 50, 'high' => 60],
        'co2_high' => 1200,
        'voc_high' => 200,
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
