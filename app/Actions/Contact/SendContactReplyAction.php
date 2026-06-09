<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Mail\Contact\ContactReplyMail;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Events\Contact\ContactReplySent;

class SendContactReplyAction
{
    public function __construct(private \App\Support\Audit\AuditRecorder $auditRecorder) {}

    public function handle(string $reply, ContactMessage $originalMessage, ?int $tenantId = null, ?int $actorUserId = null): ContactMessage
    {
        if ($tenantId !== null) {
            Tenancy::actAs($tenantId);
        }

        // Send email via Laravel Mail facade
        $messageId = $this->sendEmail($reply, $originalMessage);

        // Store outbound message in database
        $fromName = config('mail.from.name', 'WinProx Support');
        $fromEmail = config('mail.from.address', 'info@winprox.app');

        $outboundMessage = ContactMessage::create([
            'message_id' => $messageId,
            'name' => $fromName,
            'email' => $fromEmail,
            'subject' => 'Re: ' . $originalMessage->subject,
            'message' => $reply,
            'direction' => 'outbound',
            'tenant_id' => $tenantId, // Can be null for SuperUser global replies
        ]);

        // Log audit event only if we have a valid tenant (skip for orphaned IMAP messages)
        if ($tenantId !== null && $tenantId > 0) {
            $this->auditRecorder->record(
                userId: $actorUserId,
                tenantId: $tenantId,
                action: 'contact.reply_sent',
                modelType: 'ContactMessage',
                modelId: $outboundMessage->id,
                payload: [
                    'original_message_id' => $originalMessage->id,
                    'recipient_email' => $originalMessage->email,
                    'subject' => $outboundMessage->subject,
                ],
            );
        }

        // Dispatch webhook event
        ContactReplySent::dispatch($originalMessage, $outboundMessage, $actorUserId);

        return $outboundMessage;
    }

    private function sendEmail(string $reply, ContactMessage $originalMessage): string
    {
        $messageId = Str::uuid() . '@winprox.app';

        // Strip angle brackets if present (IMAP stores them with brackets)
        $originalMessageId = trim($originalMessage->message_id, '<>');

        $mail = new ContactReplyMail(
            subjectText: 'Re: ' . $originalMessage->subject,
            bodyText: $reply,
            recipientName: $originalMessage->name ?? '',
            tenant: null,
            messageId: $messageId,
            inReplyTo: $originalMessageId,
            references: $originalMessageId,
        );

        Mail::to($originalMessage->email, $originalMessage->name ?: null)
            ->send($mail);

        return $messageId;
    }
}
