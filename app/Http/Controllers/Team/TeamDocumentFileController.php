<?php

namespace App\Http\Controllers\Team;

use App\Models\TeamDocument;
use App\Models\User;
use App\Support\Portal\WorkerDeviceSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TeamDocumentFileController
{
    public function __invoke(Request $request, int $teamDocument): StreamedResponse
    {
        $document = TeamDocument::query()->withoutGlobalScopes()->findOrFail($teamDocument);

        $user = $request->user();
        if ($user instanceof User && Gate::forUser($user)->allows('update', $document)) {
            return $this->stream($document);
        }

        $worker = WorkerDeviceSession::rememberedWorkerForTenant((int) $document->tenant_id);
        abort_unless($worker !== null && $document->is_active, 403);
        abort_unless($document->published_at === null || $document->published_at->lte(now()), 403);
        abort_unless((int) $worker->internal_team_id === (int) $document->internal_team_id, 403);
        abort_unless(
            $document->worker_id === null || (int) $document->worker_id === (int) $worker->id,
            403,
        );

        return $this->stream($document);
    }

    private function stream(TeamDocument $document): StreamedResponse
    {
        abort_unless(Storage::disk(TeamDocument::DISK)->exists($document->file_path), 404);

        $filename = $document->title;
        $extension = pathinfo($document->file_path, PATHINFO_EXTENSION);
        if ($extension !== '' && ! str_ends_with(mb_strtolower($filename), '.'.mb_strtolower($extension))) {
            $filename .= '.'.$extension;
        }

        return Storage::disk(TeamDocument::DISK)->download(
            $document->file_path,
            $filename,
        );
    }
}
