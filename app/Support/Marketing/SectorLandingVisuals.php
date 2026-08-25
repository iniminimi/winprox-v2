<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoLanding;

final class SectorLandingVisuals
{
    /**
     * Relative paths under public/ for campaign landing photos.
     *
     * @return array<string, string>
     */
    public static function for(PromoLanding $landing): array
    {
        $map = self::map($landing);
        if ($map === []) {
            return [];
        }

        foreach ($map as $relative) {
            if (! is_file(public_path($relative))) {
                return [];
            }
        }

        return $map;
    }

    /**
     * @return array<string, string>
     */
    private static function map(PromoLanding $landing): array
    {
        return match ($landing) {
            PromoLanding::RealEstate => [
                'hero' => 'images/landing/general/welcome_01.jpg',
                'problem' => 'images/landing/general/welcome_04.jpg',
                'steps' => 'images/landing/general/welcome_02.jpg',
                'places' => 'images/landing/general/welcome_03.jpg',
                'roles' => 'images/landing/general/welcome_05.jpg',
                'why' => 'images/landing/general/welcome_06.jpg',
                'close' => 'images/landing/general/welcome_07.jpg',
            ],
            default => [],
        };
    }
}
