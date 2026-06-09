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
        string $recipientName,
        string $subject,
        string $body,
        int $tenantId,
        ?int $actorUserId = null,
    ): ContactMessage {
        // Set tenant context
        Tenancy::actAs($tenantId);

        // Generate unique Message-ID
        $messageId = Str::uuid() . '@winprox.app';

        // Send email
        $mail = new NewOutboundMessageMail(
            subjectText: $subject,
            bodyText: $body,
            recipientName: $recipientName,
            tenant: Tenancy::getCurrentTenant(),
        );

        Mail::to($recipientEmail, $recipientName)->send($mail);

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

        // Log audit event
        $this->auditRecorder->record(
            userId: $actorUserId,
            tenantId: $tenantId,
            action: 'contact.new_outbound_sent',
            modelType: 'ContactMessage',
            modelId: $outboundMessage->id,
            payload: [
                'recipient_email' => $recipientEmail,
                'recipient_name' => $recipientName,
                'subject' => $subject,
            ],
        );

        return $outboundMessage;
    }
}
