<?php

namespace App\Mail;

use App\Listeners\AppendEmailUnsubscribeFooterToMessage;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email as SymfonyEmail;

class VerifyUserEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $token,
    ) {
        $locale = in_array((string) $user->locale, config('locales.supported', []), true)
            ? (string) $user->locale
            : (string) config('locales.default', 'nl');

        $this->user->loadMissing('tenant');
        $this->locale($locale);
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
            $message->getHeaders()->addTextHeader(
                AppendEmailUnsubscribeFooterToMessage::SKIP_HEADER,
                '1',
            );
        });
    }

    public function verificationUrl(): string
    {
        return URL::route('verification.start', ['token' => $this->token], true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verify_email.subject', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        $minutes = max(1, (int) config('auth.verification.expire', 60));
        $hours = intdiv($minutes, 60);
        $validity = $minutes >= 120 && $minutes % 60 === 0
            ? __('mail.verify_email.validity_hours', ['hours' => $hours])
            : __('mail.verify_email.validity_minutes', ['minutes' => $minutes]);

        return new Content(
            text: 'emails.auth.verify-email-text',
            with: [
                'recipientName' => (string) $this->user->name,
                'tenantName' => $this->tenantName(),
                'verifyUrl' => $this->verificationUrl(),
                'validity' => $validity,
            ],
        );
    }

    private function tenantName(): string
    {
        return (string) ($this->user->tenant?->name ?? config('app.name'));
    }
}
