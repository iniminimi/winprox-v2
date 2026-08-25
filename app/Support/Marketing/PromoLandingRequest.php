<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use App\Models\PromoRecipient;
use App\Support\ResolveAppLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

final class PromoLandingRequest
{
    public static function recipient(Request $request): ?PromoRecipient
    {
        $token = PromoRecipientSession::syncFromRequest($request);
        if ($token === null) {
            return null;
        }

        $recipient = PromoRecipient::query()->where('token', $token)->first();
        if ($recipient === null) {
            PromoRecipientSession::forget();
        }

        return $recipient;
    }

    /**
     * Campagnetaal voor de /promo-ingang. Sector-landings volgen het locale-prefix
     * in het pad, anders kan een taalwissel met ?ref= niet.
     */
    public static function desiredLocale(Request $request, ?PromoRecipient $recipient, bool $fromRefQuery): ?string
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

    public static function persistLocale(Request $request, string $locale): void
    {
        $request->session()->put('locale', $locale);
        Cookie::queue(ResolveAppLocale::COOKIE_NAME, $locale, ResolveAppLocale::COOKIE_MINUTES);
        app()->setLocale($locale);
    }

    public static function shouldLogVisit(Request $request): bool
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
