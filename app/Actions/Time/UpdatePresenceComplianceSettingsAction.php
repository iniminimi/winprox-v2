<?php

namespace App\Actions\Time;

use App\Enums\PresenceComplianceScope;
use App\Models\Tenant;
use App\Support\Audit\AuditRecorder;
use App\Support\Time\TimeModuleAccess;
use InvalidArgumentException;

class UpdatePresenceComplianceSettingsAction
{
    public function __construct(private AuditRecorder $audit) {}

    /**
     * @param  array{
     *     presence_compliance_scope?: string|null,
     *     enterprise_number?: string|null,
     *     foreign_vat_number?: string|null,
     *     presence_rsz_client_id?: string|null,
     *     presence_rsz_private_key?: string|null,
     *     clear_private_key?: bool
     * }  $data
     */
    public function handle(Tenant $tenant, array $data, ?int $actorUserId = null): Tenant
    {
        if (! TimeModuleAccess::tenantHasModule($tenant)) {
            throw new InvalidArgumentException('time_module_disabled');
        }

        if (! $tenant->presence_compliance_enabled) {
            throw new InvalidArgumentException('presence_compliance_locked');
        }

        $updates = [];

        if (array_key_exists('enterprise_number', $data)) {
            $raw = preg_replace('/\D+/', '', (string) ($data['enterprise_number'] ?? '')) ?? '';
            $updates['enterprise_number'] = $raw !== '' ? $raw : null;
        }

        if (array_key_exists('foreign_vat_number', $data)) {
            $vat = trim((string) ($data['foreign_vat_number'] ?? ''));
            $updates['foreign_vat_number'] = $vat !== '' ? $vat : null;
        }

        if (array_key_exists('presence_compliance_scope', $data)) {
            $scopeRaw = $data['presence_compliance_scope'];
            if ($scopeRaw === null || $scopeRaw === '') {
                $updates['presence_compliance_scope'] = null;
            } else {
                $scope = PresenceComplianceScope::from((string) $scopeRaw);
                if (! $scope->isAvailable()) {
                    throw new InvalidArgumentException('presence_scope_unavailable');
                }
                $updates['presence_compliance_scope'] = $scope->value;
            }
        }

        if (array_key_exists('presence_rsz_client_id', $data)) {
            $clientId = trim((string) ($data['presence_rsz_client_id'] ?? ''));
            // Lege string = niet wijzigen (wachtwoordachtig veld).
            if ($clientId !== '') {
                $updates['presence_rsz_client_id'] = $clientId;
            }
        }

        if (! empty($data['clear_private_key'])) {
            $updates['presence_rsz_private_key'] = null;
        } elseif (array_key_exists('presence_rsz_private_key', $data)) {
            $key = trim((string) ($data['presence_rsz_private_key'] ?? ''));
            if ($key !== '') {
                $updates['presence_rsz_private_key'] = $key;
            }
        }

        if ($updates !== []) {
            $tenant->update($updates);
        }

        $fresh = $tenant->fresh();

        $this->audit->record(
            userId: $actorUserId,
            tenantId: (int) $fresh->id,
            action: 'tenant.presence_compliance_updated',
            modelType: Tenant::class,
            modelId: (int) $fresh->id,
            payload: [
                'presence_compliance_enabled' => $fresh->presence_compliance_enabled,
                'presence_compliance_scope' => $fresh->presence_compliance_scope,
                'has_enterprise_number' => filled($fresh->enterprise_number),
                'has_foreign_vat_number' => filled($fresh->foreign_vat_number),
                'has_rsz_client_id' => filled($fresh->presence_rsz_client_id),
                'has_rsz_private_key' => filled($fresh->presence_rsz_private_key),
            ],
        );

        return $fresh;
    }
}
