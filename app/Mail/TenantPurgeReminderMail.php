<?php

namespace App\Mail;

use App\Models\TenantPurgeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPurgeReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantPurgeRequest $purgeRequest,
        public User $admin,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.reminder.subject', ['tenant' => $this->purgeRequest->tenant_name]),
        );
    }

    public function content(): Content
    {
        $when = $this->purgeRequest->scheduled_purge_at
            ?->timezone(config('app.timezone'))
            ->format('d/m/Y H:i');

        return new Content(
            text: 'mail.tenant-purge-reminder',
            with: [
                'adminName' => $this->admin->name,
                'tenantName' => $this->purgeRequest->tenant_name,
                'scheduledAt' => $when ?? '—',
                'timezone' => config('app.timezone'),
                'subscriptionUrl' => route('subscription.index'),
            ],
        );
    }
}
