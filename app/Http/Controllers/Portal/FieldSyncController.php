<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\SyncFieldOutboxItemAction;
use App\Enums\FieldSyncType;
use App\Exceptions\Portal\FieldSyncException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\FieldSyncRequest;
use App\Models\Unit;
use App\Support\Portal\WorkerDeviceSession;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;

class FieldSyncController extends Controller
{
    public function __invoke(FieldSyncRequest $request, SyncFieldOutboxItemAction $sync): JsonResponse
    {
        $worker = WorkerDeviceSession::resolveWorker(
            WorkerDeviceSession::deviceTokenFromRequest($request),
        );
        if ($worker === null) {
            return $this->error(__('portal.worker.errors.no_permission'), 403);
        }

        $unit = Unit::withoutGlobalScope('tenant')
            ->with(['location', 'category.teams', 'tenant'])
            ->where('qr_token', (string) $request->validated('unit_token'))
            ->first();

        if ($unit === null) {
            return $this->error(__('portal.worker.errors.no_permission'), 403);
        }

        Tenancy::actAs((int) $unit->tenant_id);

        $type = FieldSyncType::from((string) $request->validated('type'));
        $photos = array_values(array_filter(
            $request->file('photos', []) ?? [],
            fn ($file) => $file instanceof UploadedFile,
        ));

        try {
            $result = $sync->handle(
                worker: $worker,
                clientId: (string) $request->validated('client_id'),
                type: $type,
                unit: $unit,
                payload: $request->decodedPayload(),
                photos: $photos,
            );
        } catch (FieldSyncException $exception) {
            return $this->error($exception->getMessage(), $exception->status, $exception->errors);
        }

        return response()->json([
            'ok' => $result->ok,
            'replayed' => $result->replayed,
            'type' => $type->value,
            'client_id' => (string) $request->validated('client_id'),
            'result' => $result->result,
            'error' => $result->error !== '' ? $result->error : null,
            'errors' => $result->errors,
        ], $result->httpStatus);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private function error(string $message, int $status, array $errors = []): JsonResponse
    {
        return response()->json([
            'ok' => false,
            'replayed' => false,
            'error' => $message,
            'errors' => $errors,
        ], $status);
    }
}
