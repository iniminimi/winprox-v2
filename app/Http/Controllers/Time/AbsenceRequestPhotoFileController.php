<?php

namespace App\Http\Controllers\Time;

use App\Models\AbsenceRequest;
use App\Models\AbsenceRequestPhoto;
use App\Models\User;
use App\Support\Portal\WorkerDeviceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Toont een foto bij een afwezigheid. Privé-disk: de uitvoerder zelf, of wie de aanvraag mag zien.
 */
class AbsenceRequestPhotoFileController
{
    public function __invoke(Request $request, int $photo): StreamedResponse
    {
        $row = AbsenceRequestPhoto::query()->withoutGlobalScopes()->findOrFail($photo);
        $absence = AbsenceRequest::query()->withoutGlobalScopes()->find($row->absence_request_id);
        abort_unless($absence instanceof AbsenceRequest, 404);

        $user = $request->user();
        if ($user instanceof User && Gate::forUser($user)->allows('view', $absence)) {
            return $this->stream($row);
        }

        $worker = WorkerDeviceSession::rememberedWorkerForTenant((int) $row->tenant_id);
        abort_unless(
            $worker !== null && (int) $worker->id === (int) $absence->worker_id,
            403,
        );

        return $this->stream($row);
    }

    private function stream(AbsenceRequestPhoto $photo): StreamedResponse
    {
        $disk = Storage::disk(AbsenceRequestPhoto::DISK);
        abort_unless($disk->exists((string) $photo->path), 404);

        return $disk->response((string) $photo->path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
