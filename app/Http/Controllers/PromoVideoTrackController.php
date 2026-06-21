<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVideoPlayAction;
use App\Http\Requests\Marketing\TrackPromoVideoPlayRequest;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Translation\LocaleSupport;
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

        $locale = LocaleSupport::normalize(app()->getLocale());

        $recordPlay->handle(
            promoRecipientId: (int) $recipient->id,
            videoKey: (string) $request->validated('video_key'),
            locale: $locale,
            playedAt: now(),
        );

        return response()->noContent();
    }
}
