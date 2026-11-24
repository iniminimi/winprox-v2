<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Screens\SubmitScreenClaimAction;
use App\Enums\ScreenClaimStatus;
use App\Http\Requests\Screens\SubmitScreenClaimRequest;
use App\Models\ScreenClaim;
use Illuminate\Http\JsonResponse;

class ScreenClaimController extends Controller
{
    public function store(SubmitScreenClaimRequest $request, SubmitScreenClaimAction $submit): JsonResponse
    {
        $claim = $submit->handle(
            (string) $request->validated('code'),
            (string) $request->validated('device_hint'),
            $request->ip(),
        );

        return $this->success([
            'claim_token' => $claim->claim_token,
            'status' => ScreenClaimStatus::Pending->value,
        ], 201);
    }

    public function show(string $claimToken): JsonResponse
    {
        $claim = ScreenClaim::query()
            ->withoutGlobalScopes()
            ->where('claim_token', $claimToken)
            ->first();

        if ($claim === null) {
            abort(404);
        }

        $data = [
            'status' => $claim->status->value,
        ];

        if ($claim->status === ScreenClaimStatus::Confirmed && $claim->issued_token !== null && $claim->expires_at?->isFuture()) {
            $data['sense_key'] = $claim->issued_token;
            $data['role'] = 'sense';
        }

        return $this->success($data);
    }
}
