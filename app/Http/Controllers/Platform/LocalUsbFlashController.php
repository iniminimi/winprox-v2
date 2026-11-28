<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Actions\Ops\DetectUsbFlashTargetAction;
use App\Actions\Ops\FlashUsbClockAction;
use App\Data\Ops\UsbFlashSighting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocalUsbFlashController
{
    public function detect(DetectUsbFlashTargetAction $detect): JsonResponse
    {
        return $this->payload($detect->handle());
    }

    public function run(Request $request, FlashUsbClockAction $flash): JsonResponse
    {
        $mac = strtoupper((string) $request->input('mac', ''));
        $userId = $request->user()?->id;

        return $this->payload($flash->handle($mac, $userId !== null ? (int) $userId : null));
    }

    private function payload(UsbFlashSighting $seen): JsonResponse
    {
        return response()->json([
            'status' => $seen->status,
            'type' => $seen->typeLabel,
            'mac' => $seen->macDisplay,
            'raw' => $seen->mac,
            'message' => $seen->message(),
        ]);
    }
}
