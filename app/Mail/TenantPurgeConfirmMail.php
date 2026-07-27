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
use Illuminate\Support\Facades\URL;

class TenantPurgeConfirmMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public TenantPurgeRequest $purgeRequest,
        public User $admin,
        public string $plainToken,
        public TenantPurgeTrack $track,
    ) {
        $locale = in_array((string) $admin->locale, config('locales.supported', []), true)
            ? (string) $admin->locale
            : (string) config('locales.default', 'nl');

        $this->locale($locale);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.tenant_purge.confirm.subject', ['tenant' => $this->purgeRequest->tenant_name]),
        );
    }

    public function content(): Content
    {
        $confirmUrl = URL::route('subscription.purge.confirm', [
            'purgeRequest' => $this->purgeRequest->id,
            'token' => $this->plainToken,
        ], true);

        return new Content(
            html: 'emails.contact.winprox-template',
            with: [
                'recipientName' => (string) $this->admin->name,
                'bodyText' => '',
                'bodyHtml' => view('emails.tenant-purge.confirm-body', [
                    'tenantName' => $this->purgeRequest->tenant_name,
                    'confirmUrl' => $confirmUrl,
                    'track' => $this->track->value,
                    'hours' => (int) config('tenant_purge.confirm_token_hours', 48),
                ])->render(),
            ],
        );
    }
}
