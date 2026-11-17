<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Notifications\SaveWorkerPushSubscriptionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SaveWorkerPushSubscriptionRequest;
use App\Support\Portal\WorkerDeviceSession;
use Illuminate\Http\Response;

class SaveWorkerPushSubscriptionController extends Controller
{
    public function __invoke(
        SaveWorkerPushSubscriptionRequest $request,
        SaveWorkerPushSubscriptionAction $save,
    ): Response {
        $saved = $save->handle(
            deviceToken: WorkerDeviceSession::deviceTokenFromRequest($request),
            endpoint: (string) $request->validated('endpoint'),
            publicKey: (string) $request->validated('public_key'),
            authToken: (string) $request->validated('auth_token'),
            locale: (string) $request->validated('locale'),
        );

        abort_unless($saved, 403);

        return response()->noContent();
    }
}
