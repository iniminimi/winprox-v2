<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\BuildClockPointHomescreenManifestAction;
use Illuminate\Http\JsonResponse;

class ClockPointHomescreenManifestController
{
    public function __invoke(string $token, BuildClockPointHomescreenManifestAction $action): JsonResponse
    {
        $manifest = $action->handle($token);
        if ($manifest === null) {
            abort(404);
        }

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
