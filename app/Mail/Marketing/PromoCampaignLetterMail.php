<?php

namespace App\Mail\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PromoCampaignLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public const LAYOUT_HEADER = 'X-WinProx-Email-Layout';

    public const LAYOUT_PLAIN = 'plain';

    public function __construct(
        public string $emailSubject,
        public string $emailBodyHtml,
        public string $mailLocale,
        public bool $plainLayout = false,
    ) {
        $this->locale($mailLocale);
        $this->mailer((string) config('winprox.promo_mailer', 'municipal_promo'));
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

    public function headers(): Headers
    {
        if (! $this->plainLayout) {
            return new Headers;
        }

        return new Headers(
            text: [
                self::LAYOUT_HEADER => self::LAYOUT_PLAIN,
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: $this->plainLayout
                ? 'emails.marketing.promo-plain'
                : 'emails.contact.winprox-template',
            with: [
                'bodyHtml' => $this->emailBodyHtml,
                'bodyText' => '',
                'recipientName' => '',
                'subject' => $this->emailSubject,
            ],
        );
    }

    /**
     * @return list<\Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
