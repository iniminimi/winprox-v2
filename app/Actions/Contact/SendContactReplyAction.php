<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Contracts\WebhookEvent;
use Symfony\Component\Mime\Header\IdentificationHeader;

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

        // Log audit event
        $this->auditRecorder->record(
            userId: $actorUserId,
            tenantId: $tenantId ?? 0,
            action: 'contact.reply_sent',
            modelType: 'ContactMessage',
            modelId: $outboundMessage->id,
            payload: [
                'original_message_id' => $originalMessage->id,
                'recipient_email' => $originalMessage->email,
                'subject' => $outboundMessage->subject,
            ],
        );

        // Dispatch webhook event
        WebhookEvent::dispatch([
            'event_type' => 'contact_reply_sent',
            'data' => [
                'original_message_id' => $originalMessage->id,
                'reply_message_id' => $outboundMessage->id,
                'recipient_email' => $originalMessage->email,
                'tenant_id' => $tenantId,
                'actor_user_id' => $actorUserId,
            ],
            'created_at' => now(),
        ]);

        return $outboundMessage;
    }

    private function sendEmail(string $reply, ContactMessage $originalMessage): string
    {
        // Generate unique Message-ID (without angle brackets - IdentificationHeader adds them)
        $messageId = Str::uuid() . '@winprox.app';

        // Build the email
        $email = Mail::raw($reply, function ($message) use ($originalMessage, $messageId) {
            $message
                ->to($originalMessage->email, $originalMessage->name)
                ->subject('Re: ' . $originalMessage->subject)
                ->from(config('mail.from.address', 'info@winprox.app'), config('mail.from.name', 'WinProx Support'));

            $headers = $message->getHeaders();

            // Strip angle brackets if present (IMAP stores them with brackets, IdentificationHeader adds them)
            $originalMessageId = trim($originalMessage->message_id, '<>');

            // Use IdentificationHeader for proper header type
            $headers->add(new IdentificationHeader('Message-ID', [$messageId]));
            $headers->add(new IdentificationHeader('In-Reply-To', [$originalMessageId]));
            $headers->add(new IdentificationHeader('References', [$originalMessageId]));
        });

        return $messageId;
    }
}
