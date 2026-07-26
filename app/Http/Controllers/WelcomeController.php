<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordWelcomeVisitAction;
use App\Support\Faq\FaqSections;
use App\Support\Platform\SupportTenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class WelcomeController extends Controller
{
    public function __invoke(Request $request, RecordWelcomeVisitAction $recordVisit): View|RedirectResponse
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user !== null && $user->is_superuser && $user->tenant_id === null && ! SupportTenantContext::isActive()) {
                return redirect()->route('platform.tenants');
            }

            return redirect()->route('dashboard');
        }

        $recordVisit->handle(
            locale: (string) $request->route('locale', config('app.locale', 'nl')),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            utmSource: $this->queryString($request, 'utm_source'),
            utmMedium: $this->queryString($request, 'utm_medium'),
            utmCampaign: $this->queryString($request, 'utm_campaign'),
        );

        return view('welcome', [
            'faqItems' => FaqSections::orderedItems(),
        ]);
    }

    private function queryString(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return is_string($value) ? $value : null;
    }
}
