<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Enums\PromoLanding;

final class SectorLandingVisuals
{
    /**
     * @return array{
     *     paths: array<string, string>,
     *     modifiers: array<string, string>,
     *     layouts: array<string, string>,
     *     closeStyle: string|null
     * }
     */
    public static function bundle(PromoLanding $landing): array
    {
        $empty = [
            'paths' => [],
            'modifiers' => [],
            'layouts' => [],
            'closeStyle' => null,
        ];

        $config = self::config($landing);
        if ($config === []) {
            return $empty;
        }

        $paths = [];
        $modifiers = [];
        $layouts = [];
        $closeStyle = null;

        foreach ($config as $key => $item) {
            $src = is_array($item) ? ($item['src'] ?? null) : $item;
            if (! is_string($src) || $src === '') {
                continue;
            }

            if (! is_file(public_path($src))) {
                return $empty;
            }

            $paths[$key] = $src;

            if (is_array($item)) {
                if (filled($item['modifier'] ?? null)) {
                    $modifiers[$key] = (string) $item['modifier'];
                }
                if (filled($item['layout'] ?? null)) {
                    $layouts[$key] = (string) $item['layout'];
                }
                if ($key === 'close' && filled($item['closeStyle'] ?? null)) {
                    $closeStyle = (string) $item['closeStyle'];
                }
            }
        }

        return [
            'paths' => $paths,
            'modifiers' => $modifiers,
            'layouts' => $layouts,
            'closeStyle' => $closeStyle,
        ];
    }

    /**
     * Relative paths under public/ for campaign landing photos.
     *
     * @return array<string, string>
     */
    public static function for(PromoLanding $landing): array
    {
        return self::bundle($landing)['paths'];
    }

    /**
     * @return array<string, string>
     */
    public static function modifiers(PromoLanding $landing): array
    {
        return self::bundle($landing)['modifiers'];
    }

    /**
     * @return array<string, string>
     */
    public static function layouts(PromoLanding $landing): array
    {
        return self::bundle($landing)['layouts'];
    }

    public static function closeStyle(PromoLanding $landing): ?string
    {
        return self::bundle($landing)['closeStyle'];
    }

    /**
     * @return array<string, array{src: string, modifier?: string, layout?: string, closeStyle?: string}|string>
     */
    private static function config(PromoLanding $landing): array
    {
        return match ($landing) {
            PromoLanding::RealEstate => [
                'hero' => 'images/landing/general/welcome_01.jpg',
                'problem' => 'images/landing/general/welcome_04.jpg',
                'steps' => 'images/landing/general/welcome_02.jpg',
                'places' => 'images/landing/general/welcome_03.jpg',
                'roles' => [
                    'src' => 'images/landing/general/welcome_05.jpg',
                    'modifier' => 'wp-landing-visual--roles',
                ],
                'why' => 'images/landing/general/welcome_06.jpg',
                'close' => 'images/landing/general/welcome_07.jpg',
            ],
            PromoLanding::Industry => [
                'hero' => 'images/landing/industry/image_01.jpg',
                'problem' => 'images/landing/industry/image_08.jpg',
                'steps' => [
                    'src' => 'images/landing/industry/image_04.jpg',
                    'modifier' => 'wp-landing-visual--compact',
                ],
                'places' => [
                    'src' => 'images/landing/industry/image_09.jpg',
                    'layout' => 'wide',
                    'modifier' => 'wp-landing-visual--wide',
                ],
                'roles' => [
                    'src' => 'images/landing/industry/image_05.jpg',
                    'modifier' => 'wp-landing-visual--roles wp-landing-visual--tall',
                ],
                'why' => [
                    'src' => 'images/landing/industry/image_07.jpg',
                    'modifier' => 'wp-landing-visual--feature',
                ],
                'close' => [
                    'src' => 'images/landing/industry/image_06.jpg',
                    'closeStyle' => 'scrim',
                ],
            ],
            PromoLanding::Hospitality => [
                'hero' => 'images/landing/hospitality/image_03.jpg',
                'problem' => 'images/landing/hospitality/image_01.jpg',
                'steps' => [
                    'src' => 'images/landing/hospitality/image_04.jpg',
                    'modifier' => 'wp-landing-visual--compact',
                ],
                'places' => [
                    'src' => 'images/landing/hospitality/image_05.jpg',
                    'layout' => 'wide',
                    'modifier' => 'wp-landing-visual--wide',
                ],
                'roles' => [
                    'src' => 'images/landing/hospitality/image_07.jpg',
                    'modifier' => 'wp-landing-visual--roles wp-landing-visual--tall',
                ],
                'close' => [
                    'src' => 'images/landing/hospitality/image_06.jpg',
                    'closeStyle' => 'scrim',
                ],
            ],
            PromoLanding::Healthcare => [
                'hero' => 'images/landing/healthcare/05.jpg',
                'problem' => 'images/landing/healthcare/image_02.jpg',
                'steps' => [
                    'src' => 'images/landing/healthcare/image_06.jpg',
                    'modifier' => 'wp-landing-visual--compact',
                ],
                'places' => [
                    'src' => 'images/landing/healthcare/image_01.jpg',
                    'layout' => 'wide',
                    'modifier' => 'wp-landing-visual--wide',
                ],
                'roles' => [
                    'src' => 'images/landing/healthcare/image_04.jpg',
                    'modifier' => 'wp-landing-visual--roles wp-landing-visual--tall',
                ],
                'why' => [
                    'src' => 'images/landing/healthcare/image_7.jpg',
                    'modifier' => 'wp-landing-visual--feature',
                ],
                'close' => [
                    'src' => 'images/landing/healthcare/image_03.jpg',
                    'closeStyle' => 'scrim',
                ],
            ],
            default => [],
        };
    }
}
