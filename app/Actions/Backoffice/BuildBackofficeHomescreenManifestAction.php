<?php

namespace App\Actions\Backoffice;

class BuildBackofficeHomescreenManifestAction
{
    /**
     * Eén beheer-app voor de hele site. Aparte id t.o.v. Clock Point, en
     * handle_links niet-preferred zodat een scan de prikklok-app blijft openen.
     * Het venster zelf navigeert wel door heel het beheer (scope = site-root).
     *
     * @return array<string, mixed>
     */
    public function handle(string $locale): array
    {
        $appId = url('/backoffice');
        $manifestUrl = route('backoffice.manifest');
        $icon192 = url('/images/pwa/winprox-192.png').'?v=3';
        $icon512 = url('/images/pwa/winprox-512.png').'?v=3';
        $maskable192 = url('/images/pwa/winprox-maskable-192.png').'?v=3';
        $maskable512 = url('/images/pwa/winprox-maskable-512.png').'?v=3';

        return [
            'id' => $appId,
            'name' => trans('common.pwa.name', [], $locale),
            'short_name' => trans('common.pwa.short_name', [], $locale),
            'start_url' => route('dashboard'),
            'scope' => rtrim(url('/'), '/').'/',
            'handle_links' => 'not-preferred',
            'launch_handler' => [
                'client_mode' => 'navigate-existing',
            ],
            'related_applications' => [
                [
                    'platform' => 'webapp',
                    'url' => $manifestUrl,
                    'id' => $appId,
                ],
            ],
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#059669',
            'icons' => [
                [
                    'src' => $icon192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $icon512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => $maskable192,
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
                [
                    'src' => $maskable512,
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
        ];
    }
}
