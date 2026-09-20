<?php

declare(strict_types=1);

namespace App\Http\Controllers\Time;

use App\Actions\Time\ExportPresenceSubmissionsAction;
use App\Enums\PresenceSubmissionStatus;
use App\Models\PresenceSubmission;
use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PresenceSubmissionPrintController
{
    public function __invoke(Request $request, ExportPresenceSubmissionsAction $export): View
    {
        Gate::authorize('viewAny', PresenceSubmission::class);

        $tenant = Tenant::query()->findOrFail(Tenancy::id());
        $status = PresenceSubmissionStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        $result = $export->handle((int) $tenant->id, $status, $search);

        return view('reports.print-ciao', [
            'tenant' => $tenant,
            'submissions' => $result->rows,
            'truncated' => $result->truncated,
            'limit' => $result->limit,
        ]);
    }
}
