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
        public string $docxPath,
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
            htmlString: $this->wrapEmailBody($this->emailBodyHtml),
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromPath($this->docxPath)
                ->as(basename($this->docxPath))
                ->withMime('application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ];
    }

    private function wrapEmailBody(string $bodyHtml): string
    {
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;line-height:1.6;color:#334155;">'
            .$bodyHtml
            .'</body></html>';
    }
}
