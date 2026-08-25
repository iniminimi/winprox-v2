<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoLanding;
use App\Support\Marketing\JsonLd;
use App\Support\Marketing\PromoLandingRequest;
use App\Support\Marketing\SectorLandingVideo;
use App\Support\Marketing\SectorLandingVisuals;
use App\Support\Translation\LocaleSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class SectorLandingController extends Controller
{
    /**
     * Sector-landings volgen het locale-prefix. Campagnetaal via ?ref= hoort
     * bij /promo (anders kan de taalwisselaar niet weg van de mail-taal).
     */
    public function show(Request $request, RecordPromoVisitAction $recordVisit): View
    {
        $landing = PromoLanding::tryFrom((string) $request->route()?->getName());
        abort_unless($landing instanceof PromoLanding, 404);

        $recipient = PromoLandingRequest::recipient($request);

        if (PromoLandingRequest::shouldLogVisit($request)) {
            $recordVisit->handle(
                promoRecipientId: $recipient?->id,
                locale: LocaleSupport::normalize(app()->getLocale()),
                visitedAt: now(),
                page: $landing->visitPage(),
            );
        }

        $locale = LocaleSupport::normalize(app()->getLocale());
        $videoRelative = SectorLandingVideo::relativePath($landing, $locale);
        $key = 'landings.'.$landing->value;
        $visualBundle = SectorLandingVisuals::bundle($landing);

        return view('landings.show', [
            'landing' => $landing,
            'slug' => $landing->value,
            'videoSrc' => $videoRelative !== null ? asset($videoRelative) : null,
            'visuals' => $visualBundle['paths'],
            'visualModifiers' => $visualBundle['modifiers'],
            'visualLayouts' => $visualBundle['layouts'],
            'closeStyle' => $visualBundle['closeStyle'],
            'relatedLinks' => $this->relatedLinks($landing),
            'promoTrackingToken' => $recipient?->token,
            'promoRecipientLabel' => $recipient?->label,
            'layoutTitle' => __("{$key}.meta_title"),
            'layoutSocialTitle' => __("{$key}.social.og_title"),
            'layoutSocialDescription' => __("{$key}.social.og_description"),
            'layoutJsonLdGraphs' => [
                JsonLd::organization(),
                JsonLd::softwareApplication(),
            ],
        ]);
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    private function relatedLinks(PromoLanding $current): array
    {
        $links = [];
        foreach (PromoLanding::cases() as $landing) {
            if ($landing === $current) {
                continue;
            }
            $links[] = [
                'label' => __($landing->labelKey()),
                'url' => route($landing->routeName()),
            ];
        }

        $links[] = ['label' => __('landings.shared.welcome'), 'url' => route('welcome')];
        $links[] = ['label' => __('features.shared.links.pricing'), 'url' => route('pricing')];

        return $links;
    }
}
