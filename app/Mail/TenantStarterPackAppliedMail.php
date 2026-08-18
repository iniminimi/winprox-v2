<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\TenantStarterPackType;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TenantStarterPackAppliedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $actor,
        public TenantStarterPackType $type,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.starter_pack.subject', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.tenant-starter-pack-applied',
            with: [
                'packType' => __($this->type->labelKey()),
            ],
        );
    }
}
