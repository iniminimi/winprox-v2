<?php

namespace App\Support\Gdpr;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueUpdate;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Bouwt een machineleesbare GDPR-export voor de ingelogde gebruiker.
 */
final class UserDataExporter
{
    public const SCHEMA_VERSION = 1;

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        $exportedAt = Carbon::now()->toIso8601String();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => $exportedAt,
            'user' => $this->userPayload($user),
            'tenant' => $this->tenantPayload($user),
            'issues_approved' => $this->issuesApprovedPayload($user),
            'issue_updates' => $this->issueUpdatesPayload($user),
            'audit_log' => $this->auditLogPayload($user),
            'api_tokens' => $this->apiTokensPayload($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'is_active' => $user->is_active,
            'is_superuser' => $user->is_superuser,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'updated_at' => $user->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tenantPayload(User $user): ?array
    {
        $tenant = $user->tenant;

        if ($tenant === null) {
            return null;
        }

        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'email' => $tenant->email,
            'phone' => $tenant->phone,
            'street' => $tenant->street,
            'house_number' => $tenant->house_number,
            'postal_code' => $tenant->postal_code,
            'city' => $tenant->city,
            'country_code' => $tenant->country_code,
            'is_active' => $tenant->is_active,
            'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
            'billing_plan' => $tenant->billing_plan,
            'billing_active_until' => $tenant->billing_active_until?->toIso8601String(),
            'stripe_customer_linked' => filled($tenant->stripe_customer_id),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function issuesApprovedPayload(User $user): array
    {
        return Issue::query()
            ->withoutGlobalScopes()
            ->where('approved_by', $user->id)
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'location_id', 'unit_id', 'status', 'source', 'approved_at', 'created_at', 'updated_at'])
            ->map(fn (Issue $issue) => [
                'id' => $issue->id,
                'tenant_id' => $issue->tenant_id,
                'location_id' => $issue->location_id,
                'unit_id' => $issue->unit_id,
                'status' => $issue->status instanceof \BackedEnum ? $issue->status->value : $issue->status,
                'source' => $issue->source instanceof \BackedEnum ? $issue->source->value : $issue->source,
                'approved_at' => $issue->approved_at?->toIso8601String(),
                'created_at' => $issue->created_at?->toIso8601String(),
                'updated_at' => $issue->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function issueUpdatesPayload(User $user): array
    {
        return IssueUpdate::query()
            ->withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->get(['id', 'tenant_id', 'issue_id', 'kind', 'body', 'created_at', 'updated_at'])
            ->map(fn (IssueUpdate $update) => [
                'id' => $update->id,
                'tenant_id' => $update->tenant_id,
                'issue_id' => $update->issue_id,
                'kind' => $update->kind,
                'body' => $update->body,
                'created_at' => $update->created_at?->toIso8601String(),
                'updated_at' => $update->updated_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function auditLogPayload(User $user): array
    {
        return AuditLog::query()
            ->where('user_id', $user->id)
            ->orderBy('id')
            ->limit(5000)
            ->get(['id', 'tenant_id', 'action', 'model_type', 'model_id', 'payload', 'created_at'])
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'tenant_id' => $log->tenant_id,
                'action' => $log->action,
                'model_type' => $log->model_type,
                'model_id' => $log->model_id,
                'payload' => $log->payload,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function apiTokensPayload(User $user): array
    {
        return $user->tokens()
            ->orderBy('id')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at', 'expires_at'])
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
                'expires_at' => $token->expires_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
