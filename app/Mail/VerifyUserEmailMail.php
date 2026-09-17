<?php

namespace App\Mail;

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
        });
    }

    public function verificationUrl(): string
    {
        return URL::route('verification.start', ['token' => $this->token], true);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.new_qr_issue.subject', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        $tenantName = $this->tenantName();
        $tenant = $this->user->tenant;
        $addressParts = array_filter([
            trim(trim((string) ($tenant?->street ?? '')).' '.trim((string) ($tenant?->house_number ?? ''))),
            trim(trim((string) ($tenant?->postal_code ?? '')).' '.trim((string) ($tenant?->city ?? ''))),
        ]);

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => (string) $this->user->name,
                'tenantName' => $tenantName,
                'bodyText' => '',
                'bodyHtml' => view('emails.issues.new-qr-body', [
                    'tenantName' => $tenantName,
                    'locationLine' => $tenantName,
                    'address' => implode(', ', $addressParts),
                    'description' => __('mail.verify_email.description'),
                    'reporterName' => (string) $this->user->name,
                    'issueUrl' => $this->verificationUrl(),
                ])->render(),
            ],
        );
    }

    private function tenantName(): string
    {
        return (string) ($this->user->tenant?->name ?? config('app.name'));
    }
}
