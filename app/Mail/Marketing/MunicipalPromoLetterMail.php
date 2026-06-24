<?php

namespace App\Mail\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MunicipalPromoLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $municipalityName,
        public string $promoUrl,
        public string $docxPath,
    ) {
        $this->locale('nl');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('winprox.municipal_promo_email_from.address'),
                (string) config('winprox.municipal_promo_email_from.name'),
            ),
            subject: __('mail.municipal_promo_letter.subject', ['municipality' => $this->municipalityName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'mail.municipal-promo-letter',
            text: 'mail.municipal-promo-letter-text',
            with: [
                'municipalityName' => $this->municipalityName,
                'promoUrl' => $this->promoUrl,
            ],
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
}
