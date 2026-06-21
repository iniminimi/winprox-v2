<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
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

        $locale = LocaleSupport::normalize(app()->getLocale());

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
