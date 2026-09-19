<?php

namespace App\Mail;

use App\Models\ClockPoint;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as SymfonyEmail;

class ClockPointQrMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public ClockPoint $clockPoint,
        public string $portalUrl,
        public string $qrPng,
        public string $mailLocale,
    ) {
        $supported = config('locales.supported', []);
        $locale = in_array($this->mailLocale, $supported, true)
            ? $this->mailLocale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
        $this->clockPoint->loadMissing('location');
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
            $message->embed($this->qrPng, 'clock-point-qr.png', 'image/png');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.clock_point_qr.subject', ['tenant' => $this->tenantName()]),
        );
    }

    public function content(): Content
    {
        $location = $this->clockPoint->location;
        $locationLine = collect([$location?->localizedName(), $location?->formattedAddress()])
            ->filter()
            ->join(' · ');

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => null,
                'tenantName' => $this->tenantName(),
                'bodyText' => '',
                'bodyHtml' => view('emails.time.clock-point-qr-body', [
                    'tenantName' => $this->tenantName(),
                    'locationLine' => $locationLine,
                    'portalUrl' => $this->portalUrl,
                ])->render(),
            ],
        );
    }

    private function tenantName(): string
    {
        return (string) ($this->tenant->name ?: config('app.name'));
    }
}
