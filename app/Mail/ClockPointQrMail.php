<?php

namespace App\Mail;

use App\Mail\Marketing\PromoCampaignLetterMail;
use App\Models\ClockPoint;
use App\Models\Tenant;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Mime\Email as SymfonyEmail;

/**
 * Worker Clock Point link — same deliverability path as promo test mail:
 * Cloud86 promo mailbox + plain HTML (no marketing template / CTA button).
 * Fancy template + green button + /time|cp tokens was fingerprinting as spam
 * at Telenet/Gmail even when From was correct.
 */
class ClockPointQrMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public ClockPoint $clockPoint,
        public string $portalUrl,
        public string $mailLocale,
    ) {
        $supported = config('locales.supported', []);
        $locale = in_array($this->mailLocale, $supported, true)
            ? $this->mailLocale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
        $this->clockPoint->loadMissing('location');
        $this->mailer((string) config('winprox.promo_mailer', 'municipal_promo'));
        $this->withSymfonyMessage(function (SymfonyEmail $message): void {
            $message->getHeaders()->addTextHeader('X-WinProx-Transactional', '1');
        });
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: new Address(
                (string) config('winprox.municipal_promo_email_from.address'),
                (string) config('winprox.municipal_promo_email_from.name'),
            ),
            subject: __('mail.clock_point_qr.subject', ['tenant' => $this->tenantName()]),
        );
    }

    public function headers(): Headers
    {
        return new Headers(
            text: [
                PromoCampaignLetterMail::LAYOUT_HEADER => PromoCampaignLetterMail::LAYOUT_PLAIN,
            ],
        );
    }

    public function content(): Content
    {
        $location = $this->clockPoint->location;
        $locationLine = collect([$location?->localizedName(), $location?->formattedAddress()])
            ->filter()
            ->join(' · ');

        return new Content(
            html: 'emails.marketing.promo-plain',
            with: [
                'bodyHtml' => view('emails.time.clock-point-qr-body', [
                    'tenantName' => $this->tenantName(),
                    'locationLine' => $locationLine,
                    'portalUrl' => $this->portalUrl,
                ])->render(),
                'bodyText' => '',
                'recipientName' => '',
                'subject' => __('mail.clock_point_qr.subject', ['tenant' => $this->tenantName()]),
            ],
        );
    }

    private function tenantName(): string
    {
        return (string) ($this->tenant->name ?: config('app.name'));
    }
}
