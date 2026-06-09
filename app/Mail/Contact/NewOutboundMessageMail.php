<?php

namespace App\Mail\Contact;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewOutboundMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $locale;

    public function __construct(
        public string $subjectText,
        public string $bodyText,
        public string $recipientName,
        public ?Tenant $tenant = null,
    ) {
        $this->locale = $this->tenant?->locale ?? app()->getLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->subjectText,
        );
    }

    public function content(): Content
    {
        return (new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyText' => $this->bodyText,
                'recipientName' => $this->recipientName,
                'tenantName' => $this->tenant?->name ?? config('app.name'),
            ],
        ))->locale($this->locale);
    }
}
