<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Cameras\SubmitCameraClaimAction;
use App\Actions\Cameras\TouchCameraAction;
use App\Enums\CameraClaimStatus;
use App\Http\Requests\Cameras\SubmitCameraClaimRequest;
use App\Models\Camera;
use App\Models\CameraClaim;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CameraClaimController extends Controller
{
    public function store(SubmitCameraClaimRequest $request, SubmitCameraClaimAction $submit): JsonResponse
    {
        $claim = $submit->handle(
            (string) $request->validated('code'),
            (string) $request->validated('device_hint'),
            $request->ip(),
        );

        return $this->success([
            'claim_token' => $claim->claim_token,
            'status' => CameraClaimStatus::Pending->value,
        ], 201);
    }

    public function show(string $claimToken): JsonResponse
    {
        $claim = CameraClaim::query()
            ->withoutGlobalScopes()
            ->where('claim_token', $claimToken)
            ->first();

        if ($claim === null) {
            abort(404);
        }

        $data = [
            'status' => $claim->status->value,
        ];

        if ($claim->status === CameraClaimStatus::Confirmed && $claim->issued_token !== null && $claim->expires_at?->isFuture()) {
            $camera = $claim->camera()->withoutGlobalScopes()->first();
            $data['camera_key'] = $claim->issued_token;
            $data['forwarding'] = (bool) $camera?->forwarding_enabled;
        }

        return $this->success($data);
    }

    public function status(Request $request, TouchCameraAction $touch): JsonResponse
    {
        $camera = $request->attributes->get('camera');
        abort_unless($camera instanceof Camera, 401);

        $touch->handle($camera);

        return $this->success([
            'forwarding' => (bool) $camera->forwarding_enabled,
        ]);
    }
}
