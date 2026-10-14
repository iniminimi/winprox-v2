<?php

namespace App\Http\Controllers\Time;

use App\Actions\Time\BuildClockPointHomescreenManifestAction;
use Illuminate\Http\JsonResponse;

class TenantHomescreenManifestController
{
    public function __invoke(int $tenant, BuildClockPointHomescreenManifestAction $action): JsonResponse
    {
        $manifest = $action->handle($tenant);
        if ($manifest === null) {
            abort(404);
        }

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
