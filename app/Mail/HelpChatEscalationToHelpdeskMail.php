<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HelpChatEscalationToHelpdeskMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $question,
        public ?string $assistantReply = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('help.mail.escalation_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.help-chat-escalation',
        );
    }
}
