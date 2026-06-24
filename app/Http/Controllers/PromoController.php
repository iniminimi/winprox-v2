<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Marketing\PromoVisitScannerDetector;
use App\Support\Marketing\PromoVisitSession;
use App\Support\Translation\LocaleSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function show(Request $request, RecordPromoVisitAction $recordVisit): View
    {
        $token = PromoRecipientSession::syncFromRequest($request);
        $recipient = null;

        if ($token !== null) {
            $recipient = PromoRecipient::query()->where('token', $token)->first();
            if ($recipient === null) {
                PromoRecipientSession::forget();
                $token = null;
            }
        }

        if ($this->shouldLogVisit($request)) {
            $locale = LocaleSupport::normalize(app()->getLocale());

            $recordVisit->handle(
                promoRecipientId: $recipient?->id,
                locale: $locale,
                visitedAt: now(),
            );
        }

        return view('promo', [
            'promoTrackingToken' => $recipient?->token,
            'promoRecipientLabel' => $recipient?->label,
        ]);
    }

    private function shouldLogVisit(Request $request): bool
    {
        if ($request->isMethod('HEAD')) {
            return false;
        }

        if (in_array($request->headers->get('Sec-Purpose'), ['prefetch', 'prerender'], true)) {
            return false;
        }

        if (PromoVisitScannerDetector::isAutomatedFetch($request->userAgent())) {
            return false;
        }

        if (PromoRecipientSession::token() !== null) {
            return true;
        }

        return PromoVisitSession::shouldLogAnonymousVisit($request);
    }
}
