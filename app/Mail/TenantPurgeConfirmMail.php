<?php

namespace App\Mail;

use App\Enums\TenantPurgeTrack;
use App\Models\TenantPurgeRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantPurgeConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantPurgeRequest $purgeRequest,
        public User $admin,
        public string $plainToken,
        public TenantPurgeTrack $track,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.confirm.subject', ['tenant' => $this->purgeRequest->tenant_name]),
        );
    }

    public function content(): Content
    {
        $url = route('subscription.purge.confirm', [
            'purgeRequest' => $this->purgeRequest->id,
            'token' => $this->plainToken,
        ]);

        return new Content(
            text: 'mail.tenant-purge-confirm',
            with: [
                'adminName' => $this->admin->name,
                'tenantName' => $this->purgeRequest->tenant_name,
                'confirmUrl' => $url,
                'track' => $this->track->value,
                'hours' => (int) config('tenant_purge.confirm_token_hours', 48),
            ],
        );
    }
}
