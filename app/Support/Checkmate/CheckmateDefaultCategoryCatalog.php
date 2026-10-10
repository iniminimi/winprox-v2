<?php

declare(strict_types=1);

namespace App\Support\Checkmate;

/**
 * Stille defaultcategorie voor Checkmate: Facility-gating (units) zonder
 * dat de gebruiker «categorieën» hoeft te begrijpen.
 */
final class CheckmateDefaultCategoryCatalog
{
    public const SOURCE_LOCALE = 'nl';

    public const SOURCE_NAME = 'Klantlocatie';

    /**
     * @return array<string, string> locale => name
     */
    public static function namesByLocale(): array
    {
        return [
            'nl' => 'Klantlocatie',
            'en' => 'Customer site',
            'fr' => 'Site client',
            'de' => 'Kundenstandort',
            'es' => 'Sede del cliente',
            'it' => 'Sede cliente',
        ];
    }
}
