<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Actions\Marketing\RecordWelcomeVisitAction;
use App\Enums\PromoVisitPage;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Marketing\PromoRecipientToken;
use App\Support\Marketing\PromoVisitScannerDetector;
use App\Support\Platform\SupportTenantContext;
use App\Support\Translation\LocaleSupport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(
        Request $request,
        RecordWelcomeVisitAction $recordVisit,
        RecordPromoVisitAction $recordPromoVisit,
    ): View|RedirectResponse {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user !== null && $user->is_superuser && $user->tenant_id === null && ! SupportTenantContext::isActive()) {
                return redirect()->route('platform.tenants');
            }

            return redirect()->route('dashboard');
        }

        $this->recordAttributedPromoVisit($request, $recordPromoVisit);

        $recordVisit->handle(
            locale: (string) $request->route('locale', config('app.locale', 'nl')),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            utmSource: $this->queryString($request, 'utm_source'),
            utmMedium: $this->queryString($request, 'utm_medium'),
            utmCampaign: $this->queryString($request, 'utm_campaign'),
        );

        return view('welcome');
    }

    private function recordAttributedPromoVisit(Request $request, RecordPromoVisitAction $recordPromoVisit): void
    {
        if ($request->isMethod('HEAD')) {
            return;
        }

        if (in_array($request->headers->get('Sec-Purpose'), ['prefetch', 'prerender'], true)) {
            return;
        }

        if (PromoVisitScannerDetector::isAutomatedFetch($request->userAgent())) {
            return;
        }

        $token = PromoRecipientSession::syncFromRequest($request);
        if ($token === null) {
            return;
        }

        $recipient = PromoRecipient::query()->where('token', $token)->first();
        if ($recipient === null) {
            PromoRecipientSession::forget();

            return;
        }

        // Alleen attributen wanneer de e-mail/link expliciet ?ref= meegaf.
        if (PromoRecipientToken::normalize((string) $request->query('ref', '')) === '') {
            return;
        }

        $recordPromoVisit->handle(
            promoRecipientId: (int) $recipient->id,
            locale: LocaleSupport::normalize((string) $request->route('locale', config('app.locale', 'nl'))),
            visitedAt: now(),
            page: PromoVisitPage::Welcome,
        );
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }
}
