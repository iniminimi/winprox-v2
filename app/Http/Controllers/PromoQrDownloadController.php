<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\GeneratePromoQrCodeAction;
use App\Enums\PromoLanding;
use App\Support\Marketing\PromoLandingUrl;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PromoQrDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, GeneratePromoQrCodeAction $action): Response
    {
        $this->authorize('downloadPromoQr', $request->user());

        $landing = PromoLanding::tryFrom((string) $request->query('landing', PromoLanding::default()->value))
            ?? PromoLanding::default();

        $pngData = $action->handle(
            size: 3000,
            targetUrl: PromoLandingUrl::anonymous(landing: $landing),
            actorUserId: (int) $request->user()->id,
        );

        return response($pngData)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="winprox-'.$landing->value.'.png"');
    }
}
