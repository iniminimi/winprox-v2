<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Actions\Audit\LogAuditAction;
use App\Enums\EmailUnsubscribeSource;
use App\Models\EmailUnsubscribe;

class SetEmailSubscriptionAction
{
    public function __construct(private LogAuditAction $logAudit) {}

    public function handle(
        string $email,
        bool $unsubscribed,
        ?int $actorUserId = null,
        ?EmailUnsubscribeSource $source = null,
    ): void {
        $email = EmailUnsubscribe::normalizeEmail($email);

        if ($unsubscribed) {
            $resolvedSource = $source
                ?? ($actorUserId !== null
                    ? EmailUnsubscribeSource::Manual
                    : EmailUnsubscribeSource::Voluntary);

            $row = EmailUnsubscribe::query()->firstOrNew(['email' => $email]);
            $row->source = $resolvedSource;
            $row->unsubscribed_at = now();
            $row->save();

            $this->logAudit->handle(
                userId: $actorUserId,
                tenantId: null,
                action: 'email.unsubscribed',
                modelType: 'EmailUnsubscribe',
                modelId: $row->id,
                payload: [
                    'email' => $email,
                    'source' => $resolvedSource->value,
                ],
            );

            return;
        }

        $existing = EmailUnsubscribe::query()->where('email', $email)->first();
        EmailUnsubscribe::query()->where('email', $email)->delete();

        $this->logAudit->handle(
            userId: $actorUserId,
            tenantId: null,
            action: 'email.resubscribed',
            modelType: 'EmailUnsubscribe',
            modelId: $existing?->id,
            payload: [
                'email' => $email,
                'source' => $existing?->source?->value,
            ],
        );
    }
}
