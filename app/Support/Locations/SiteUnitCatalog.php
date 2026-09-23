<?php

declare(strict_types=1);

namespace App\Support\Locations;

/**
 * Vaste bronnaam + vertalingen voor de automatische "hele locatie"-unit.
 * Bron is altijd NL; vertalingen worden als completed slots gezet.
 */
final class SiteUnitCatalog
{
    public const SOURCE_LOCALE = 'nl';

    public const SOURCE_NAME = 'Hele locatie';

    /**
     * @return array<string, string> locale => name
     */
    public static function namesByLocale(): array
    {
        return [
            'nl' => 'Hele locatie',
            'en' => 'Whole location',
            'fr' => 'Site entier',
            'de' => 'Gesamter Standort',
            'es' => 'Ubicación completa',
            'it' => 'Sede intera',
        ];
    }

    public static function nameForLocale(string $locale): string
    {
        $names = self::namesByLocale();

        return $names[$locale] ?? self::SOURCE_NAME;
    }
}
