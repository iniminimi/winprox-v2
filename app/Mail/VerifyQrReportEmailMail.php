<?php

namespace App\Mail;

use App\Models\QrReportEmailHold;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
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
        $this->hold->loadMissing(['unit.location', 'tenant']);
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        $tenantName = (string) ($this->hold->tenant?->name ?? config('app.name'));

        return new Envelope(
            subject: __('mail.verify_qr_report_email.subject', ['tenant' => $tenantName]),
        );
    }

    public function content(): Content
    {
        $confirmUrl = URL::route('public.qr-report-email-confirm', ['token' => $this->hold->token], true);
        $expiresInMinutes = max(1, (int) config('portal.qr_report_email_verification.expire_minutes', 60));
        $unit = $this->hold->unit;
        $location = $unit?->location;
        $locationLine = collect([$location?->name, $unit?->name])->filter()->join(' · ');
        $tenantName = (string) ($this->hold->tenant?->name ?? config('app.name'));
        $description = Str::limit(
            trim(preg_replace('/\s+/u', ' ', strip_tags((string) ($this->hold->description ?? ''))) ?? ''),
            500,
        );
        $photoCount = count($this->hold->storedPhotoPaths());

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => (string) ($this->hold->reporter_name ?? ''),
                'bodyText' => '',
                'bodyHtml' => view('emails.public.verify-qr-report-email-body', [
                    'confirmUrl' => $confirmUrl,
                    'expiresInMinutes' => $expiresInMinutes,
                    'tenantName' => $tenantName,
                    'locationLine' => $locationLine,
                    'address' => $location?->formattedAddress() ?? '',
                    'reporterName' => (string) ($this->hold->reporter_name ?? ''),
                    'description' => $description,
                    'photoCount' => $photoCount,
                ])->render(),
            ],
        );
    }
}
