<?php

namespace App\Mail;

use App\Models\TenantPurgeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class TenantPurgeExpiredTrialWarningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantPurgeRequest $purgeRequest,
        public User $admin,
    ) {
        $locale = in_array((string) $admin->locale, config('locales.supported', []), true)
            ? (string) $admin->locale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.expired_trial_warning.subject', [
                'tenant' => $this->purgeRequest->tenant_name,
            ]),
        );
    }

    public function content(): Content
    {
        $when = $this->purgeRequest->scheduled_purge_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');

        $subscriptionUrl = URL::route('subscription.index', [], true);
        $retentionDays = (int) config('tenant_purge.backup_retention_days', 30);

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => (string) $this->admin->name,
                'bodyText' => '',
                'bodyHtml' => view('emails.tenant-purge.expired-trial-warning-body', [
                    'tenantName' => $this->purgeRequest->tenant_name,
                    'scheduledAt' => $when ?? '—',
                    'timezone' => config('app.timezone'),
                    'subscriptionUrl' => $subscriptionUrl,
                    'retentionDays' => $retentionDays,
                ])->render(),
            ],
        );
    }
}
