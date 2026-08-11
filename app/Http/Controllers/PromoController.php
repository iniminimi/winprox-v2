<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoVisitPage;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientLocale;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Marketing\PromoRecipientToken;
use App\Support\Marketing\PromoVisitScannerDetector;
use App\Support\Marketing\PromoVisitSession;
use App\Support\ResolveAppLocale;
use App\Support\Translation\LocaleSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class PromoController extends Controller
{
    public function show(Request $request, RecordPromoVisitAction $recordVisit): View|RedirectResponse
    {
        $refFromQuery = PromoRecipientToken::normalize((string) $request->query('ref', ''));
        $token = PromoRecipientSession::syncFromRequest($request);
        $recipient = null;

        if ($token !== null) {
            $recipient = PromoRecipient::query()->where('token', $token)->first();
            if ($recipient === null) {
                PromoRecipientSession::forget();
                $token = null;
            }
        }

        $desiredLocale = $this->desiredVisitLocale($request, $recipient, $refFromQuery !== '');
        $routeLocale = (string) $request->route('locale');

        if ($desiredLocale !== null && $desiredLocale !== $routeLocale) {
            $query = $request->query();
            unset($query['lang']);

            return redirect()->route('promo', array_merge($query, ['locale' => $desiredLocale]));
        }

        if ($desiredLocale !== null) {
            $this->persistLocale($request, $desiredLocale);
        }

        if ($this->shouldLogVisit($request)) {
            $locale = LocaleSupport::normalize(app()->getLocale());

            $recordVisit->handle(
                promoRecipientId: $recipient?->id,
                locale: $locale,
                visitedAt: now(),
                page: PromoVisitPage::Promo,
            );
        }

        return view('promo', [
            'promoTrackingToken' => $recipient?->token,
            'promoRecipientLabel' => $recipient?->label,
        ]);
    }

    private function desiredVisitLocale(Request $request, ?PromoRecipient $recipient, bool $fromRefQuery): ?string
    {
        $supported = config('locales.supported', []);

        $langParam = $request->query('lang');
        if (is_string($langParam)) {
            $langParam = strtolower(trim($langParam));
            if (in_array($langParam, $supported, true)) {
                return $langParam;
            }
        }

        if ($fromRefQuery && $recipient !== null) {
            return PromoRecipientLocale::forRecipient($recipient);
        }

        return null;
    }

    private function persistLocale(Request $request, string $locale): void
    {
        $request->session()->put('locale', $locale);
        Cookie::queue(ResolveAppLocale::COOKIE_NAME, $locale, ResolveAppLocale::COOKIE_MINUTES);
        app()->setLocale($locale);
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
