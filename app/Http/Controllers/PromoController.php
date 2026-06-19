<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
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

        $locale = app()->getLocale();
        if (! in_array($locale, ['nl', 'en', 'fr', 'de'], true)) {
            $locale = 'nl';
        }

        $recordVisit->handle(
            promoRecipientId: $recipient?->id,
            locale: $locale,
            visitedAt: now(),
        );

        return view('promo', [
            'promoTrackingToken' => $recipient?->token,
        ]);
    }
}
