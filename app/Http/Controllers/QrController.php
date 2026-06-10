<?php

namespace App\Http\Controllers;

use App\Actions\QrCodes\RecordQrScanAction;
use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrController extends Controller
{
    public function __invoke(Request $request, string $token, RecordQrScanAction $recordScan)
    {
        $qrCode = QrCode::where('token', $token)->first();

        if (!$qrCode) {
            abort(404);
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
        $status = $qrCode->status;

        if ($status === QrCodeStatus::Damaged) {
            return response()->view('qr.damaged', [
                'stickerNumber' => $qrCode->display_sticker_number,
            ], 403);
        }

        if ($status === QrCodeStatus::Inactive) {
            return response()->view('qr.inactive', [
                'stickerNumber' => $qrCode->display_sticker_number,
            ], 403);
        }

        if ($status === QrCodeStatus::Unassigned) {
            return redirect()->route('public.unassigned-qr-portal', ['token' => $qrCode->token]);
        }

        if ($status === QrCodeStatus::Active) {
            if (!$qrCode->unit_id) {
                // Active but not linked - this shouldn't happen but handle gracefully
                return response()->view('qr.error', [
                    'stickerNumber' => $qrCode->display_sticker_number,
                ], 500);
            }

            // Redirect to the unit portal
            return redirect()->route('public.unit-portal', ['token' => $qrCode->unit->qr_token]);
        }

        // Fallback
        abort(404);
    }
}
