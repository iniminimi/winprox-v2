<?php

namespace App\Http\Controllers;

use App\Actions\QrCodes\RecordQrScanAction;
use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Support\Qr\InvalidQrResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrController extends Controller
{
    public function __invoke(Request $request, string $token, RecordQrScanAction $recordScan)
    {
        $qrCode = QrCode::where('token', $token)->first();

        if (! $qrCode) {
            return InvalidQrResponse::make();
        }

        $qrCode = $recordScan->handle(
            $qrCode,
            Auth::id(),
            $request->ip(),
            $request->userAgent(),
        );

        return $this->handleQrCode($qrCode);
    }

    protected function handleQrCode(QrCode $qrCode)
    {
        if ($qrCode->status === QrCodeStatus::Unassigned) {
            return redirect()->route('public.unassigned-qr-portal', ['token' => $qrCode->token]);
        }

        if ($qrCode->status === QrCodeStatus::Active && $qrCode->unit_id) {
            return redirect()->route('public.unit-portal', ['token' => $qrCode->unit->qr_token]);
        }

        return InvalidQrResponse::make();
    }
}
