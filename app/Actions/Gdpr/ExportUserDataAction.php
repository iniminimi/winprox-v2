<?php

namespace App\Actions\Gdpr;

use App\Models\User;
use App\Support\Audit\AuditRecorder;
use App\Support\Gdpr\UserDataExporter;

final class ExportUserDataAction
{
    public function __construct(
        private UserDataExporter $exporter,
        private AuditRecorder $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user): array
    {
        $payload = $this->exporter->export($user);

        if ($user->tenant_id !== null) {
            $this->audit->record(
                userId: $user->id,
                tenantId: (int) $user->tenant_id,
                action: 'gdpr.data_exported',
                modelType: User::class,
                modelId: $user->id,
            );
        }

        return $payload;
    }
}
