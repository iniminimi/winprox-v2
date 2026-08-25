<?php

namespace App\Mail;

use App\Models\QrReportEmailHold;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

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
            subject: __('mail.verify_qr_report_email.subject', ['snippet' => $this->subjectSnippet()]),
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
        $photoPaths = $this->hold->storedPhotoPaths();
        $photoUrls = [];
        foreach ($photoPaths as $path) {
            $photoUrls[] = URL::asset('storage/'.$path);
        }

        $submittedAt = $this->hold->created_at
            ? $this->hold->created_at->timezone((string) config('app.timezone'))->format('d/m/Y H:i')
            : '';

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
                    'locationLine' => collect([$locationName, $unitName])->filter()->join(' · '),
                    'address' => $location?->formattedAddress() ?? '',
                    'reporterName' => (string) ($this->hold->reporter_name ?? ''),
                    'reporterEmail' => (string) ($this->hold->reporter_contact ?? ''),
                    'description' => (string) ($this->hold->description ?? ''),
                    'submittedAt' => $submittedAt,
                    'photoCount' => count($photoPaths),
                    'photoUrls' => $photoUrls,
                ])->render(),
            ],
        );
    }

    private function subjectSnippet(): string
    {
        $snippet = trim(preg_replace('/\s+/', ' ', (string) ($this->hold->description ?? '')) ?? '');
        if ($snippet === '') {
            $snippet = (string) ($this->hold->unit?->name ?? '');
        }

        return $snippet === '' ? '…' : Str::limit($snippet, 50);
    }
}
