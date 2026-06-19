<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVideoPlayAction;
use App\Http\Requests\Marketing\TrackPromoVideoPlayRequest;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use Illuminate\Http\Response;

class PromoVideoTrackController extends Controller
{
    public function __invoke(
        TrackPromoVideoPlayRequest $request,
        RecordPromoVideoPlayAction $recordPlay,
    ): Response {
        $token = PromoRecipientSession::token();
        if ($token === null) {
            abort(404);
        }

        $recipient = PromoRecipient::query()->where('token', $token)->first();
        if ($recipient === null) {
            abort(404);
        }

        $locale = app()->getLocale();
        if (! in_array($locale, ['nl', 'en', 'fr', 'de'], true)) {
            $locale = 'nl';
        }

        $recordPlay->handle(
            promoRecipientId: (int) $recipient->id,
            videoKey: (string) $request->validated('video_key'),
            locale: $locale,
            playedAt: now(),
        );

        return response()->noContent();
    }
}
