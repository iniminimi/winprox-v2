<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class HelpChatUnansweredToHelpdeskMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $question,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('help.mail.unanswered_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.help-chat-unanswered',
        );
    }
}
