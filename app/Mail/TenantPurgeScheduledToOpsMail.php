<?php

namespace App\Mail;

use App\Models\TenantPurgeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPurgeScheduledToOpsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public TenantPurgeRequest $purgeRequest)
    {
        $this->locale((string) config('locales.default', 'nl'));
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.ops_scheduled.subject', [
                'tenant' => $this->purgeRequest->tenant_name,
            ]),
        );
    }

    public function content(): Content
    {
        $when = $this->purgeRequest->scheduled_purge_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');

        return new Content(
            text: 'mail.tenant-purge-scheduled-to-ops',
            with: [
                'tenantName' => $this->purgeRequest->tenant_name,
                'tenantId' => $this->purgeRequest->tenant_id,
                'track' => $this->purgeRequest->track->value,
                'scheduledAt' => $when ?? '—',
                'timezone' => (string) config('app.timezone'),
                'purgeRequestId' => $this->purgeRequest->id,
            ],
        );
    }
}
