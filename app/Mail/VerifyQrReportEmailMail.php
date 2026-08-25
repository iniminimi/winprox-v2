<?php

namespace App\Mail;

use App\Models\QrReportEmailHold;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

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
        $unitName = (string) ($this->hold->unit?->name ?? '');
        $locationName = (string) ($this->hold->unit?->location?->name ?? '');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => $this->hold->reporter_name,
                'bodyText' => '',
                'bodyHtml' => view('emails.public.verify-qr-report-email-body', [
                    'confirmUrl' => $confirmUrl,
                    'expiresInMinutes' => $expiresInMinutes,
                    'unitName' => $unitName,
                    'locationName' => $locationName,
                ])->render(),
            ],
        );
    }
}
