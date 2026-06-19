<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\GeneratePromoQrCodeAction;
use App\Models\PromoRecipient;
use App\Support\Marketing\PromoLandingUrl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PromoRecipientQrDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, PromoRecipient $promoRecipient, GeneratePromoQrCodeAction $action): Response
    {
        $this->authorize('managePromoRecipients', $request->user());

        $pngData = $action->handle(
            size: 3000,
            targetUrl: PromoLandingUrl::forRecipientToken($promoRecipient->token),
            actorUserId: (int) $request->user()->id,
        );

        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($promoRecipient->label)) ?: 'recipient';
        $slug = trim($slug, '-');

        return response($pngData)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="winprox-promo-'.$slug.'.png"');
    }
}
