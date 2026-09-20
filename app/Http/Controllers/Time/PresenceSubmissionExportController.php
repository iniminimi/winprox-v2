<?php

declare(strict_types=1);

namespace App\Http\Controllers\Time;

use App\Actions\Time\ExportPresenceSubmissionsAction;
use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use App\Support\Reports\CsvStreamer;
use App\Support\Tenancy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PresenceSubmissionExportController
{
    public function __invoke(Request $request, ExportPresenceSubmissionsAction $export): StreamedResponse
    {
        Gate::authorize('viewAny', PresenceSubmission::class);

        $status = PresenceSubmissionStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $result = $export->handle((int) Tenancy::id(), $status, $search);
        $filename = __('reports.ciao.filename').'-'.now()->format('Y-m-d').'.csv';

        $headers = [
            __('time.ciao.export.columns.when'),
            __('time.ciao.export.columns.worker'),
            __('time.ciao.export.columns.event'),
            __('time.ciao.export.columns.type'),
            __('time.ciao.export.columns.location'),
            __('time.ciao.export.columns.status'),
            __('time.ciao.export.columns.rsz_id'),
            __('time.ciao.export.columns.validity'),
            __('time.ciao.export.columns.error'),
        ];

        $rows = [];
        if ($result->truncated) {
            $rows[] = [__('reports.truncated', ['limit' => $result->limit]), '', '', '', '', '', '', '', ''];
        }

        $tz = (string) config('app.timezone');

        foreach ($result->rows as $submission) {
            $rows[] = [
                $submission->registration_at?->timezone($tz)->format('Y-m-d H:i') ?? '',
                $submission->worker?->displayName() ?? '',
                __('time.ciao.event.'.$submission->source_event->value),
                $submission->presence_type->value,
                $submission->location?->name ?? '',
                __('time.ciao.status.'.$submission->status->value),
                $submission->rsz_id !== null ? (string) $submission->rsz_id : '',
                $submission->rsz_validity ?? '',
                $submission->error_message
                    ? explode(':', (string) $submission->error_message, 2)[0]
                    : '',
            ];
        }

        return CsvStreamer::download($filename, $headers, $rows);
    }
}
