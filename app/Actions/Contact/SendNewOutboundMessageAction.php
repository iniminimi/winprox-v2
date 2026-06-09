<?php

namespace App\Actions\Contact;

use App\Mail\Contact\NewOutboundMessageMail;
use App\Models\ContactMessage;
use App\Models\EmailUnsubscribe;
use App\Support\Audit\AuditRecorder;
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
        string $recipientName = '',
    ): ContactMessage {
        $normalizedEmail = EmailUnsubscribe::normalizeEmail($recipientEmail);

        if (EmailUnsubscribe::isUnsubscribed($normalizedEmail)) {
            throw new \RuntimeException(__('contact-messages.recipient_unsubscribed'));
        }

        // Generate unique Message-ID
        $messageId = Str::uuid() . '@winprox.app';

        // Send email (SuperUser sends globally, no tenant context)
        $mail = new NewOutboundMessageMail(
            subjectText: $subject,
            bodyText: $body,
            recipientName: $recipientName,
            tenant: null,
        );

        Mail::to($recipientEmail, $recipientName ?: null)->send($mail);

        // Store outbound message in database (SuperUser sends globally, no tenant)
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
        ]);

        // Log audit event (no tenant for SuperUser global send - null tenant_id for FK compatibility)
        $this->auditRecorder->record(
            userId: $actorUserId,
            tenantId: null,
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
