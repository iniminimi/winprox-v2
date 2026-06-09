<?php

namespace App\Actions\Contact;

use App\Mail\Contact\NewOutboundMessageMail;
use App\Models\ContactMessage;
use App\Support\Audit\AuditRecorder;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendNewOutboundMessageAction
{
    public function __construct(
        private readonly AuditRecorder $auditRecorder,
    ) {}

    public function handle(
        string $recipientEmail,
        string $subject,
        string $body,
        ?int $actorUserId = null,
        ?int $tenantId = null,
        string $recipientName = '',
    ): ContactMessage {
        // Set tenant context if provided
        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        // Generate unique Message-ID
        $messageId = Str::uuid() . '@winprox.app';

        // Get tenant for email template (or null for global/SuperUser context)
        $tenant = $tenantId !== null ? Tenancy::getCurrentTenant() : null;

        // Send email
        $mail = new NewOutboundMessageMail(
            subjectText: $subject,
            bodyText: $body,
            recipientName: $recipientName,
            tenant: $tenant,
        );

        Mail::to($recipientEmail, $recipientName ?: null)->send($mail);

        // Store outbound message in database
        $fromName = config('mail.from.name', 'WinProx Support');
        $fromEmail = config('mail.from.address', 'info@winprox.app');

        $outboundMessage = ContactMessage::create([
            'message_id' => $messageId,
            'name' => $fromName,
            'email' => $fromEmail,
            'subject' => $subject,
            'message' => $body,
            'direction' => 'outbound',
            'read_at' => now(),
            'tenant_id' => $tenantId,
        ]);

        // Log audit event (use 0 for tenant_id if null, for audit log compatibility)
        $this->auditRecorder->record(
            userId: $actorUserId,
            tenantId: $tenantId ?? 0,
            action: 'contact.new_outbound_sent',
            modelType: 'ContactMessage',
            modelId: $outboundMessage->id,
            payload: [
                'recipient_email' => $recipientEmail,
                'subject' => $subject,
            ],
        );

        return $outboundMessage;
    }
}
