<?php

namespace App\Actions\Time;

class BuildClockPointHomescreenManifestAction
{
    public function __construct(private ResolveClockPointPortalTokenAction $resolveToken) {}

    /**
     * @return array<string, mixed>|null
     */
    public function handle(string $token): ?array
    {
        $resolution = $this->resolveToken->handle($token);
        $clockPoint = $resolution->clockPoint;

        if ($clockPoint === null || ! $resolution->isUsable()) {
            return null;
        }

        $startUrl = route('public.time-portal.cp', $token);
        $scope = rtrim(url('/'), '/').'/';
        $manifestUrl = route('public.time-portal.manifest', $token);
        $icon192 = url('/images/pwa/winprox-192.png');
        $icon512 = url('/images/pwa/winprox-512.png');

        return [
            'id' => $startUrl,
            'name' => 'WinProx',
            'short_name' => 'WinProx',
            'start_url' => $startUrl,
            // Sticker en klokscherm zitten op /time/, het icoon op /cp/. Alleen de
            // site-root dekt beide, zodat een scan op Android de geïnstalleerde app opent.
            'scope' => $scope,
            'handle_links' => 'preferred',
            'launch_handler' => [
                'client_mode' => 'navigate-existing',
            ],
            'related_applications' => [
                [
                    'platform' => 'webapp',
                    'url' => $manifestUrl,
                    'id' => $startUrl,
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
            ],
        ];
    }
}
