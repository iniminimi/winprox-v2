<?php

declare(strict_types=1);

namespace App\Http\Controllers\Ops;

use App\Actions\Ops\BuildOpsHomescreenManifestAction;
use Illuminate\Http\JsonResponse;

class OpsHomescreenManifestController
{
    public function __invoke(BuildOpsHomescreenManifestAction $action): JsonResponse
    {
        return response()->json($action->handle(), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
