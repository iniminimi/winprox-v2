<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit');

/**
 * @return list<string>
 */
function expectedTargetLocales(string $sourceLocale = 'nl'): array
{
    return array_values(array_filter(
        config('locales.supported', []),
        fn (string $locale) => $locale !== $sourceLocale,
    ));
}
