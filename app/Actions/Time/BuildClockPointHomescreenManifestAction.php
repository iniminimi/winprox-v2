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

        // Altijd de vaste QR van het Clock Point. Een scherm-QR roteert en is
        // na het venster ongeldig; die mag niet het startadres van de app worden.
        $homeToken = (string) $clockPoint->qr_token;
        $startUrl = route('public.time-portal.cp', $homeToken);
        $scope = rtrim(url('/'), '/').'/';
        // Eén app per tenant: de token verandert bij QR-vernieuwing, maar het id
        // blijft stabiel. Anders ziet Android de vernieuwde app als een nieuwe
        // installatie en komt er een tweede startscherm-icoon bij.
        $appId = url('/time-portal/'.$clockPoint->tenant_id);
        $manifestUrl = route('public.time-portal.cp.manifest', $homeToken);
        $icon192 = url('/images/pwa/winprox-192.png');
        $icon512 = url('/images/pwa/winprox-512.png');
        $maskable192 = url('/images/pwa/winprox-maskable-192.png');
        $maskable512 = url('/images/pwa/winprox-maskable-512.png');

        return [
            'id' => $appId,
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
