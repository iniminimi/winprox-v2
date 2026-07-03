<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewTenantRegisteredMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $admin,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.new_tenant.subject', ['tenant' => $this->tenant->name]),
        );
    }

    public function content(): Content
    {
        return new Content(
            text: 'mail.new-tenant-registered',
            with: [
                'phone' => $this->tenant->phone ?? '—',
                'address' => $this->tenant->organisationAddressLine() ?? '—',
                'country' => $this->tenant->country_code ?? '—',
            ],
        );
    }
}
