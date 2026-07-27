<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class TenantPurgeCompletedMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, int>  $counts
     */
    public function __construct(
        public string $tenantName,
        public string $adminName,
        public array $counts,
        public Carbon $backupExpiresAt,
        public string $adminLocale = 'nl',
    ) {
        $locale = in_array($adminLocale, config('locales.supported', []), true)
            ? $adminLocale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.completed.subject', ['tenant' => $this->tenantName]),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => $this->adminName,
                'bodyText' => '',
                'bodyHtml' => view('emails.tenant-purge.completed-body', [
                    'tenantName' => $this->tenantName,
                    'counts' => $this->counts,
                    'backupExpiresAt' => $this->backupExpiresAt
                        ->timezone(config('app.timezone'))
                        ->format('d/m/Y'),
                ])->render(),
            ],
        );
    }
}
