<?php

namespace App\Mail\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PromoCampaignLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $emailSubject,
        public string $emailBodyHtml,
        public ?string $docxPath,
        public string $mailLocale,
    ) {
        $this->locale($mailLocale);
        $this->mailer('municipal_promo');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('winprox.municipal_promo_email_from.address'),
                (string) config('winprox.municipal_promo_email_from.name'),
            ),
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'bodyHtml' => $this->emailBodyHtml,
                'bodyText' => '',
                'recipientName' => '',
                'subject' => $this->emailSubject,
            ],
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        if ($this->docxPath === null || ! is_file($this->docxPath)) {
            return [];
        }

        return [
            Attachment::fromPath($this->docxPath)
                ->as(basename($this->docxPath))
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }
}
