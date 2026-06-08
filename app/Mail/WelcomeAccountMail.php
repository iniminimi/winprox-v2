<?php

namespace App\Mail;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WelcomeAccountMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $locale;

    public function __construct(
        public User $user,
        public Tenant $tenant,
        public User $admin,
        public string $resetToken,
    ) {
        // Use the tenant's locale if available, otherwise use admin's locale, fallback to app locale
        $this->locale = $this->tenant->locale ?? $this->admin->locale ?? app()->getLocale();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.welcome.subject', ['tenant' => $this->tenant->name]),
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $minutes = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire');

        return (new Content(
            html: 'mail.welcome-account',
            text: 'mail.welcome-account-text',
            with: [
                'userName' => $this->user->name,
                'userEmail' => $this->user->email,
                'userRole' => $this->user->role === User::ROLE_ADMIN
                    ? __('mail.welcome.role_admin')
                    : __('mail.welcome.role_employee'),
                'tenantName' => $this->tenant->name,
                'adminName' => $this->admin->name,
                'adminEmail' => $this->admin->email,
                'resetUrl' => $this->resetUrl(),
                'loginUrl' => URL::route('login', [], true),
                'minutes' => $minutes,
            ],
        ))->locale($this->locale);
    }

    private function resetUrl(): string
    {
        return URL::route('password.reset', [
            'token' => $this->resetToken,
            'email' => $this->user->email,
        ], true);
    }
}
