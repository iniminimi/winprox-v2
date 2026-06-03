<?php

namespace App\Http\Controllers;

use App\Models\QrCode;
use App\Models\QrScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QrController extends Controller
{
    public function __invoke(Request $request, string $token)
    {
        $qrCode = QrCode::where('token', $token)->first();

        if (!$qrCode) {
            abort(404);
        }

        // Log the scan
        $this->logScan($qrCode, $request);

        // Update last scanned timestamp
        $qrCode->update(['last_scanned_at' => now()]);

        return $this->handleQrCode($qrCode);
    }

    protected function logScan(QrCode $qrCode, Request $request): void
    {
        QrScan::create([
            'qr_code_id' => $qrCode->id,
            'tenant_id' => $qrCode->tenant_id,
            'user_id' => Auth::id(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'scanned_at' => now(),
        ]);
    }

    protected function handleQrCode(QrCode $qrCode)
    {
        $status = $qrCode->status;

        // Damaged QR code
        if ($status->value === 'damaged') {
            return response()->view('qr.damaged', [
                'stickerNumber' => $qrCode->sticker_number,
            ], 403);
        }

        // Inactive QR code
        if ($status->value === 'inactive') {
            return response()->view('qr.inactive', [
                'stickerNumber' => $qrCode->sticker_number,
            ], 403);
        }

        // Unassigned QR code
        if ($status->value === 'unassigned') {
            // Always redirect to unassigned portal (portal handles login + linking)
            return redirect()->route('public.unassigned-qr-portal', ['token' => $qrCode->token]);
        }

        // Active QR code - should be linked to a unit
        if ($status->value === 'active') {
            if (!$qrCode->unit_id) {
                // Active but not linked - this shouldn't happen but handle gracefully
                return response()->view('qr.error', [
                    'stickerNumber' => $qrCode->sticker_number,
                ], 500);
            }

            // Redirect to the unit portal
            return redirect()->route('public.unit-portal', ['token' => $qrCode->unit->qr_token]);
        }

        // Fallback
        abort(404);
    }
}
