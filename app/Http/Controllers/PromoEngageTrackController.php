<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\RecordPromoVisitAction;
use App\Enums\PromoVisitKind;
use App\Enums\PromoVisitPage;
use App\Http\Requests\Marketing\TrackPromoEngageRequest;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoRecipientSession;
use App\Support\Marketing\PromoVisitScannerDetector;
use App\Support\Translation\LocaleSupport;
use Illuminate\Http\Response;

class PromoEngageTrackController extends Controller
{
    public function __invoke(
        TrackPromoEngageRequest $request,
        RecordPromoVisitAction $recordVisit,
    ): Response {
        if (PromoVisitScannerDetector::isAutomatedFetch($request->userAgent())) {
            return response()->noContent();
        }

        $token = PromoRecipientSession::token();
        if ($token === null) {
            abort(404);
        }

        $recipient = PromoRecipient::query()->where('token', $token)->first();
        if ($recipient === null) {
            abort(404);
        }

        $page = PromoVisitPage::from((string) $request->validated('page'));

        $recordVisit->handle(
            promoRecipientId: (int) $recipient->id,
            locale: LocaleSupport::normalize(app()->getLocale()),
            visitedAt: now(),
            page: $page,
            kind: PromoVisitKind::Engaged,
        );

        return response()->noContent();
    }
}
