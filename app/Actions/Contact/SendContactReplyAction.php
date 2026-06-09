<?php

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendContactReplyAction
{
    public function handle(string $reply, ContactMessage $originalMessage, int $tenantId, int $actorUserId): ContactMessage
    {
        Tenancy::actAs($tenantId);

        // Send email via Laravel Mail facade
        $messageId = $this->sendEmail($reply, $originalMessage);

        // Store outbound message in database
        $outboundMessage = ContactMessage::create([
            'message_id' => $messageId,
            'name' => 'WinProx Support',
            'email' => 'info@winprox.app',
            'subject' => 'Re: ' . $originalMessage->subject,
            'message' => $reply,
            'direction' => 'outbound',
        ]);

        return $outboundMessage;
    }

    private function sendEmail(string $reply, ContactMessage $originalMessage): string
    {
        // Generate unique Message-ID
        $messageId = '<' . Str::uuid() . '@winprox.app>';
        
        // Build the email
        $email = Mail::raw($reply, function ($message) use ($originalMessage, $messageId) {
            $message
                ->to($originalMessage->email, $originalMessage->name)
                ->subject('Re: ' . $originalMessage->subject)
                ->from('info@winprox.app', 'WinProx Support')
                ->getHeaders()
                ->addTextHeader('Message-ID', $messageId)
                ->addTextHeader('In-Reply-To', $originalMessage->message_id)
                ->addTextHeader('References', $originalMessage->message_id);
        });

        return $messageId;
    }
}
