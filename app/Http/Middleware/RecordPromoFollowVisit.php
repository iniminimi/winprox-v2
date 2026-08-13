<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoVisitFollowKey;
use App\Enums\PromoVisitKind;
use App\Enums\PromoVisitPage;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Marketing\PromoVisitScannerDetector;
use App\Support\Translation\LocaleSupport;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RecordPromoFollowVisit
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            return $response;
        }

        if ($request->isMethod('HEAD')) {
            return $response;
        }

        if (in_array($request->headers->get('Sec-Purpose'), ['prefetch', 'prerender'], true)) {
            return $response;
        }

        if (PromoVisitScannerDetector::isAutomatedFetch($request->userAgent())) {
            return $response;
        }

        $followKey = PromoVisitFollowKey::fromRouteName($request->route()?->getName());
        if ($followKey === null) {
            return $response;
        }

        $token = PromoRecipientSession::token();
        if ($token === null) {
            return $response;
        }

        $recipient = PromoRecipient::query()->where('token', $token)->first();
        if ($recipient === null) {
            return $response;
        }

        app(RecordPromoVisitAction::class)->handle(
            promoRecipientId: (int) $recipient->id,
            locale: LocaleSupport::normalize(app()->getLocale()),
            visitedAt: now(),
            page: PromoVisitPage::Welcome,
            kind: PromoVisitKind::Follow,
            followKey: $followKey,
        );

        return $response;
    }
}
