<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\ResolvePromoRecipientLandingAction;
use App\Support\Marketing\PromoLandingRequest;
use App\Support\Marketing\PromoRecipientToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PromoController extends Controller
{
    public function show(Request $request, ResolvePromoRecipientLandingAction $resolveLanding): RedirectResponse
    {
        $refFromQuery = PromoRecipientToken::normalize((string) $request->query('ref', ''));
        $recipient = PromoLandingRequest::recipient($request);
        $desiredLocale = PromoLandingRequest::desiredLocale($request, $recipient, $refFromQuery !== '');
        $locale = $desiredLocale ?? (string) $request->route('locale');
        $landing = $resolveLanding->handle($recipient);

        $query = $request->query();
        unset($query['lang']);

        return redirect()->route(
            $landing->routeName(),
            array_merge($query, ['locale' => $locale]),
            301,
        );
    }
}
