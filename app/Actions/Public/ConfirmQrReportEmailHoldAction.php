<?php

namespace App\Actions\Public;

use App\Actions\Issues\CreateIssueAction;
use App\Models\Issue;
use App\Models\QrReportEmailHold;
use App\Support\Audit\AuditRecorder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Bevestigt een vastgehouden QR-melding via de e-maillink: maakt Issue + taken
 * (bestaande CreateIssueAction-keten) en koppelt eerder opgeslagen foto's.
 */
class ConfirmQrReportEmailHoldAction
{
    public function __construct(
        private CreateIssueAction $createIssue,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $token): Issue
    {
        return DB::transaction(function () use ($token) {
            $hold = QrReportEmailHold::withoutGlobalScopes()
                ->where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($hold === null) {
                throw ValidationException::withMessages([
                    'token' => [__('portal.report.verify_email_invalid')],
                ]);
            }

            Tenancy::actAs((int) $hold->tenant_id);

            if ($hold->confirmed_at !== null && $hold->issue_id !== null) {
                $existing = Issue::query()->find($hold->issue_id);
                if ($existing instanceof Issue) {
                    return $existing;
                }

                throw ValidationException::withMessages([
                    'token' => [__('portal.report.verify_email_invalid')],
                ]);
            }

            if ($hold->expires_at !== null && $hold->expires_at->isPast()) {
                throw ValidationException::withMessages([
                    'token' => [__('portal.report.verify_email_expired')],
                ]);
            }

            $unit = $hold->unit()->first();
            if ($unit === null) {
                throw ValidationException::withMessages([
                    'token' => [__('portal.report.verify_email_invalid')],
                ]);
            }

            $unit->loadMissing('category.teams');
            $teamIds = [];
            if ($unit->category !== null) {
                $team = $unit->category->teams()->first();
                if ($team !== null && $team->is_active) {
                    $teamIds = [$team->id];
                }
            }

            $issue = $this->createIssue->handle([
                'location_id' => $unit->location_id,
                'unit_id' => $unit->id,
                'reporter_name' => $hold->reporter_name,
                'reporter_contact' => $hold->reporter_contact,
                'description' => $hold->description,
                'source' => 'qr',
                'original_language' => $hold->original_language,
            ], $teamIds);

            foreach ($hold->storedPhotoPaths() as $path) {
                $issue->photos()->create(['path' => $path]);
            }

            $hold->forceFill([
                'confirmed_at' => now(),
                'issue_id' => $issue->id,
            ])->save();

            $this->audit->record(
                userId: null,
                tenantId: (int) $hold->tenant_id,
                action: 'qr_report_email_hold.confirmed',
                modelType: QrReportEmailHold::class,
                modelId: (int) $hold->id,
                payload: [
                    'issue_id' => $issue->id,
                    'unit_id' => $unit->id,
                ],
            );

            return $issue->fresh(['unit', 'photos']);
        });
    }
}
