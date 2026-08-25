<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as SymfonyEmail;

class VerifyUserEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $verificationUrl,
        public int $expiresInMinutes,
    ) {
        $locale = in_array((string) $user->locale, config('locales.supported', []), true)
            ? (string) $user->locale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verify_email.subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => (string) $this->user->name,
                'bodyText' => '',
                'bodyHtml' => view('emails.auth.verify-email-body', [
                    'verificationUrl' => $this->verificationUrl,
                    'expiresInMinutes' => $this->expiresInMinutes,
                ])->render(),
            ],
        );
    }
}
