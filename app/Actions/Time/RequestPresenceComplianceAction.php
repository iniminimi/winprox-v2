<?php

namespace App\Actions\Time;

use App\Enums\PresenceComplianceScope;
use App\Mail\PresenceComplianceRequestedMail;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

/**
 * CIAO self-service (Checkmate): tenant dient ondernemingsnummer in,
 * WinProx bevestigt daarna handmatig via platform (presence_compliance_enabled).
 * Zolang de aanvraag pending is, worden geen presence-submissions aangemaakt —
 * compliance geldt pas vanaf activatie, geen backfill (docs/CHECKMATE.md §6).
 */
class RequestPresenceComplianceAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{enterprise_number?: string|null, foreign_vat_number?: string|null}  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Tenant
    {
        if ($tenant->presence_compliance_enabled) {
            throw new InvalidArgumentException('presence_already_enabled');
        }

        if ($tenant->presenceComplianceRequested()) {
            throw new InvalidArgumentException('presence_request_pending');
        }

        $enterpriseNumber = preg_replace('/\D+/', '', (string) ($data['enterprise_number'] ?? '')) ?? '';
        if ($enterpriseNumber === '') {
            throw new InvalidArgumentException('enterprise_number_required');
        }

        $foreignVat = trim((string) ($data['foreign_vat_number'] ?? ''));

        $tenant->update([
            'enterprise_number' => $enterpriseNumber,
            'foreign_vat_number' => $foreignVat !== '' ? $foreignVat : $tenant->foreign_vat_number,
            'presence_compliance_scope' => $tenant->presence_compliance_scope
                ?? PresenceComplianceScope::CiaoCleaning->value,
        ]);

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.presence_compliance_requested',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'id' => $fresh->id,
                'enterprise_number' => $fresh->enterprise_number,
            ],
        );

        $opsEmail = config('winprox.new_tenant_notification_email');
        if (is_string($opsEmail) && $opsEmail !== '') {
            Mail::to($opsEmail)->send(new PresenceComplianceRequestedMail($fresh));
        }

        return $fresh;
    }
}
