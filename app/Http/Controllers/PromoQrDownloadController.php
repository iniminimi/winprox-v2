<?php

namespace App\Http\Controllers;

use App\Actions\Marketing\GeneratePromoQrCodeAction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PromoQrDownloadController extends Controller
{
    use AuthorizesRequests;

    public function __invoke(Request $request, GeneratePromoQrCodeAction $action): Response
    {
        $this->authorize('downloadPromoQr', $request->user());

        $pngData = $action->handle(
            size: 3000,
            targetUrl: 'https://winprox.app/promo',
            actorUserId: (int) $request->user()->id,
        );

        return response($pngData)
            ->header('Content-Type', 'image/png')
            ->header('Content-Disposition', 'attachment; filename="winprox-voetbal-promo-qr.png"');
    }
}
