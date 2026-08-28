<?php

declare(strict_types=1);

namespace App\Support\Import;

final class TabularImportBoolean
{
    /**
     * @return array{value: bool|null, valid: bool} null value = empty cell → caller uses default
     */
    public static function parseOptional(string $raw): array
    {
        $normalized = strtolower(trim($raw));
        if ($normalized === '') {
            return ['value' => null, 'valid' => true];
        }

        if (in_array($normalized, ['1', 'true', 'yes', 'ja', 'y', 'oui', 'si', 'sì', 'sim'], true)) {
            return ['value' => true, 'valid' => true];
        }

        if (in_array($normalized, ['0', 'false', 'no', 'nee', 'n', 'non', 'nein'], true)) {
            return ['value' => false, 'valid' => true];
        }

        return ['value' => null, 'valid' => false];
    }
}
