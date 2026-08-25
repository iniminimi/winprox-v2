<?php

namespace App\Mail;

use App\Models\QrReportEmailHold;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Symfony\Component\Mime\Email as SymfonyEmail;

class VerifyQrReportEmailMail extends Mailable
{
    use SerializesModels;

    public function __construct(public QrReportEmailHold $hold)
    {
        $supported = config('locales.supported', []);
        $locale = (string) ($this->hold->original_language ?: config('locales.default', 'nl'));
        if (! in_array($locale, $supported, true)) {
            $locale = (string) config('locales.default', 'nl');
        }

        $this->locale($locale);
        $this->hold->loadMissing('unit.location');
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.verify_qr_report_email.subject'),
        );
    }

    public function content(): Content
    {
        $confirmUrl = URL::route('public.qr-report-email-confirm', ['token' => $this->hold->token], true);
        $expiresInMinutes = max(1, (int) config('portal.qr_report_email_verification.expire_minutes', 60));
        $unit = $this->hold->unit;
        $location = $unit?->location;
        $unitName = (string) ($unit?->name ?? '');
        $locationName = (string) ($location?->name ?? '');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => null,
                'bodyText' => '',
                'bodyHtml' => view('emails.public.verify-qr-report-email-body', [
                    'confirmUrl' => $confirmUrl,
                    'expiresInMinutes' => $expiresInMinutes,
                    'guestName' => (string) ($this->hold->reporter_name ?? ''),
                    'unitName' => $unitName,
                    'locationName' => $locationName,
                ])->render(),
            ],
        );
    }
}
