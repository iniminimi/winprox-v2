<?php

namespace App\Actions\Time;

use App\Models\ClockPoint;

class BuildClockPointHomescreenManifestAction
{
    /**
     * Eén canoniek manifest per tenant: dezelfde manifest-URL, id en start_url
     * op elk Clock Point. Oudere browsers dedupliceren installs op URL i.p.v.
     * het id-veld — per-token URL's lieten daar telkens een "nieuwe" app
     * installeren (en na QR-vernieuwing opnieuw een icoon).
     *
     * @return array<string, mixed>|null
     */
    public function handle(int $tenantId): ?array
    {
        $hasUsablePoint = ClockPoint::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->exists();

        if (! $hasUsablePoint) {
            return null;
        }

        $appId = url('/time-portal/'.$tenantId);
        $manifestUrl = route('public.time-portal.tenant.manifest', $tenantId);
        $scope = rtrim(url('/'), '/').'/';
        // ?v=3 bust Chrome's icoon-cache bij herinstall — gelijke URL's
        // zouden anders de oude PNG's blijven tonen.
        $icon192 = url('/images/pwa/winprox-192.png').'?v=3';
        $icon512 = url('/images/pwa/winprox-512.png').'?v=3';
        $maskable192 = url('/images/pwa/winprox-maskable-192.png').'?v=3';
        $maskable512 = url('/images/pwa/winprox-maskable-512.png').'?v=3';

        return [
            'id' => $appId,
            'name' => 'WinProx',
            'short_name' => 'WinProx',
            // Stabiele startpagina die doorverwijst naar een live Clock Point.
            'start_url' => $appId,
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
