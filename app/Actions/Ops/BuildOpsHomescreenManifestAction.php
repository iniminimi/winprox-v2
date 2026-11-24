<?php

declare(strict_types=1);

namespace App\Actions\Ops;

/**
 * Aparte PWA voor WinProx OPS (superuser-gsm), los van Clock Point en Beheer.
 * Scope blijft onder /ops/ zodat Chrome ook de gewone WinProx-PWA toelaat.
 */
final class BuildOpsHomescreenManifestAction
{
    /**
     * @return array<string, mixed>
     */
    public function handle(): array
    {
        $appId = route('public.ops');
        $scope = rtrim(url('/ops'), '/').'/';
        $manifestUrl = route('public.ops.manifest');
        $icon192 = url('/images/pwa/winprox-192.png').'?v=4';
        $icon512 = url('/images/pwa/winprox-512.png').'?v=4';
        $maskable192 = url('/images/pwa/winprox-maskable-192.png').'?v=4';
        $maskable512 = url('/images/pwa/winprox-maskable-512.png').'?v=4';
        $badge = url('/images/pwa/winprox-badge-96.png').'?v=2';

        return [
            'id' => $appId,
            'name' => 'WinProx OPS',
            'short_name' => 'OPS',
            'start_url' => $appId,
            // Alleen /ops/* — niet de hele site, anders blokkeert Chrome de uitvoerders-PWA.
            'scope' => $scope,
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
                [
                    'src' => $badge,
                    'sizes' => '96x96',
                    'type' => 'image/png',
                    'purpose' => 'monochrome',
                ],
            ],
        ];
    }
}
