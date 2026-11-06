<?php

namespace App\Http\Controllers\Backoffice;

use App\Actions\Backoffice\BuildBackofficeHomescreenManifestAction;
use Illuminate\Http\JsonResponse;

class BackofficeHomescreenManifestController
{
    public function __invoke(BuildBackofficeHomescreenManifestAction $action): JsonResponse
    {
        return response()->json($action->handle(app()->getLocale()), 200, [
            'Content-Type' => 'application/manifest+json',
        ]);
    }
}
