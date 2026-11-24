<?php

declare(strict_types=1);

namespace App\Support\Sense;

/**
 * Verzadigde lcd-kleuren van het D1S-scherm. Dezelfde hex op de zwarte Sense-kaart.
 */
final class SensePalette
{
    public const BLACK = '#000000';

    public const TEXT = '#ffffff';

    public const MUTED = '#a3a3a3';

    public const BLUE = '#0080ff';

    public const GREEN = '#00d26a';

    public const RED = '#ff2d2d';

    public const DRY = '#ff7a00';

    public const HUMID = '#1a8cff';

    public const YELLOW = '#ffe14a';

    /**
     * @return array{low: string, target: string, high: string}
     */
    public static function band(string $metric): array
    {
        if ($metric === 'humidity') {
            return ['low' => self::DRY, 'target' => self::GREEN, 'high' => self::HUMID];
        }

        return ['low' => self::BLUE, 'target' => self::GREEN, 'high' => self::RED];
    }

    /**
     * @return array{low: string, high: string, yellow: string}
     */
    public static function ceiling(): array
    {
        return ['low' => self::GREEN, 'high' => self::RED, 'yellow' => self::YELLOW];
    }
}
