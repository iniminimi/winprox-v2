<?php

declare(strict_types=1);

use App\Support\JsonTranslationLoader;
use Illuminate\Filesystem\Filesystem;

it('merget een productkaart op key ongeacht de volgorde', function () {
    $loader = new JsonTranslationLoader(new Filesystem, lang_path());

    $base = [
        'full' => [
            ['title' => 'Samenvatting', 'key' => 'summary', 'body' => 'basis-samenvatting'],
            [
                'title' => 'Metingen',
                'key' => 'measurements',
                'body' => 'basis-meting',
                'items' => ['eerste', 'tweede'],
            ],
        ],
    ];
    $overlay = [
        'full' => [
            'measurements' => [
                'body' => 'nl-meting',
                'items' => [
                    1 => 'tweede-nl',
                ],
            ],
        ],
    ];

    $merged = $loader->mergeTranslations($base, $overlay);

    expect($merged['full'][0]['body'])->toBe('basis-samenvatting')
        ->and($merged['full'][1]['body'])->toBe('nl-meting')
        ->and($merged['full'][1]['items'][0])->toBe('eerste')
        ->and($merged['full'][1]['items'][1])->toBe('tweede-nl')
        ->and(array_is_list($merged['full']))->toBeTrue()
        ->and(array_key_exists('measurements', $merged['full']))->toBeFalse();
});

it('laat een genummerde lijstregel op zijn plaats', function () {
    $loader = new JsonTranslationLoader(new Filesystem, lang_path());

    $merged = $loader->mergeTranslations(
        ['flow' => ['Inklokken', 'Uurrooster', 'Uren', 'Secretariaat']],
        ['flow' => [3 => 'Salarisadministratie']],
    );

    expect($merged['flow'])->toBe(['Inklokken', 'Uurrooster', 'Uren', 'Salarisadministratie']);
});

it('voegt een onbekende kaartnaam niet toe aan de lijst', function () {
    $loader = new JsonTranslationLoader(new Filesystem, lang_path());

    $merged = $loader->mergeTranslations(
        ['full' => [
            ['title' => 'Metingen', 'key' => 'measurements', 'body' => 'basis'],
        ]],
        ['full' => [
            'ontbreekt' => ['body' => 'nee'],
        ]],
    );

    expect($merged['full'])->toHaveCount(1)
        ->and($merged['full'][0]['body'])->toBe('basis')
        ->and(array_is_list($merged['full']))->toBeTrue();
});
