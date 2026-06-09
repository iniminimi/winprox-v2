<?php

namespace App\Mail\Contact;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email;

class ContactReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $mailLocale;

    public function __construct(
        public string $subjectText,
        public string $bodyText,
        public string $recipientName,
        public ?Tenant $tenant = null,
        public string $messageId = '',
        public string $inReplyTo = '',
        public string $references = '',
    ) {
        $this->mailLocale = $this->tenant?->locale ?? app()->getLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyText' => $this->bodyText,
                'recipientName' => $this->recipientName,
                'tenantName' => $this->tenant?->name ?? config('app.name'),
            ],
        );
    }

    public function build()
    {
        $this->withSymfonyMessage(function (Email $message) {
            if ($this->messageId !== '') {
                $message->getHeaders()->addIdHeader('Message-ID', $this->messageId);
            }
            if ($this->inReplyTo !== '') {
                $message->getHeaders()->addIdHeader('In-Reply-To', $this->inReplyTo);
            }
            if ($this->references !== '') {
                $message->getHeaders()->addIdHeader('References', $this->references);
            }
        });

        return $this;
    }
}
