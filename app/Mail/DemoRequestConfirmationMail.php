<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as SymfonyEmail;

class DemoRequestConfirmationMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $preference,
        string $locale,
    ) {
        $supported = config('locales.supported', []);
        $resolved = in_array($locale, $supported, true)
            ? $locale
            : (string) config('locales.default', 'nl');

        $this->locale($resolved);
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.demo_request.confirmation_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => $this->recipientName,
                'bodyText' => '',
                'bodyHtml' => view('emails.demo.confirmation-body', [
                    'preferenceLabel' => __('demo.form.preference_'.$this->preference),
                ])->render(),
            ],
        );
    }
}
